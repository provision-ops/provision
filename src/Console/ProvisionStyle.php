<?php

namespace Aegir\Provision\Console;

use Aegir\Provision\Provision;
use Drupal\Console\Core\Style\DrupalStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

class ProvisionStyle extends DrupalStyle {

    /**
     * @var BufferedOutput
     */
    protected $bufferedOutput;
    protected $input;
    protected $lineLength;

    /**
     * Icons
     */
    const ICON_EDIT = '✎';
    const ICON_START = '▷';
    const ICON_FINISH = '🏁';
    const ICON_FAILED = '🔥';
    const ICON_COMMAND = '$';

    public function __construct(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->bufferedOutput = new BufferedOutput($output->getVerbosity(), false, clone $output->getFormatter());
        // Windows cmd wraps lines as soon as the terminal width is reached, whether there are following chars or not.
        $width = (new Terminal())->getWidth() ?: self::MAX_LINE_LENGTH;
        $this->lineLength = min($width - (int) (DIRECTORY_SEPARATOR === '\\'), self::MAX_LINE_LENGTH);

        parent::__construct($input, $output);
    }

    public function taskInfoBlock($task_id, $op, $status = 'none') {


        switch ($op) {
            case 'started':
            default:
                $bg = 'black';
                $fg = 'blue';
                $icon = ' ' . self::ICON_START;
                $op = ucfirst($op);
                break;
            case 'completed':
                $bg = 'black';
                $fg = 'green';
                $icon = self::ICON_FINISH;
                $op = ucfirst($op);
                break;

            case 'failed':
                $bg = 'black';
                $fg = 'red';
                $icon = self::ICON_FAILED;
                $op = ucfirst($op);
                break;

        }

        $app_name = Provision::APPLICATION_FUN_NAME;

        $task_word = 'Task';
        $message = "{$app_name} {$icon} {$task_word} {$op}";
        $timestamp = date('r');
        $message_suffix = $task_id;
        $spaces = $this::MAX_LINE_LENGTH - strlen($message . $message_suffix) - 2;
        $message .= str_repeat(' ', $spaces) . $message_suffix;
        $message .= "\n" . $timestamp;


        $this->autoPrependBlock();
        $this->block(
            $message,
            NULL,
            "bg=$bg;fg=$fg",
            '  ',
            TRUE
        );
    }

    public function commandBlock($message, $directory = '') {
        $this->autoPrependBlock();
        $this->customLite($message, $directory . ' <fg=yellow>' . self::ICON_COMMAND . '</>', '');
    }

    public function outputBlock($message) {
        $this->block(
            $message,
            NULL,
            'fg=yellow;bg=black',
            ' ╎ ',
            TRUE
            );
    }

    /**
     * Replacement for parent::autoPrependBlock(), allowing access and setting newLine to 1 - instead of 2 -.
     */
    private function autoPrependBlock()
    {
        $chars = substr(str_replace(PHP_EOL, "\n", $this->bufferedOutput->fetch()), -2);

        if (!isset($chars[0])) {
            return $this->newLine(); //empty history, so we should start with a new line.
        }
        //Prepend new line for each non LF chars (This means no blank line was output before)
        $this->newLine(1 - substr_count($chars, "\n"));
    }
}