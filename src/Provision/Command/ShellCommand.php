<?php

namespace Aegir\Provision\Command;

use Aegir\Provision\Command;
use Aegir\Provision\Provision;
use Aegir\Provision\Service\DockerServiceInterface;
use Psy\Shell;
use Psy\Configuration;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ShellCommand
 *
 * @package Aegir\Provision\Command
 */
class ShellCommand extends Command
{
    const CONTEXT_REQUIRED = TRUE;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
          ->setName('shell')
          ->setDescription($this->trans('commands.shell.description'))
          ->setHelp($this->trans('commands.shell.help'));
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $messages = [];
        $process = new \Symfony\Component\Process\Process("bash");
        $process->setTty(TRUE);
        $dir = $this->context->getWorkingDir();

        if ($this->context->type == 'site') {

            $ps1 = Provision::APPLICATION_FUN_NAME . ' \[\e[33m\]'  . $this->context_name . '\[\e[m\] \w \[\e[36;40m\]\\$\[\e[m\] ';
            $process->setWorkingDirectory($dir);
            $process->setCommandLine("cd $dir && PS1='$ps1' bash");

            //@TODO: Allow services to set environment variables for both shell command and virtualhost config.
            $env = $_SERVER;
            $env['db_type'] = $this->context->getSubscription('db')->service->getType();
            $env['db_name'] = $this->context->getSubscription('db')->getProperty('db_name');
            $env['db_user'] = $this->context->getSubscription('db')->getProperty('db_user');
            $env['db_passwd'] = $this->context->getSubscription('db')->getProperty('db_password');

            // @TODO: We shouldn't always rely on what remote_host says.
            $env['db_host'] = $this->context->getSubscription('db')->service->provider->getProperty('remote_host');
            $env['db_port'] = $this->context->getSubscription('db')->service->getCreds()['port'];

            // If bin dir is found, add to path
            if (file_exists($this->context->getProperty('root') . '/bin')) {
                $env['PATH'] .= ':' . $this->context->getProperty('root') . '/bin';
            }
            if (file_exists($this->context->getProperty('root') . '/vendor/bin')) {
                $env['PATH'] .= ':' . $this->context->getProperty('root') . '/vendor/bin';
            }

            $process->setEnv($env);
        }

        if ($this->context->hasService('http') && $this->context->getService('http') instanceof DockerServiceInterface) {
            $process->setWorkingDirectory($this->context->service('http')->provider->getWorkingDir());
            $process->setCommandLine("docker-compose exec http bash");
        }

        $messages[] = "Opening bash shell in " . $dir;
        $messages[] = "Running the command: " . $process->getCommandLine();

        $messages[] = 'The commands composer, drupal, drush and more are available.';
        $messages[] = 'Type "exit" to leave.';

        $this->io->commentBlock($messages);
        $process->run();
    }
}
