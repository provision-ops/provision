<?php

namespace Aegir\Provision\Command\Ui;

use Aegir\Provision\Command;
use Aegir\Provision\Context;
use Aegir\Provision\Context\PlatformContext;
use Aegir\Provision\Context\ServerContext;
use Aegir\Provision\Context\SiteContext;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Class VerifyCommand
 *
 * Replacement for drush hostmaster-install command.
 *
 * Basically all of this code was written years ago for the `devshop install:devmaster` command.
 *
 * See https://github.com/opendevshop/devshop/blob/1.x/src/DevShop/Command/InstallDevmaster.php for the original code.
 *
 * @package Aegir\Provision\Command
 * @see provision.drush.inc
 * @see drush_provision_verify()
 */
class UiCreateCommand extends Command
{

    /**
     * Executes the command.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        // Validate the database.
        if ($this->validateSecureDatabase()) {
            $this->output->writeln('<info>Database is secure.</info>');
        }
        else {
            $this->output->writeln('<error>Database is NOT Secure. Run "mysql_secure_installation" or see https://dev.mysql.com/doc/refman/5.7/en/mysql-secure-installation.html for more information.</error>');
            return;
        }

        // Confirm all of the options.
        $this->validateOptions();

        // Prepare "aegir contexts"
        $this->prepareContexts();

        // Finalize setup: Clear drush caches and run "hosting-setup" to create cronjobs.
        $this->finalize();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('ui:create')
            ->setDescription('Setup the web user interface for Provision.')
            ->setHelp(
            'Prepare server and site contexts with the Provision web interface. Equivalent to the legacy "drush hostmaster-install" command.'
            )

            // master_drush_alias
            ->addOption(
                'master_drush_alias', NULL, InputOption::VALUE_OPTIONAL,
                "The string to use for the master site's drush alias.",
                'hostmaster'
            )

            // site
            ->addOption(
                'site', NULL, InputOption::VALUE_OPTIONAL,
                'The front-end URL to use for Devmaster.'
            )

            // devshop_version
            ->addOption(
                'provision_ui_version', NULL, InputOption::VALUE_OPTIONAL,
                'The version to install. Will default to the latest devshop version.'
            )

            // remote_host
            ->addOption(
                'remote_host', NULL, InputOption::VALUE_OPTIONAL,
                'The aegir host. Will default to the detected hostname of this server.'
            )

            // script_user
            ->addOption(
                'script_user', NULL, InputOption::VALUE_OPTIONAL,
                'The user running this script. Will default to the detected user.'
            )

            // db_host
            ->addOption(
                'db_host', NULL, InputOption::VALUE_OPTIONAL,
                'The database host.',
                'localhost'
            )

            // db_port
            ->addOption(
                'db_port', NULL, InputOption::VALUE_OPTIONAL,
                'The database server port.',
                '3306'
            )

            // db_user
            ->addOption(
                'db_user', NULL, InputOption::VALUE_OPTIONAL,
                'The database user, one that is allowed to CREATE new databases.',
                'root'
            )

            // aegir_db_pass
            ->addOption(
                'db_pass', NULL, InputOption::VALUE_OPTIONAL,
                'The database password for the "db_user"',
                'root'
            )

            // profile
            ->addOption(
                'profile', NULL, InputOption::VALUE_OPTIONAL,
                'The desired install profile.',
                'devmaster'
            )

            // makefile
            ->addOption(
                'makefile', NULL, InputOption::VALUE_OPTIONAL,
                'The makefile to use to build the platform.',
                // @TODO: Change to a provision-provided default.
                'https://raw.githubusercontent.com/opendevshop/devshop/2.x/build-devmaster.make'
            )

            // aegir_root
            ->addOption(
                'aegir_root', NULL, InputOption::VALUE_OPTIONAL,
                'The home directory for the "aegir" user.  If not specified will be automatically detected.'
            )

            // root
            ->addOption(
                'root', NULL, InputOption::VALUE_OPTIONAL,
                'The desired path to install to.  Example: /var/aegir/devmaster-0.x. If not specified, will be created from aegir_root, profile, and version.'
            )

            // http_service_type
            ->addOption(
                'server_http', NULL, InputOption::VALUE_OPTIONAL,
                'The HTTP service to use: apache or nginx',
                'apache'
            )

            // http_port
            ->addOption(
                'http_port', NULL, InputOption::VALUE_OPTIONAL,
                'The port that the webserver should use.',
                '80'
            )

            // web_group
            ->addOption(
                'web_group', NULL, InputOption::VALUE_OPTIONAL,
                'The web server user group. If not specified, will be detected automatically.'
            )

            // client_name
            ->addOption(
                'client_name', NULL, InputOption::VALUE_OPTIONAL,
                'The name of the aegir "client".',
                'admin'
            )

            // client_email
            // If not specified, will use the remote_host
            ->addOption(
                'client_email', NULL, InputOption::VALUE_OPTIONAL,
                'The email to use for the administrator user.'
            )

            // working_copy
            ->addOption(
                'working-copy', NULL, InputOption::VALUE_NONE,
                'Passed to drush make: use to clone the source code using git.'
            )
            // path_to_drush
            ->addOption(
                'drush-path', NULL, InputOption::VALUE_OPTIONAL,
                'Path to drush executable',
                dirname(dirname(dirname(dirname(__DIR__)))) . DIRECTORY_SEPARATOR . 'bin/drush'
            )
        ;
    }

    /**
     * Generate the list of options derived from ProvisionContextType classes.
     *
     * @return \Symfony\Component\Console\Input\InputDefinition
     */
    protected function getCommandDefinition()
    {
        $inputDefinition = [];
        return new InputDefinition($inputDefinition);
    }

    /**
     * Initializes the command. We set more complex default options here.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     */
    protected function initialize(InputInterface $input, OutputInterface $output) {

        parent::initialize($input, $output);

        $output->writeln('');

        $output->writeln('This command will operate the following changes in your system:');
        $output->writeln('');
        $output->writeln(' 1. Create provision "contexts" for:');
        $output->writeln('   - Server <comment>server_master</comment> (with http and db services)');
        $output->writeln('   - server_$db_host if different than remote_host. (db server)');
//        $output->writeln('   - platform_provision_io (devmaster codebase)');
        $output->writeln('   - Site <comment>provision_ui</comment> (Provision Web Interface)');
        $output->writeln(' 2. Install the provision_ui site');
//        $output->writeln(' 3. Setup a cron job to run `drush @hostmaster hosting-tasks.`');
        $output->writeln('');

        // provision_ui_version
        $version = $input->getOption('provision_ui_version');
        if (empty($version)) {
            $input->setOption('provision_ui_version', $this->getApplication()->getVersion());
        }
        else {
            // Validate chosen version
            $output->writeln('Validating version...');
            try {
                $this->checkVersion($version);
            }
            catch (\Exception $e) {
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                exit(1);
            }
        }

        // site
        if (!$input->getOption('site')) {
            $input->setOption('site', $this->findFqdn());
        }

        // remote_host
        if (!$input->getOption('remote_host')) {
            $input->setOption('remote_host', $this->findFqdn());
        }

        // script_user
        if (!$input->getOption('script_user')) {
            $input->setOption('script_user', $this->findCurrentUser());
        }

        // makefile
        if (!$input->getOption('makefile')) {
            $input->setOption('makefile', realpath(dirname(__FILE__) . '/../../../build-devmaster.make'));
        }

        // aegir_root
        if (!$input->getOption('aegir_root')) {
            $input->setOption('aegir_root', getenv('HOME'));
        }

        // root
        if (!$input->getOption('root')) {
            $root = $input->getOption('aegir_root') . '/' . $input->getOption('profile') . '-' . $input->getOption('provision_ui_version');
            $input->setOption('root', $root);
        }

        // aegir_db_pass
        // Here, we look for an existing server_localhost and load the root password from there.
        if (file_exists($input->getOption('aegir_root') . '/.drush/server_localhost.alias.drushrc.php')) {
            $aliases = array('server_localhost');
            include $input->getOption('aegir_root') . '/.drush/server_localhost.alias.drushrc.php';
            if (isset($aliases['server_localhost']['master_db'])) {
                $input->setOption('aegir_db_pass', parse_url($aliases['server_localhost']['master_db'], PHP_URL_PASS));
                $input->setOption('db_user', parse_url($aliases['server_localhost']['master_db'], PHP_URL_USER));
            }

        }


        // web_group
        if (!$input->getOption('web_group')) {
            $input->setOption('web_group', $this->findDefaultWebGroup());
        }

        // client_email
        if (!$input->getOption('client_email')) {
            if ($input->getOption('remote_host') == 'localhost') {
                $default_email = 'webmaster@example.com';
            }
            else {
                $default_email = 'webmaster@' . $input->getOption('remote_host');
            }
            $input->setOption('client_email', $default_email);
        }
    }

    /**
     * Ensure the database cannot be accessed by anonymous users, as it will
     * otherwise fail later in the install, and thus be harder to recover from.
     */
    private function validateSecureDatabase() {
        $command = sprintf('mysql -u intntnllyInvalid -h %s -P %s -e "SELECT VERSION()"', $this->input->getOption('db_host'), $this->input->getOption('db_port'));

        // Run the Mysql process to test the database.
        $process = new Process($command);

        try {
            $process->mustRun();
            $output = $process->getOutput();

            if (preg_match("/Access denied for user 'intntnllyInvalid'@'([^']*)'/", $output, $match)) {
                return TRUE;
            }
            elseif (preg_match("/Host '([^']*)' is not allowed to connect to/", $output, $match)) {
                return TRUE;
            }
            else {
                return FALSE;
            }

        } catch (ProcessFailedException $e) {
            return TRUE;
        }
    }

    /**
     * Validate the users command line options.
     */
    private function validateOptions() {

        $options = $this->input->getOptions();

        $options = array_diff_key($options, array(
            'help' => '',
            'quiet' => '',
            'verbose' => '',
            'version' => '',
            'ansi' => '',
            'no-ansi' => '',
            'no-interaction' => '',
        ));

        foreach ($options as $option => $value) {
            $this->output->writeln("<info>{$option}:</info> {$value}");
        }

        $this->output->writeln('');
        if ($this->input->isInteractive() && !$this->io->confirm('Continue installation with these options? ')) {
            $this->output->writeln('<fg=red>Installation aborted.');
            $this->output->writeln('');
            exit(1);
        }
    }

    /**
     * Determine which web server user group exists on this server.
     *
     * @return null
     */
    private function findDefaultWebGroup() {
        $info = posix_getgrgid(posix_getgid());
        $common_groups = array(
            'www-data',
            'apache',
            'nginx',
            'www',
            '_www',
            'webservd',
            'httpd',
            'nogroup',
            'nobody',
            $info['name']);

        foreach ($common_groups as $group) {
            if ($this->findPosixGroupname($group)) {
                return $group;
                break;
            }
        }
        return NULL;
    }

    /**
     * return the FQDN of the machine or provided host
     *
     * this replicates hostname -f, which is not portable
     *
     * Copy of provision_fqdn()
     */
    private function findFqdn($host = NULL) {
        if (is_null($host)) {
            $host = php_uname('n');
        }
        return strtolower(gethostbyaddr(gethostbyname($host)));
    }

    /**
     * Get's the current user (the one running this command.)
     * @return int
     *
     * Copy of provision_current_user();
     */
    private function findCurrentUser() {
        $user = posix_geteuid();
        if (is_numeric($user)) {
            $info = posix_getpwuid($user);
            $user = $info['name'];
        }
        else {
            $info = posix_getpwnam($user);
            $user = $info['name'];
        }
        return $user;
    }

    /**
     * Replacement for provision_posix_groupname()
     *
     * @param $group
     * @return mixed
     */
    private function findPosixGroupname($group){
        // TODO: make these singletons with static variables for caching.
        // we do this both ways, so that the function returns NULL if no such user was found.
        if (is_numeric($group)) {
            $info = posix_getgrgid($group);
            $group = $info['name'];
        }
        else {
            $info = posix_getgrnam($group);
            $group = $info['name'];
        }
        return $group;
    }

    /**
     * Prepares aegir "contexts" (aka drush aliases) for server_master,
     * server_localhost, and platform_hostmaster.
     *
     * Contexts:
     *
     * - [x] server_master: This server. Home to devmaster site.
     * - [x] server_localhost: The database server.  Not used if "db_host" is
     *   the same as "remote_host".
     * - [ ] platform_hostmaster: The aegir platform for the hostmaster/devmaster front-end site.
     * - [ ] hostmaster: The hostmaster/devmaster front-end site.
     *
     * @TODO: This is still in progress. Platform and site are not yet saved.*
     */
    private function prepareContexts() {

        // Get Database Server Credentials from options.
        $master_db = sprintf("mysql://%s:%s@%s:%s",
            urlencode($this->input->getOption('db_user')),
            urlencode($this->input->getOption('aegir_db_pass')),
            $this->input->getOption('db_host'),
            $this->input->getOption('db_port')
        );

        // If the db host and web host are different...
        if ($this->input->getOption('remote_host') != $this->input->getOption('db_host')) {

            // Create Database Server Context.
            $db_server = 'server_' . $this->input->getOption('db_host');
            $this->saveContext($db_server, array(
                'remote_host' => $this->input->getOption('db_host'),
                'context_type' => 'server',
                'db_service_type' => 'mysql',
                'master_db' => $master_db,
                'db_port' => $this->input->getOption('db_port'),
            ));

            $server_master_db_service_type = NULL;
            $server_master_master_db = NULL;
        }
        // If the db host and web host are the same...
        else {

            // Save
            $db_server = 'server_master';
            $server_master_db_service_type = 'mysql';
            $server_master_master_db = $master_db;
        }

        // Save @server_master
        $this->saveContext('server_master', array(
            'context_type'      => 'server',
            'remote_host'       => $this->input->getOption('remote_host'),
            'aegir_root'        => $this->input->getOption('aegir_root'),
            'script_user'       => $this->input->getOption('script_user'),
//            'http_service_type' => $this->input->getOption('http_service_type'),
            'http_port'         => $this->input->getOption('http_port'),
            'web_group'         => $this->input->getOption('web_group'),
//            'master_url'        => "http://" . $this->input->getOption('site'),
            'db_port'           => $this->input->getOption('db_port'),
//            'db_service_type'   => $server_master_db_service_type,
            'master_db'         => $server_master_master_db,
        ));

        // Save Hostmaster Platform
        $server = '@server_master';
//        $this->saveContext('platform_hostmaster', array(
//            'context_type'      => 'platform',
//            'server' => $server,
//            'web_server' => $server,
//            'root' => $this->input->getOption('root'),
//            'makefile' => $this->input->getOption('makefile'),
//        ));

        // Save Hostmaster Site context, and flag for installation, pre-verify.
        $platform_name = '@platform_hostmaster';
        $this->saveContext('hostmaster', array(
            'context_type' => 'site',
//            'platform' => $platform_name,
            'server_db' => '@' . $db_server,
            'server_http' => '@' . $db_server,
            'uri' => $this->input->getOption('site'),
            'root' => $this->input->getOption('root'),
//            'client_name' => $this->input->getOption('client_name'),
            'profile' => $this->input->getOption('profile'),
//            'drush_aliases' => 'hm',
//            'https_enabled' => 1, // HOSTING_HTTPS_ENABLED
        ), TRUE);

        // So... saveContext() saves the alias, then runs provision-verify.
        // install.hostmaster.inc runs provision-save, then runs provision-install, then runs provision-verify.
        // I'm going to leave it for the moment, and let $this->saveContext() verify before install, to see what happens.
    }

    /**
     * Saves data to a aegir "context".
     *
     * We skip using provision-save because of complexity.  It is much easier to
     * just write a new context file.
     *
     * @param $name
     * @param $data
     */
    private function saveContext($name, $data, $install = FALSE) {

        if (!$this->input->isInteractive() || $this->io->confirm("Save {$data['context_type']} {$name}?")) {
            $command = $this->getApplication()->find('context:save');

            $arguments["--context"] = $name;
            foreach ($data as $option_name => $option_value) {
                $arguments["--$option_name"] = $option_value;
            }

            // Pass specific options through
            foreach (array(
                        'quiet',
                         'verbose',
                         'ansi',
                         'no-ansi',
                         'no-interaction',
                         'makefile',
                         'make_working_copy',
                         'make_working_copy',
                     ) as $key){
                $arguments["--{$key}"] = $this->input->getOption($key);
            }


            $input = new ArrayInput($arguments);

            print "RUNNING context:save for $name with  "; print_r($arguments);

            // @TODO: Figure out why $command includes a context by default.
            $command->context = NULL;
            $provision_save_exit_code = $command->run($input, $this->output);
            if ($provision_save_exit_code !== 0) {
                throw new \Exception('provision context:save command did not execute successfully.');
            }
        }
    }

    //
//        $data_export = var_export($data, TRUE);
//        $output = <<<PHP
//<?php
///**
// * @file
// * An Aegir Context, written by the `devshop devmaster:install` command.
// *
// * Changes to this file will be overwritten on the next "provision-verify".
// */
//\$aliases['$name'] = $data_export;
//
//PHP;
//
//        // Determine home path and path to alias file.
//        $drush_path = $this->input->getOption('drush-path');
//        $home = $this->input->getOption('aegir_root');
//        $path_to_alias_file = "{$home}/.drush/{$name}.alias.drushrc.php";
//
//        // Notify user.
//        $this->output->writeln("Writing alias file {$path_to_alias_file}...");
//        $this->output->writeln("<comment>$output</comment>");
//
//        // Dump to file
//        $fs = new Filesystem();
//        $fs->dumpFile($path_to_alias_file, $output);
//        $this->output->writeln("<info>Done</info>");
//
//        // If this is hostmaster, we need to install first.  provision-verify will fail, otherwise.
//        if ($install) {
//            $client_email = $this->input->getOption('client_email');
//            $this->output->writeln("");
//            $this->output->writeln("Running <comment>{$drush_path} @{$name} provision-install --client_email={$client_email}</comment> ...");
//            $process = $this->getProcess("{$drush_path} @{$name} provision-install --client_email={$client_email} -v");
//            $process->setTimeout(NULL);
//
//            // Ensure process runs sucessfully.
//            if ($this->runProcess($process)) {
//                $this->output->writeln("");
//                $this->output->writeln("Running <comment>drush @{$name} provision-install</comment>: <info>Done</info>");
//                $this->output->writeln("");
//            }
//            else {
//                $this->output->writeln("");
//                $this->output->writeln("<error>Unable to run drush @{$name} provision-install.");
//                $this->output->writeln("");
//                exit(1);
//            }
//        }
//
//        // Run provision-verify
//        $drush_path = $this->input->getOption('drush-path');
//        $this->output->writeln("");
//        $this->output->writeln("Running <comment>drush @{$name} provision-verify</comment> ...");
//        $process = $this->getProcess("{$drush_path} @{$name} provision-verify");
//        $process->setTimeout(NULL);
//
//        if ($this->runProcess($process)) {
//            $this->output->writeln("");
//            $this->output->writeln("Running <comment>drush @{$name} provision-verify</comment>: <info>Done</info>");
//            $this->output->writeln("");
//        }
//        else {
//            $this->output->writeln("");
//            $this->output->writeln("<error>Unable to run drush @{$name} provision-verify.");
//            $this->output->writeln("");
//            exit(1);
//        }
//    }

    /**
     * Last steps:
     *   Clear drush cache.
     *   Run `drush hosting-setup`
     */
    private function finalize() {

        // Run `drush cc drush`
        $drush_path = $this->input->getOption('drush-path');
        if ($this->runProcess(new Process("{$drush_path} cc drush"))) {
            $this->output->writeln("");
            $this->output->writeln("Running <comment>drush cc drush</comment>: <info>Done</info>");
            $this->output->writeln("");
        }
        else {
            $this->output->writeln("");
            $this->output->writeln("<error>Unable to run drush cc drush. Cannot continue.</error>");
            $this->output->writeln("");
            exit(1);
        }

        // Run `drush @hostmaster hosting-setup`
        // @see install.hostmaster.inc: 275
        $master_drush_alias = $this->input->getOption('master_drush_alias');
        if ($this->runProcess(new Process("{$drush_path} @{$master_drush_alias} hosting-setup -y"))) {
            $this->output->writeln("");
            $this->output->writeln("Running <comment>drush @{$master_drush_alias} hosting-setup</comment>: <info>Done</info>");
            $this->output->writeln("");
        }
        else {
            $this->output->writeln("");
            $this->output->writeln("<error>Unable to run drush @{$master_drush_alias} hosting-setup.</error>");
            $this->output->writeln("");
            exit(1);
        }

        // Run `drush @hostmaster cc drush`
        if ($this->runProcess(new Process("{$drush_path} @{$master_drush_alias} cc drush"))) {
            $this->output->writeln("");
            $this->output->writeln("Running <comment>drush @{$master_drush_alias} cc drush</comment>: <info>Done</info>");
            $this->output->writeln("");
        }
        else {
            $this->output->writeln("");
            $this->output->writeln("<error>Unable to run drush @{$master_drush_alias} cc drush. Cannot continue.</error>");
            $this->output->writeln("");
            exit(1);
        }
    }
}
