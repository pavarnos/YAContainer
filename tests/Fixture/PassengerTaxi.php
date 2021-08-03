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

    public EngineInterface $engine;

    public int $roadSpeedLimit;

    public int $maximumPassengers;

    public int $numberOfSeats;

    public string $roadSpeedUnit;

    /**
     * ScalarArguments constructor.
     * @param EngineInterface $engine
     * @param int             $roadSpeedLimit    legally defined system parameter
     * @param string          $roadSpeedUnit     km/h or mph
     * @param int             $maximumPassengers legally defined system parameter
     * @param int             $numberOfSeats
     */
    public function __construct(
        EngineInterface $engine,
        int $roadSpeedLimit,
        string $roadSpeedUnit,
        int $maximumPassengers,
        int $numberOfSeats = self::DEFAULT_SEATS
    ) {
        $this->engine            = $engine;
        $this->roadSpeedLimit    = $roadSpeedLimit;
        $this->roadSpeedUnit     = $roadSpeedUnit;
        $this->maximumPassengers = $maximumPassengers;
        $this->numberOfSeats     = $numberOfSeats;
        $this->roadSpeedUnit     = $roadSpeedUnit;
    }
}