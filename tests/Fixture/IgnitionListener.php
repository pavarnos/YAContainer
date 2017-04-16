<?php
/**
 * @file
 * @author Lightly Salted Software Ltd
 * @date   16/04/17
 */

namespace LSS\YAContainer\Fixture;

class IgnitionListener
{
    /** @var bool */
    public $started = false;

    public function onStart()
    {
        $this->started = true;
    }

    public function onStop()
    {
        $this->started = false;
    }
}