<?php

namespace Aegir\Provision;

use Aegir\Provision\Common\ProvisionAwareTrait;
use Aegir\Provision\Console\ProvisionStyle;
use Drupal\Console\Core\Style\DrupalStyle;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Robo\Common\ConfigAwareTrait;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Drupal\Console\Core\Command\Shared\CommandTrait;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Command
 *
 * @package Aegir\Provision\Command
 */
abstract class Command extends BaseCommand
{

    use CommandTrait;
    use ProvisionAwareTrait;
    use LoggerAwareTrait;

    /**
     * Set if this command requires a context. If so provision will automatically ask for which one if not specified..
     */
    const CONTEXT_REQUIRED = FALSE;

    /**
     * Set if this command is only for certain context types.
     */
    const CONTEXT_REQUIRED_TYPES = null;

    const CONTEXT_REQUIRED_QUESTION = 'Which context';
    /**
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;
    
    /**
     * @var ProvisionStyle;
     */
    protected $io;

    /**
     * @var \Aegir\Provision\Console\Config
     */
    protected $config;

    /**
     * @var \Aegir\Provision\Context;
     */
    public $context;

    /**
     * @var string
     */
    public $context_name;
    
    /**
     * @param InputInterface $input An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     */
    protected function initialize(
      InputInterface $input,
      OutputInterface $output
    ) {
        $this->input = $input;
        $this->output = $output;
        
        $this->io = new ProvisionStyle($input, $output);
        
        // Load active context if a command uses the argument.
        if ($this->input->getOption('context') && !empty($this->input->getOption('context'))) {

            try {
                // Load context from context_name option.
                $this->context_name = $this->input->getOption('context');
                $this->context = $this->getProvision()->getContext($this->context_name);
            }
            catch (\Exception $e) {

                // If no context with the specified name is found:
                // if this is "save" command and option for --delete is used, throw exception: context must exist to delete.
                if ($this->getName() == 'context:save' && $input->getOption('delete')) {
                    throw new \Exception("No context named {$this->context_name}. Unable to delete.");
                }
                // If this is any other command, context is required.
                elseif ($this->getName() != 'context:save') {
                    throw new InvalidArgumentException($e->getMessage());
                }
            }
        }
        
        // If context_name is not specified, ask for it.
        elseif (($this::CONTEXT_REQUIRED && empty($this->input->getOption('context')))
            || ($this->getName() == 'save' && empty($this->input->getOption('context')))
        ) {

            $this->askForContext($this::CONTEXT_REQUIRED_QUESTION, $this::CONTEXT_REQUIRED_TYPES);
            $this->input->setOption('context', $this->context_name);

            try {
                $this->context = $this->getProvision()->getContext($this->context_name);
            }
            catch (\Exception $e) {
                $this->context = NULL;
            }
        }
    }
    
    /**
     * Show a list of Contexts to the user for them to choose from.
     */
    public function askForContext($question = self::CONTEXT_REQUIRED_QUESTION, $context_types = array()) {
        if (empty($this->getProvision()->getAllContextsOptions())) {
            throw new \Exception('No contexts available! use `provision save` to create one.');
        }

        $this->context_name = $this->io->choice($question, $this->getProvision()->getAllContextsOptions($context_types));
    }
    
    /**
     * Run a process.
     *
     * @param $cmd
     */
    protected function process($cmd)
    {
        $this->output->writeln(["Running: $cmd"]);
        shell_exec($cmd);
    }

    /**
     * Gets the application instance for this command.
     *
     * @return \Aegir\Provision\Application
     *
     * @api
     */
    public function getApplication()
    {
        return parent::getApplication();
    }
}
