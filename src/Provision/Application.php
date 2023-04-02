<?php

namespace Aegir\Provision;

use Aegir\Provision\Command\CdCommand;
use Aegir\Provision\Command\EditCommand;
use Aegir\Provision\Command\SaveCommand;
use Aegir\Provision\Command\ServicesCommand;
use Aegir\Provision\Command\ShellCommand;
use Aegir\Provision\Command\StatusCommand;
use Aegir\Provision\Command\Ui\CreateUiCommand;
use Aegir\Provision\Command\Ui\UiCreateCommand;
use Aegir\Provision\Command\VerifyCommand;
use Aegir\Provision\Command\InstallCommand;
use Aegir\Provision\Common\ProvisionAwareTrait;
use Aegir\Provision\Console\Config;
use Aegir\Provision\Console\ConsoleOutput;
use Drupal\Console\Core\Style\DrupalStyle;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\Command as BaseCommand;

//use Symfony\Component\DependencyInjection\ContainerInterface;
//use Drupal\Console\Annotations\DrupalCommandAnnotationReader;
//use Drupal\Console\Utils\AnnotationValidator;
//use Drupal\Console\Core\Application as BaseApplication;


/**
 * Class Application
 *
 * @package Aegir\Provision
 */
class Application extends BaseApplication
{
    /**
     * @var string
     */
    const CONSOLE_CONFIG = '.provision.yml';

    /**
     * @var string
     */
    const DEFAULT_TIMEZONE = 'America/New_York';

    use ProvisionAwareTrait;
    use LoggerAwareTrait;
    
    /**
     * @var ConsoleOutput
     */
    public $console;

    private static $logo = '
    ____                  _      _                __ __
   / __ \_________ _   __(_)____(_)___  ____     / // /
  / /_/ / ___/ __ \ | / / / ___/ / __ \/ __ \   / // /_
 / ____/ /  / /_/ / |/ / (__  ) / /_/ / / / /  /__  __/
/_/   /_/   \____/|___/_/____/_/\____/_/ /_/     /_/   
                                
';
    private static $logoLarge = '

                    ██                 ▄▄▄██████▄▄▄
                   ██               ▄████▀▀██████████▄▄
                  ▄██             ▄██████▌   ▀██████████▄
                 ▄██▌             ████████     ███████████▄
                ▄███              ▀███████      ███████████▌
               ▄███▌                ▀▀▀▀        █████████████
               ████  ▐▄                         ██████████████
              █████   █▄                       ████████████████
             ▄█████ ▌ ▀██▄                   ▄██████████████████
            ▄██████ █  ▀████▄▄            ▄▄█████████████████████
           ▄███████  █▄  ▀████████████████████████████████████████
           ████████  ███▄   ▀█████████████████████████████████████▌
          ██████████  █████▄      ▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀████████
         ███████████▌  ████████▄▄▄ 
        ▄████████████▌   ████████████████▄▄▄▄▄▄▄▄▄▄▄▄        
       ▄███████████████▄   ▀█████████████████████████████████████████▌
      ▄██████████████████▄    ▀▀██████████████████████████████████████▌
      ██████████████████████▄▄      ▀▀▀████████████████████████████████▄
     ████████████████████████████▄▄▄            ▀▀▀▀▀███████████████████▄
    ▄█████████████████████████████████████▄▄▄▄                 ▀▀▀▀▀█████
   ▄█████████████████████████████████████████████████▄▄▄                  
  ▄███████████████████████████████████████████████████████████▄▄▄▄ 
 ▄█████████████████████████████████████████████████████████████████████▄▄
▄███████████████████████████████████████████████████████████████████████████
▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀
    ____                  _      _                __ __
   / __ \_________ _   __(_)____(_)___  ____     / // /
  / /_/ / ___/ __ \ | / / / ___/ / __ \/ __ \   / // /_
 / ____/ /  / /_/ / |/ / (__  ) / /_/ / / / /  /__  __/
/_/   /_/   \____/|___/_/____/_/\____/_/ /_/     /_/   
                                
';
    /**
     * Application constructor.
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Aegir\Provision\Console\OutputInterface
     *
     * @throws \Exception
     */
    public function __construct($name = 'UNKNOWN', $version = 'UNKNOWN')
    {
        // If no timezone is set, set Default.
        if (empty(ini_get('date.timezone'))) {
            date_default_timezone_set($this::DEFAULT_TIMEZONE);
        }
        
        parent::__construct($name, $version);
    }

    /**
     * Output at the top of the "list" command.
     * @return string
     */
    public function getHelp()
    {
        $help = $this->getProvision()->getOutput()->isVerbose()? self::$logoLarge : self::$logo;
        $help .= parent::getHelp();
        return $help;
    }

    /**
     * Make configureIO public so we can run it before ->run()
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function configureIO(InputInterface $input, OutputInterface $output)
    {
        parent::configureIO($input, $output);
    }

    /**
     * Initializes all the default commands.
     */
    protected function getDefaultCommands()
    {
        $commands[] = new CdCommand();
        $commands[] = new HelpCommand();
        $commands[] = new ListCommand();
        $commands[] = new SaveCommand();
        $commands[] = new EditCommand();
        $commands[] = new SetupCommand();
        $commands[] = new ServicesCommand();
        $commands[] = new ShellCommand();
        $commands[] = new StatusCommand();
        $commands[] = new VerifyCommand();
        $commands[] = new InstallCommand();
//        $commands[] = new UiCreateCommand();

        return $commands;
    }
    
    /**
     * Interrupts Command execution to add services like provision and logger.
     *
     * @param \Symfony\Component\Console\Command\Command        $command
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int
     */
    protected function doRunCommand( BaseCommand $command, InputInterface $input, OutputInterface $output)
    {
        // Only setProvision if the command is using the trait.
        if (method_exists($command, 'setProvision')) {
            $command
                ->setProvision($this->getProvision())
                ->setLogger($this->logger)
            ;
        }
        $exitCode = parent::doRunCommand($command, $input, $output);
        return $exitCode;
    }

    /**
     * {@inheritdoc}
     *
     * Adds "--target" option.
     */
    protected function getDefaultInputDefinition()
    {
        $inputDefinition = parent::getDefaultInputDefinition();
        $inputDefinition->addOption(
          new InputOption(
            '--context',
            '-c',
            InputOption::VALUE_OPTIONAL,
            'The target context to act on.'
          )
        );

        return $inputDefinition;
    }
}
