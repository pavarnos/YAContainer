<?php
/**
 * @file
 * @author Lightly Salted Software Ltd
 * @date   16/04/17
 */

namespace LSS\YAContainer\Fixture;

interface EngineInterface
{
    /**
     * @param \LSS\YAContainer\Fixture\IgnitionListener $listener
     */
    public function setIgnitionListener(IgnitionListener $listener): void;

    /**
     * @return \LSS\YAContainer\Fixture\IgnitionListener
     */
    public function getIgnitionListener(): IgnitionListener;
}