<?php
/**
 * @file
 * @author Lightly Salted Software Ltd
 * @date   16/04/17
 */

namespace LSS\YAContainer\Fixture;

class PrivateConstructor
{
    private EngineInterface $engine;

    private function __construct(EngineInterface $engine)
    {
        $this->engine = $engine;
    }
}