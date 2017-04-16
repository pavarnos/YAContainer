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
     * @var int
     */
    public $fuelPercent = 0;

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

    /**
     * @return \LSS\YAContainer\Fixture\EngineInterface
     */
    public function getEngine(): \LSS\YAContainer\Fixture\EngineInterface
    {
        return $this->engine;
    }

    public function refuel($percentage = 100): void
    {
        $this->fuelPercent = $percentage;
    }
}