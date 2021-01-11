<?php
/**
 * @file
 * @author Lightly Salted Software Ltd
 * @date   16/04/17
 */

namespace LSS\YAContainer;

use LSS\YAContainer\Fixture\Car;
use LSS\YAContainer\Fixture\CircularDependency;
use LSS\YAContainer\Fixture\CircularDependencyA;
use LSS\YAContainer\Fixture\CircularDependencyB;
use LSS\YAContainer\Fixture\CircularDependencyC;
use LSS\YAContainer\Fixture\CircularDependencyInterface;
use LSS\YAContainer\Fixture\ElectricEngine;
use LSS\YAContainer\Fixture\EngineInterface;
use LSS\YAContainer\Fixture\MissingArgument;
use LSS\YAContainer\Fixture\MissingScalarArgument;
use LSS\YAContainer\Fixture\PassengerTaxi;
use LSS\YAContainer\Fixture\PrivateConstructor;
use LSS\YAContainer\Fixture\V8Engine;
use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
    public function testGetNoConstructor(): void
    {
        $subject = new Container();
        self::assertTrue($subject->get(V8Engine::class) instanceof V8Engine);
    }

    public function testGetWithMissingArgument(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('|Can not build .*MissingArgument|');
        $subject = new Container();
        $subject->get(MissingArgument::class);
    }

    public function testGetNonInstantiable(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('not instantiable');
        $subject = new Container();
        $subject->get(EngineInterface::class);
    }

    public function testGetPrivateConstructor(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('not instantiable');
        $subject = new Container();
        $subject->get(PrivateConstructor::class);
    }

    public function testGetClassParameterAlias(): void
    {
        $subject = new Container();
        $subject->addAlias(EngineInterface::class, V8Engine::class);
        self::assertTrue($subject->get(Car::class) instanceof Car);
    }

    public function testGetShared(): void
    {
        $subject = new Container();
        $subject->addAlias(EngineInterface::class, V8Engine::class);
        $car = $subject->get(Car::class);
        self::assertTrue($car instanceof Car);
        self::assertSame($car, $subject->get(Car::class));
        self::assertSame($car, $subject->get(Car::class));
    }

    public function testGetNotShared(): void
    {
        $subject = new Container();
        // tell the container to build a new Car each time get() is called
        $subject->setShouldShare(
            function (string $name) {
                return $name !== Car::class;
            }
        );
        $subject->addAlias(EngineInterface::class, V8Engine::class);
        $car = $subject->get(Car::class);
        self::assertTrue($car instanceof Car);
        self::assertNotSame($car, $subject->get(Car::class));
        self::assertNotSame($car, $subject->get(Car::class));
    }

    /**
     * @dataProvider circularDependency
     * @param string $className
     * @param string $message
     */
    public function testGetWithCircularDependency(string $className, string $message): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage($message);
        $subject = new Container();
        $subject->addAlias(CircularDependencyInterface::class, CircularDependencyB::class);
        $subject->get($className);
    }

    public function circularDependency(): array
    {
        return [
            'One Level'     => [CircularDependency::class, 'Circular'],
            'Multi Level A' => [CircularDependencyA::class, 'Circular'],
            'Multi Level B' => [CircularDependencyB::class, 'Circular'],
            'Multi Level C' => [CircularDependencyC::class, 'Circular'],
            'Alias'         => [CircularDependencyInterface::class, 'Circular'],
        ];
    }

    public function testHas(): void
    {
        $subject = new Container();
        // can (potentially) make any class that exists
        self::assertTrue($subject->has(Car::class));
        self::assertTrue($subject->has(EngineInterface::class));
        self::assertTrue($subject->has(MissingArgument::class));

        // but not random strings or non existent classes
        self::assertFalse($subject->has('Foo Bar'));
        self::assertFalse($subject->has('NonExistentClass'));
    }

    public function testSet(): void
    {
        $subject = new Container();
        $engine  = new ElectricEngine();
        $name    = ElectricEngine::class;
        $subject->set($name, $engine);
        self::assertTrue($engine === $subject->get($name), 'should be same instance');
        self::assertTrue($subject->has($name));
    }

    public function testGetWithScalarArguments(): void
    {
        $subject = new Container(
            ['roadSpeedLimit' => $roadSpeedLimit = 100],
            [EngineInterface::class => ElectricEngine::class]
        );
        // can add scalars in the constructor or afterwards with addScalar()
        $subject->addScalar('roadSpeedUnit', $roadSpeedUnit = 'km/h');

        // scalar can be a callable and can take all usual parameters including other scalars
        $callCount         = 0;
        $maximumPassengers = 4;
        $subject->addScalar(
            'maximumPassengers',
            function ($roadSpeedLimit) use (&$callCount, $maximumPassengers): int {
                self::assertEquals(100, $roadSpeedLimit);
                $callCount++;
                return $maximumPassengers;
            }
        );

        $taxi = $subject->get(PassengerTaxi::class);
        self::assertEquals(1, $callCount);
        self::assertEquals($roadSpeedLimit, $taxi->roadSpeedLimit);
        self::assertEquals($roadSpeedUnit, $taxi->roadSpeedUnit);
        self::assertEquals($maximumPassengers, $taxi->maximumPassengers);
        self::assertEquals(PassengerTaxi::DEFAULT_SEATS, $taxi->numberOfSeats);
        self::assertTrue($taxi->engine instanceof ElectricEngine);
    }

    public function testGetWithUnknownScalar(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Scalar value not found');
        $subject = new Container();
        $subject->get(MissingScalarArgument::class);
    }

    public function testGetFromFactory(): void
    {
        $fuelPercent = 75;
        $subject     = new Container();
        $subject->addFactory(
            Car::class,
            function (EngineInterface $engine) use ($fuelPercent): Car {
                $result = new Car($engine);
                $result->refuel($fuelPercent);
                return $result;
            }
        );
        // factory method can resolve aliases
        $subject->addAlias(EngineInterface::class, ElectricEngine::class);

        $car = $subject->get(Car::class);
        self::assertEquals($fuelPercent, $car->fuelPercent);
    }
}
