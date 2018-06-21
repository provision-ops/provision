<?php

namespace Aegir\Provision\Console;

use Monolog\Logger as BaseLogger;

class Logger extends BaseLogger {

    const COMMAND = 700;
    const CONSOLE = 701;

    /**
     * Log a message indicating that it is a console command.
     *
     * @param $output
     * @param array $context
     *
     * @return bool
     */
    public function command($output, $context = []) {
        return $this->log(self::COMMAND, $output, $context);
    }

    /**
     * Log a message indicating that it is a console command.
     *
     * @param $output
     * @param array $context
     *
     * @return bool
     */
    public function console($output, $context = []) {
        return $this->log(self::CONSOLE, $output, $context);
    }


    /**
     * Logging levels from syslog protocol defined in RFC 5424
     *
     * @var array $levels Logging levels
     */
    protected static $levels = array(
        self::DEBUG     => 'DEBUG',
        self::INFO      => 'INFO',
        self::NOTICE    => 'NOTICE',
        self::WARNING   => 'WARNING',
        self::ERROR     => 'ERROR',
        self::CRITICAL  => 'CRITICAL',
        self::ALERT     => 'ALERT',
        self::EMERGENCY => 'EMERGENCY',
        self::COMMAND   => 'COMMAND',
        self::CONSOLE   => 'CONSOLE',
    );

}