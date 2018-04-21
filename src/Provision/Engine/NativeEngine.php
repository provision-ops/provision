<?php

namespace Aegir\Provision\Engine;

use Aegir\Provision\EngineInterface;

class NativeEngine implements EngineInterface {

    public function preVerify()
    {
        return [];
    }

    public function postVerify()
    {
        return [];
    }
}