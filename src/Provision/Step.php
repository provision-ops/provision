<?php

namespace Aegir\Provision;

class Step {

  public $startPrefix = '☐';

    function __construct()
    {
        $this->callable = function () {
            return 0;
        };
    }

    function execute($callable)
    {
        $this->callable = $callable;
        return $this;
    }
    function start($message) {

        $this->start = $message;
        return $this;
    }
    function success($message) {

        $this->success = $message;
        return $this;
    }
    function failure($message) {
        $this->failure = $message;
        return $this;
    }
    function startPrefix($prefix) {
        $this->startPrefix = $prefix;
        return $this;
    }

    /**
     * Provide an easy way to create a new step.
     *
     * Usage:
     *
     *   $steps['1'] = Step::create()
     *     ->start('Doing something...')
     *     ->success('Doing something... Worked!')
     *
     *
     * @return \Aegir\Provision\Step
     */
    public static function create() {
            return new self();
    }
}