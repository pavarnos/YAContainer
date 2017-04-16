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
use LSS\YAContainer\Fixture\IgnitionListener;
use LSS\YAContainer\Fixture\MissingArgument;
use LSS\YAContainer\Fixture\MissingScalarArgument;
use LSS\YAContainer\Fixture\PassengerTaxi;
use LSS\YAContainer\Fixture\PrivateConstructor;
use LSS\YAContainer\Fixture\V8Engine;
use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
    public function testGetNoConstructor()
    {
        $subject = new Container();
        $this->assertTrue($subject->get(V8Engine::class) instanceof V8Engine);
    }

    public function testGetWithMissingArgument()
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageRegExp('|Can not build .*MissingArgument|');
        $subject = new Container();
        $subject->get(MissingArgument::class);
    }

    public function testGetNonInstantiable()
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('not instantiable');
        $subject = new Container();
        $subject->get(EngineInterface::class);
    }

    public function testGetPrivateConstructor()
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('not instantiable');
        $subject = new Container();
        $subject->get(PrivateConstructor::class);
    }

    public function testGetClassParameterAlias()
    {
        $subject = new Container();
        $subject->addAlias(EngineInterface::class, V8Engine::class);
        $this->assertTrue($subject->get(Car::class) instanceof Car);
    }

    public function testGetShared()
    {
        $subject = new Container();
        $subject->addAlias(EngineInterface::class, V8Engine::class);
        $car = $subject->get(Car::class);
        $this->assertTrue($car instanceof Car);
        $this->assertTrue($car === $subject->get(Car::class),
            'should return exact same instance if it was built already');
    }

    /**
     * @dataProvider circularDependency
     * @param string $className
     * @param string $message
     */
    public function testGetWithCircularDependency($className, $message)
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage($message);
        $subject = new Container();
        $subject->addAlias(CircularDependencyInterface::class, CircularDependencyB::class);
        $subject->get($className);
    }

    public function circularDependency()
    {
        return [
            'One Level'     => [CircularDependency::class, 'Circular'],
            'Multi Level A' => [CircularDependencyA::class, 'Circular'],
            'Multi Level B' => [CircularDependencyB::class, 'Circular'],
            'Multi Level C' => [CircularDependencyC::class, 'Circular'],
            'Alias'         => [CircularDependencyInterface::class, 'Circular'],
        ];
    }

    public function testHas()
    {
        $subject = new Container();
        // can (potentially) make any class that exists
        $this->assertTrue($subject->has(Car::class));
        $this->assertTrue($subject->has(EngineInterface::class));
        $this->assertTrue($subject->has(MissingArgument::class));

        // but not random strings or non existent classes
        $this->assertFalse($subject->has('Foo Bar'));
        $this->assertFalse($subject->has('NonExistentClass'));
    }

    public function testGetWithScalarArguments()
    {
        $subject = new Container(
            ['roadSpeedLimit'    => $roadSpeedLimit = 100,
             'maximumPassengers' => $maximumPassengers = 4],
            [EngineInterface::class => ElectricEngine::class]
        );
        // can add scalars in the constructor or afterwards with addScalar()
        $subject->addScalar('roadSpeedUnit', $roadSpeedUnit = 'km/h');

        $taxi = $subject->get(PassengerTaxi::class);
        $this->assertEquals($roadSpeedLimit, $taxi->roadSpeedLimit);
        $this->assertEquals($roadSpeedUnit, $taxi->roadSpeedUnit);
        $this->assertEquals($maximumPassengers, $taxi->maximumPassengers);
        $this->assertEquals(PassengerTaxi::DEFAULT_SEATS, $taxi->numberOfSeats);
        $this->assertTrue($taxi->engine instanceof ElectricEngine);
    }

    public function testGetWithUnknownScalar()
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Scalar value not found');
        $subject = new Container();
        $subject->get(MissingScalarArgument::class);
    }

    public function testGetFromFactory()
    {
        $fuelPercent = 75;
        $subject     = new Container();
        $subject->addFactory(Car::class, function (EngineInterface $engine) use ($fuelPercent) {
            $result = new Car($engine);
            $result->refuel($fuelPercent);
            return $result;
        });
        // factory method can resolve aliases
        $subject->addAlias(EngineInterface::class, ElectricEngine::class);
        // factory method can inject on interfaces
        $subject->inject(EngineInterface::class, 'setIgnitionListener');

        $car      = $subject->get(Car::class);
        $listener = $subject->get(IgnitionListener::class);
        $this->assertTrue($listener === $car->getEngine()->getIgnitionListener());
        $this->assertEquals($fuelPercent, $car->fuelPercent);
    }

    public function testGetInjectInterface()
    {
        $subject = new Container();
        $subject->addAlias(EngineInterface::class, ElectricEngine::class);
        $subject->inject(EngineInterface::class, 'setIgnitionListener');
        $car      = $subject->get(Car::class);
        $listener = $subject->get(IgnitionListener::class);
        $this->assertTrue($listener === $car->getEngine()->getIgnitionListener());
    }
}
