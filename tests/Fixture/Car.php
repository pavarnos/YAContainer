<?php
/**
 * @file
 * @author Lightly Salted Software Ltd
 * @date   16/04/17
 */

namespace LSS\YAContainer\Fixture;

class Car
{
    public int $fuelPercent = 0;

    private EngineInterface $engine;

    public function __construct(EngineInterface $engine)
    {
        $this->engine = $engine;
    }

    public function getEngine(): EngineInterface
    {
        return $this->engine;
    }

    public function refuel(int $percentage = 100): void
    {
        $this->fuelPercent = $percentage;
    }
}