<?php
/**
 * @file
 * @author Lightly Salted Software Ltd
 * @date   16/04/17
 */

namespace LSS\YAContainer\Fixture;

trait IgnitionListenerAwareTrait
{
    public IgnitionListener $ignitionListener;

    public function setIgnitionListener(IgnitionListener $listener): void
    {
        $this->ignitionListener = $listener;
    }

    public function getIgnitionListener(): IgnitionListener
    {
        return $this->ignitionListener;
    }
}