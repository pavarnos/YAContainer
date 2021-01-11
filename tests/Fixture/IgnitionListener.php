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

    public function onStart(): void
    {
        $this->started = true;
    }

    public function onStop(): void
    {
        $this->started = false;
    }
}