# YAContainer: Yet Another Container

A minimal Dependency Injection Container for PHP 8.1+

The goals of this project are  
  
- Storage for objects by class name only: call `get(MyCleverThing::class)`
- Magic: auto-loading / auto-configuration with a minimum of fuss.
- Minimalism: No compilation, minimal declaration / configuration in advance, as little code as possible, 
  only the barest necessary features.
- Performance: Strips out all but essential features. 
- Strictly typed: uses phpstan @template so that if you get(T::class) it knows you will receive an object of type T back 
- Suitable for larger projects or monolithic applications with a lot of classes but only a few used per request.
- 100% test coverage

This package (or earlier versions of it) have been used in production on many sites for over a decade now. No fuss. It just works.

Why another container when there are already so many that are very good? 

- [Symfony Dependency Injection](http://symfony.com/doc/current/components/dependency_injection.html) needs up front declaration in xml, yml or php. It is possible to compile and dump the
  built container into a PHP class. For large projects, generation, compilation and dump can take a few seconds and needs
  to be re-done after every small change.
- [PHP-DI](http://php-di.org/) is flexible and powerful with excellent support from the lead maintainer. The documentation is nice.
  [Benchmarks](https://www.sitepoint.com/php-dependency-injection-container-performance-benchmarks/) show it is slower than others (though the benchmarks are from 2014).
- [Auryn](https://github.com/rdlowrey/auryn) is clean and simple with clever reflection caching and magic autoloading. 
  It deliberately does not follow PSR-11. Informal googling suggests there is [little](https://github.com/brainfoolong/php-reflection-performance-tests) [benefit](http://stackoverflow.com/a/24648651/117647) from caching ReflectionClass.
- [Aura.DI](https://github.com/auraphp/Aura.Di) needs up front configuration of every class.
- [Pimple](http://pimple.sensiolabs.org/) is tiny, elegant, and works well for small projects. It needs everything declared up front.
  
Pull requests welcome, but bear in mind the above project goals. If you have more complex needs, the other
(better written, better supported, more mature) projects mentioned above will be a better choice for you.

# How to use

Create your container and pass in scalar values and aliases to the constructor eg

```php
$aliases = [
    MyFoo::class => MyCachedFoo::class,
    MyBarInterface::class => MyBarImplementation::class
];
$container = new Container($_ENV, $aliases);
```

This is the quickest way to set up your container with static configuration. 

Ask the container for any autoloaded class by name eg `$container->get(My\\Namespace\\MyClass::class);`. 
The container will automatically build dependencies in the constructor and any recursive dependencies in _their_ constructors. 
For most classes this should work with no further effort.
For other classes that need extra configuration, you can use aliases, scalar injection and factory methods.

## Aliases

Constructors should usually depend on interfaces rather than concrete classes. So how do you tell the container which concrete
class to use? Specify an alias as in the above example.

## Scalar injection / Parameters

Scalar values (int,string,float,bool) can be used as constructor or setter parameters if the name matches _exactly_ (case sensitive) and a default value is not provided.

```php
class DatabaseConnection extends \PDO {
    public function __construct(string $databaseDSN, string $databaseUser, string $databasePassword) 
    {
        $options = ['charset' => 'utf8',PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
        parent::__construct($databaseDSN, $databaseUser, $databasePassword, $options);
    }

    // ... other utility functions    
}

$container = new Container(['databaseDSN' => 'mysql:host=localhost;dbname=theDBName', 'databaseUser' => 'theUserName', 'databasePassword' => 'thePassword']);
$databaseConnection = $container->get(DatabaseConnection::class);
```

You can also use `$container->addScalar()` to add more later if needed.

If a default value is provided for a constructor parameter, it will be respected and the scalar value will not be injected. 
If you need to pass a different value, use a factory instead.

You can also use a callable for a scalar value. The callable will be called only once then replaced with its value for 
all subsequent uses.
```php
$container->addScalar('maximumPassengers', function (Configuration $config) {
    return $config->getMaximumPassengers();
});
```

## Factory methods / Callables

For classes that are complicated to build or where the class needs a lot of stuff that nothing else needs, use a factory.
 
 ```php
 $fuelPercent = 75;
 $container = new Container();
 $container->addFactory(Car::class, function (EngineInterface $engine) use ($fuelPercent): Car {
     $result = new Car($engine);
     $result->refuel($fuelPercent);
     return $result;
 });
```

## Setter injection 

Setter injection can be emulated via a factory method. Call your setters after 

## Shared instances

All generated objects are shared by default. This means that each call to `get()` for the same class name will
return the exact same class instance each time. If you need a different instance each time, provide a function that 
tells the container which instances to share.
```php
$container->setShouldShare(function (string $className): bool { return $className !== Car::class; });
```

will build a new `Car` class instance for each `get()` call.

To disable sharing (create a new instance for every object every time) 
```php
$container->setShouldShare(function (string $className): bool { return false; });
```

## Forget

If you need a shared instance most of the time, but for some special reasons occasionally need a fresh instance,
use `forget()` to forget the current one. The next call to `get()` will create a fresh instance.
```php
$car = $container->get(Car::class);
$container->forget(Car::class);
$aDifferentCar = $container->get(Car::class);
```

# PSR-11 Containers

We deliberately do not implement Psr\Container\ContainerInterface because
- PSR-11 is not strict enough. It is a generic dictionary designed to hold any mixed thing keyed by any string
- A number of packages use v1.0 or v2.0 and managing cross dependencies was getting tricky. One fewer dependency is helpful.
- Wrapping this container in a PSR-11 proxy is trivial: see below

```php
class Psr11ContainerException extends \InvalidArgumentException implements Psr\Container\ContainerExceptionInterface {}

class Psr11Container implements Psr\Container\ContainerInterface {
    public __construct(private LSS\Container $wrapped) {}
    
    public function get(string $id) {
        try {
            return $this->wrapped->get($id);
        } catch (\Throwable $ex) {
            throw new Psr11ContainerException('Cannot build ' . $id, 0, $ex);
        }
    }
    
    public function has(string $id): bool 
    {
        return $this->wrapped->has($id);
    }
}
```

# This is NOT a Service Locator
 
Avoid the temptation to pass the container as a dependency to your created classes.
The best way to use this is in your bootstrap code to build the parts of your application into a single unit.
[Auryn has a good example](https://github.com/rdlowrey/auryn#app-bootstrapping).
