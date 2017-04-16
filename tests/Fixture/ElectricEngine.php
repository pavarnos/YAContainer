<?php
/**
 * @file
 * @author Lightly Salted Software Ltd
 * @date   16/04/17
 */

namespace LSS\YAContainer\Fixture;

class ElectricEngine implements EngineInterface
{
    public $fuelPercent = 0;

    public function refuel($percentage = 100)
    {
        $this->fuelPercent = $percentage;
    }
}