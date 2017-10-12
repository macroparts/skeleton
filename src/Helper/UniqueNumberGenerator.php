<?php

namespace Macroparts\Vortex\Helper;

class UniqueNumberGenerator
{
    private $current = 0;

    public function next()
    {
        return ++$this->current;
    }

    public function reset()
    {
        $this->current = 0;
    }
}
