<?php

namespace Aegir\Provision\Engine;

interface EngineInterface {

    /**
     * Return a list of steps before any services are verified.
     *
     * @return \Aegir\Provision\Step[]
     */
    public function preVerify();


    /**
     * Return a list of steps after any services are verified.
     *
     * @return \Aegir\Provision\Step[]
     */
    public function postVerify();
}