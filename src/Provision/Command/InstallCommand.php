<?php

namespace Aegir\Provision\Command;

use Aegir\Provision\Command;
use Aegir\Provision\Context;
use Aegir\Provision\Context\PlatformContext;
use Aegir\Provision\Context\ServerContext;
use Aegir\Provision\Context\SiteContext;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class VerifyCommand
 *
 * Replacement for drush provision-verify command
 *
 * @package Aegir\Provision\Command
 * @see provision.drush.inc
 * @see drush_provision_verify()
 */
class InstallCommand extends Command
{

    /**
     * This command needs a context.
     */
    const CONTEXT_REQUIRED = TRUE;
    const CONTEXT_REQUIRED_TYPES = ['site'];
    const CONTEXT_REQUIRED_QUESTION = 'Install which site';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
          ->setName('install')
          ->setDescription('Run the install process for a site.')
          ->setHelp(
            'Run this command to prepare the site or web app, installing database tables and preparing folders, for example.'
          )
          ->setDefinition($this->getCommandDefinition());
    }

    /**
     * Generate the list of options derived from ProvisionContextType classes.
     *
     * @return \Symfony\Component\Console\Input\InputDefinition
     */
    protected function getCommandDefinition()
    {
        $inputDefinition = [];

        $inputDefinition[] = new InputOption(
            'option',
            null,
            InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
            "Pass install options to the sites install command."
        );

        $inputDefinition[] = new InputOption(
            'skip-verify',
            null,
            InputOption::VALUE_NONE,
            "By default, the 'provision verify' command is run before the install process starts. Pass --skip-verify to skip it."
        );

        return new InputDefinition($inputDefinition);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$input->getOption('skip-verify')) {
            $this->context->runSteps('verify');
        }

        // @TODO: Allow dynamic options to be passed to the install command.
        $options = $input->getOption('option');

        $site = $this->context;
        $service = $site->getSubscription('db');
        $server = $service->server->getProperty('remote_host');
        $root = $site->getProperty('root');
        $document_root = $site->getProperty('document_root_full');
        $site_dir = str_replace('sites/', '', $site->getProperty('site_path'));

        // @TODO: Convert to Tasks!
        // @TODO: Create Subclasses from SiteContext for DrupalSiteContext
        // @TODO: Figure out a better way to run a drush command.
        // @TODO: Create system to detect the proper install method: Site local drush 9, provision/drush 8?

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
            ->silent(!$this->getProvision()->getOutput()->isVerbose())
        ;

        // @TODO: Add getDbUrl() method to make this easier.
        $command->arg("--db-url=mysql://{$service->getProperty('db_user')}:{$service->getProperty('db_password')}@{$server}:{$service->server->getService('db')->getProperty('db_port')}/{$service->getProperty('db_name')}");

        $command->arg("--root={$document_root}");
        $command->arg("--sites-subdir={$site_dir}");

        $cmd = $command->getCommand();
        return $site->getService('http')->provider->shell_exec($cmd, $root, 'exit');
    }
}
