<?php

namespace Aegir\Provision\Command;

use Aegir\Provision\Command;
use Aegir\Provision\Provision;
use Psy\Shell;
use Psy\Configuration;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DrushCommand
 *
 * @package Aegir\Provision\Command
 */
class DrushCommand extends Command
{
    const CONTEXT_REQUIRED = TRUE;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('drush')
            ->setDescription($this->trans('commands.drush.description'))
            ->setHelp($this->trans('commands.drush.help'))
            ->setDefinition($this->getCommandDefinition());
          ;
    }

    /**
     * Generate the list of options derived from ProvisionContextType classes.
     *
     * @return \Symfony\Component\Console\Input\InputDefinition
     */
    protected function getCommandDefinition() {
        $inputDefinition[] = new InputArgument(
            'drush_command_string',
            InputArgument::OPTIONAL,
            'The full drush command to run including options.'
        );
        return $inputDefinition;
    }


    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int|null|void
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        // Generate the full path and command. (Currently using built in drush (v8)
        // Provision's built in drush acts as a wrapper for site local drush when run in that directory.
        $command = dirname(dirname(dirname(dirname(__FILE__)))) . '/bin/drush ' . $input->getArgument('drush_command_string');

        $this->getProvision()->getLogger()->debug("Running $command");

        $this->context->process_exec($command, $this->context->getWorkingDir());
    }
}
