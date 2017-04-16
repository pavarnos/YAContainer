<?php
/**
 * @file
 * @author Lightly Salted Software Ltd
 * @date   16/04/17
 */

namespace LSS\YAContainer\Fixture;

trait IgnitionListenerAwareTrait
{
    /** @var \LSS\YAContainer\Fixture\IgnitionListener */
    public $ignitionListener;

    /**
     * @param \LSS\YAContainer\Fixture\IgnitionListener $listener
     */
    public function setIgnitionListener(IgnitionListener $listener): void
    {
        $this->ignitionListener = $listener;
    }

    /**
     * @return \LSS\YAContainer\Fixture\IgnitionListener
     */
    public function getIgnitionListener(): \LSS\YAContainer\Fixture\IgnitionListener
    {
        return $this->ignitionListener;
    }
}