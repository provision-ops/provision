<?php

namespace Aegir\Provision\Context;

use Aegir\Provision\Application;
use Aegir\Provision\ServiceSubscriber;
use Aegir\Provision\Provision;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class SiteContext
 *
 * @package Aegir\Provision\Context
 *
 * @see \Provision_Context_site
 */
class SiteContext extends PlatformContext implements ConfigurationInterface
{
    /**
     * @var string
     */
    public $type = 'site';
    const TYPE = 'site';

    const FORCE_VERBOSE_INSTALL = TRUE;
    /**
     * @var \Aegir\Provision\Context\PlatformContext
     */
    public $platform;


    /**
     * SiteContext constructor.
     *
     * @param $name
     * @param Application $application
     * @param array $options
     */
    function __construct(
        $name,
        Provision $provision = NULL,
        $options = []
    ) {
        parent::__construct($name, $provision, $options);

        // Load "web_server" and "platform" contexts.
        // There is no need to check if the property exists because the config system does that.
//        $this->db_server = $application->getContext($this->properties['db_server']);

        // Load platform context... @TODO: Automatically do this for required contexts?
        if (!empty($this->properties['platform'])) {
            $this->platform = $this->getProvision()->getContext($this->properties['platform']);
        }
        else {
            $this->platform = NULL;
        }
    }

    /**
     *
     */
    public function preSave() {
        if (empty($this->getProperty('site_path'))) {
            $this->setProperty('site_path', 'sites/' . DIRECTORY_SEPARATOR . $this->getProperty('uri'));
        }
    }

    static function option_documentation()
    {

        // @TODO: check for other sites with the URI.
        $options['uri'] = Provision::newProperty()
            ->description('site: example.com URI, no http:// or trailing /')
        ;
        $options['aliases'] = Provision::newProperty()
            ->description('site: comma-separated URIs')
            ->defaultValue('')
            ->required(FALSE)
        ;
        $options['redirection'] = Provision::newProperty()
            ->description('site: boolean for whether domain aliases should redirect to the primary domain; default false')
            ->defaultValue(FALSE)
            ->required(FALSE)
        ;
        $options['https_enabled'] = Provision::newProperty()
            ->description('site: Enable HTTPS')
            ->defaultValue(FALSE)
            ->required(FALSE)
        ;

        // @TODO: Use this use case to develop a plugin system.
        $options['http_basic_auth_username'] = Provision::newProperty()
            ->description('site: Basic authentication username')
            ->defaultValue('')
            ->required(FALSE)
        ;
        $options['http_basic_auth_password'] = Provision::newProperty()
            ->description('site: Basic authentication password')
            ->defaultValue('')
            ->required(FALSE)
        ;
        $options['http_basic_auth_message'] = Provision::newProperty()
            ->description('site: Basic authentication message')
            ->defaultValue('')
            ->required(FALSE)
        ;
        $options['http_basic_auth_whitelist'] = Provision::newProperty()
            ->description('site: Basic authentication grant list. Allows access from these IPs.')
            ->defaultValue('')
            ->required(FALSE)
        ;

        $options['platform'] = Provision::newProperty()
            ->description('site: The platform this site is run on. (Optional)')
            ->required(FALSE)
        ;

        $options = array_merge($options, parent::option_documentation());

        $options['language'] = Provision::newProperty('site: site language; default en')
            //@TODO: Language handling across provision, and an arbitrary site install values tool.
            ->defaultValue('en')
        ;
        $options['profile'] = Provision::newProperty('site: Drupal profile to use; default standard')
            ->defaultValue('standard')
        ;
        $options['site_path'] = Provision::newProperty()
            ->description('site: The site configuration path (sites/domain.com). If left empty, will be generated automatically.')
            ->defaultValue('sites/default')
            ->required(FALSE)
        ;

        return $options;


//          'uri' => 'site: example.com URI, no http:// or trailing /',
//          'language' => 'site: site language; default en',
//          'aliases' => 'site: comma-separated URIs',
//          'redirection' => 'site: boolean for whether --aliases should redirect; default false',
//          'client_name' => 'site: machine name of the client that owns this site',
//          'install_method' => 'site: How to install the site; default profile. When set to "profile" the install profile will be run automatically. Otherwise, an empty database will be created. Additional modules may provide additional install_methods.',
//          'profile' => 'site: Drupal profile to use; default standard',
//          'drush_aliases' => 'site: Comma-separated list of additional Drush aliases through which this site can be accessed.',
//
//            'site_path' =>
//                Provision::newProperty()
//                    ->description('site: The site configuration path (sites/domain.com). If left empty, will be generated automatically.')
//                    ->required(FALSE)
//            ,
//
//        ];
    }

    public static function serviceRequirements() {
        $requirements[] = 'http';
        $requirements[] = 'db';
        return $requirements;
    }

    /**
     * Output extra info before verifying.
     */
    public function verify()
    {

        $steps = parent::verify();

        // @TODO: These should be in a step. Maybe in runSteps()
        $this->getProvision()->io()->customLite($this->getProperty('uri'), 'Site URL: ', 'info');
        $this->getProvision()->io()->customLite($this->getProperty('root'), 'Root: ', 'info');
        $this->getProvision()->io()->customLite($this->config_path, 'Configuration File: ', 'info');
        $this->getProvision()->io()->newLine();

        // If a composer.json file is found, run composer install.
        if (Provision::fs()->exists($this->getProperty('root') . '/composer.json') && $composer_command = $this->getProperty('composer_install_command')) {
            $dir = $this->getProperty('root');
            $steps['composer.install'] = Provision::newStep()
                ->start("Running <comment>$composer_command</comment> in <comment>$dir</comment> ...")
                ->execute(function () use ($composer_command) {
                    return $this->shell_exec($composer_command, NULL, 'exit');
                });
        }

        $steps['site.prepare'] = Provision::newStep()
            ->start('Preparing Drupal site configuration...')

            /**
             * There are many ways to do this...
             * This way is very verbose and I cannot figure out how to quiet it down.
             */
//            ->execute($this->getProvision()->getTasks()->taskFilesystemStack()
//                ->mkdir("$path/sites/$uri/files")
//                ->chmod("$path/sites/$uri/files", 02770)
//                ->chgrp("$path/sites/$uri/files", $this->getServices('http')->getProperty('web_group'))

                /**
                 * @TODO: Break this up into chunks. People would like to see "setting file permissions" and "creating settings file"
                 * @TODO: Create subclasses. This is only the site.prepare task for Drupal sites.
                 *
                 * @see verify.provision.inc
                 * @see drush_provision_drupal_pre_provision_verify()
                 */
                ->execute(function() {
                    $docroot = $this->getProperty('document_root_full');
                    $site_path = $docroot . DIRECTORY_SEPARATOR . $this->getProperty('site_path');

                // @TODO: These folders are how aegir works now. We might want to rethink what folders are created.
                    // Directories set to 755
                    $this->fs->mkdir("$site_path");
                    $this->fs->chmod($site_path, 0755);

                    // Directories set to 02775
                    $this->fs->mkdir([
                        "$site_path/themes",
                        "$site_path/modules",
                        "$site_path/libraries",
                    ]);
                    $this->fs->chmod([
                        "$site_path/themes",
                        "$site_path/modules",
                        "$site_path/libraries",
                    ], 02775);


                    // Directories set to 02775
                    $this->fs->mkdir([
                        "$site_path/files",
                    ]);
                    $this->fs->chmod([
                        "$site_path/files",
                    ], 02770);

                    // Change certain folders to be in web server group.
                // @TODO: chgrp only works when running locally with apache.
                // @TODO: Figure out a way to store host web group vs container web group, and get it working with docker web service.
                // @TODO: Might want to do chgrp verification inside container?

                    $dir = "$site_path/files";
//                    $user = $this->getProvision()->getConfig()->get('web_user');
//                    $this->getProvision()->getLogger()->warning("Running chgrp {$dir} {$user}");
//                    $this->fs->chgrp($dir, $user);

                    // Copy Drupal's default settings.php file into place.
                    if (!$this->fs->exists("$site_path/settings.php")) {
                        $this->fs->copy("$docroot/sites/default/default.settings.php", "$site_path/settings.php");
                    }

                    $this->fs->chmod("$site_path/settings.php", 02770);
//                    $this->fs->chgrp("$site_path/settings.php", $this->getProvision()->getConfig()->get('web_user'));

            });
        return $steps;
    }

    /**
     * Steps needed for the 'provision install' command.
     *
     * @TODO: Make this the Drupal Subclass.
     *
     * @return array
     * @throws \Exception
     */
    public function install() {

        $site = $this;
        $service = $site->getSubscription('db');
        $server = $service->server->getProperty('remote_host');
        $root = $site->getProperty('root');
        $document_root = $site->getProperty('document_root_full');
        $site_dir = str_replace('sites/', '', $site->getProperty('site_path'));

        // @TODO: Figure out a better way to run a drush command.
        // @TODO: Create better system to detect the proper install method: Site local drush 9, provision/drush 8?

        if (file_exists("{$root}/bin/drush")) {
            $drush = realpath("{$root}/bin/drush");
        }
        elseif (file_exists("{$root}/vendor/bin/drush")) {
            $drush = realpath("{$root}/vendor/bin/drush");
        }
        elseif (file_exists(__DIR__ . '/../../../bin/drush')) {
            $drush = realpath(__DIR__ . '/../../../bin/drush');
        }
        else {
            throw new \Exception('Unable to drush in your site. Please install drush into your codebase or (COMING SOON) specify an alternate install command in your provision.yml file.');
        }

        $command = $this->getProvision()->getTasks()->taskExec($drush)
            ->arg('site-install')
            ->arg($this->getProperty('profile'))
            ->silent(!$this->getProvision()->getOutput()->isVerbose())
        ;

        // @TODO: Add getDbUrl() method to make this easier.
        $command->arg("--db-url=mysql://{$service->getProperty('db_user')}:{$service->getProperty('db_password')}@{$server}:{$service->server->getService('db')->getProperty('db_port')}/{$service->getProperty('db_name')}");

        $command->arg("--root={$document_root}");
        $command->arg("--sites-subdir={$site_dir}");
        $command->arg($this->getProvision()->getInput()->getOption("ansi")? '--ansi': '');

        // Allow dynamic options to be passed to the install command.
        $options = $this->getProvision()->getInput()->getOption('option');
        foreach ($options as $option) {
            $command->arg($option);
        }

        $cmd = $command->getCommand();

        if (!$this->getProvision()->getInput()->getOption('skip-verify')) {
            $steps = $this->verify();
        }

        $steps['site.install'] = Provision::newStep()
            ->start("Installing Drupal with the '{$this->getProperty('profile')}' profile...")
            ->execute(function () use ($cmd, $site, $root) {
                // Site install script output is important, so we force verbosity.... I think
                // @TODO: drush site-install returns non-zero exit code!
                return $site->getService('http')->provider->shell_exec($cmd, $root, 'exit', self::FORCE_VERBOSE_INSTALL) | 0;
            });

        return $steps;

    }

    /**
     * Return a list of folders to create in the Drupal root.
     *
     * @TODO: Move this to the to-be-created DrupalPlatform class.
     */
    function siteFolders($uri = 'default') {
        return [
            "sites/$uri",
            "sites/$uri/files",
        ];
    }


    /**
     * Replacement for Drupal\Component\Utility\Crypt::randomBytes(),
     * used for generating settings.php hash_salt.
     *
     * @param $count
     * @return bool|string
     */
    public static function randomBytes($count) {
        $random_state = print_r($_SERVER, TRUE);
        if (function_exists('getmypid')) {
            // Further initialize with the somewhat random PHP process ID.
            $random_state .= getmypid();
        }
        $bytes = '';
// Ensure mt_rand() is reseeded before calling it the first time.
        mt_srand();
        do {
            $random_state = hash('sha256', microtime() . mt_rand() . $random_state);
            $bytes .= hash('sha256', mt_rand() . $random_state, TRUE);
        } while (strlen($bytes) < $count);
        $output = substr($bytes, 0, $count);
        $bytes = substr($bytes, $count);
        return $output;
    }

    /**
     * Returns a URL-safe, base64 encoded string of highly randomized bytes.
     *
     * @param $count
     *   The number of random bytes to fetch and base64 encode.
     *
     * @return string
     *   The base64 encoded result will have a length of up to 4 * $count.
     *
     * @see \Drupal\Component\Utility\Crypt::randomBytes()
     */
    public static function randomBytesBase64($count = 32) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(static::randomBytes($count)));
    }
}
