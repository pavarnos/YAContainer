<?php
/**
 * @file
 * @author Lightly Salted Software Ltd
 * @date   16/04/17
 */

namespace LSS\YAContainer\Fixture;

class Car
{
    /**
     * @var \LSS\YAContainer\Fixture\EngineInterface
     */
    private $engine;

    /**
     * Car constructor.
     * @param \LSS\YAContainer\Fixture\EngineInterface $engine
     */
    public function __construct(EngineInterface $engine)
    {
        $this->engine = $engine;
    }
}