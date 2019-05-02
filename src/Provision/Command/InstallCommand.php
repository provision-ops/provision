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
    const CONTEXT_REQUIRED_TYPES = array('site');
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


        $this->io->title(strtr("Verify %type: %name", [
            '%name' => $this->context_name,
            '%type' => $this->context->type,
        ]));

        /**
         * The provision-verify command function looks like:
         *
         *
        function drush_provision_verify() {
        provision_backend_invoke(d()->name, 'provision-save');
        d()->command_invoke('verify');
        }
         */

        $this->context->runSteps('install');
    }
}
