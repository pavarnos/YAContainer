<?php
/**
 * @file
 * @author Lightly Salted Software Ltd
 * @date   16/04/17
 */

namespace LSS\YAContainer\Fixture;

class PassengerTaxi
{
    const DEFAULT_SEATS = 4;

    /**
     * @var \LSS\YAContainer\Fixture\EngineInterface
     */
    public $engine;

    /** @var int */
    public $roadSpeedLimit;

    /** @var int */
    public $maximumPassengers;

    /** @var int */
    public $numberOfSeats;

    /** @var string */
    public $roadSpeedUnit;

    /**
     * ScalarArguments constructor.
     * @param \LSS\YAContainer\Fixture\EngineInterface $engine
     * @param int                                      $roadSpeedLimit    legally defined system parameter
     * @param string                                   $roadSpeedUnit     km/h or mph
     * @param int                                      $maximumPassengers legally defined system parameter
     * @param int                                      $numberOfSeats
     */
    public function __construct(EngineInterface $engine, $roadSpeedLimit, $roadSpeedUnit, int $maximumPassengers,
        $numberOfSeats = self::DEFAULT_SEATS)
    {
        $this->engine            = $engine;
        $this->roadSpeedLimit    = $roadSpeedLimit;
        $this->roadSpeedUnit     = $roadSpeedUnit;
        $this->maximumPassengers = $maximumPassengers;
        $this->numberOfSeats     = $numberOfSeats;
        $this->roadSpeedUnit     = $roadSpeedUnit;
    }
}