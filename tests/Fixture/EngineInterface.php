<?php
/**
 * @file
 * @author Lightly Salted Software Ltd
 * @date   16/04/17
 */

namespace LSS\YAContainer\Fixture;

interface EngineInterface
{
    public function setIgnitionListener(IgnitionListener $listener): void;

    public function getIgnitionListener(): IgnitionListener;
}