<?php
/**
 * @file
 * @author Lightly Salted Software Ltd
 * @date   16/04/17
 */

namespace LSS\YAContainer\Fixture;

class PrivateConstructor
{
    /**
     * @var \LSS\YAContainer\Fixture\EngineInterface
     */
    private $engine;

    /**
     * @param \LSS\YAContainer\Fixture\EngineInterface $engine
     */
    private function __construct(EngineInterface $engine)
    {
        $this->engine = $engine;
    }
}