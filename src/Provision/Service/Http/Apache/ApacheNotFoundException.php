<?php

namespace Aegir\Provision\Service\Http\Apache;

class ApacheNotFoundException extends \Exception {

    /**
     * ApacheNotFoundException constructor.
     *
     * @TODO: Help the user figure out their options. Should apache be installed natively? If so show instructions for how to do it?
     *
     * @param array $options
     * @param int $code
     * @param \Throwable|NULL $previous
     */
    public function __construct($options = [], $code = 0, \Throwable $previous = NULL) {
        $message = 'No apache executable was found. Are you sure it is installed? I looked for: ' . implode(', ', $options);
        parent::__construct($message, $code, $previous);
    }
}