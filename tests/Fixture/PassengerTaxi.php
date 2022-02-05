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

    public function __construct(
        public readonly EngineInterface $engine,
        public readonly int $roadSpeedLimit,
        public readonly string $roadSpeedUnit,
        public readonly int $maximumPassengers,
        public readonly int $numberOfSeats = self::DEFAULT_SEATS
    ) {
    }
}
