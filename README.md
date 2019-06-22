# YAContainer: Yet Another Container

A minimal PSR11 Dependency Injection Container for PHP 7.1+
 
The goals of this project are  
  
- Magic: auto-loading / auto-configuration with a minimum of fuss.
- Minimalism: No compilation, minimal declaration / configuration in advance, as little code as possible, 
  only the barest necessary features.
- Performance: Strips out all but essential features. Assumes that you will get everything by 
  class name eg `get(FooImplementation::class)` so does not need to normalise object names. A decent IDE will ensure 
  that your class names all have correctly matching case. 
- Standards Compliance: So it is easy to wrap (eg to track object creation in for php-debugbar) and sub-class to add 
  extra features in other projects should they be needed.
- Suitable for larger projects or monolithic applications with a lot of classes but only a few used per request.   

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
$container = new Container($_ENV,$aliases);
```

This is the quickest way to set up your container with static configuration. 

Ask the container for any autoloaded class by name eg `$container->get(My\\Namespace\\MyClass::class);`. 
The container will automatically build dependencies in the constructor and any recursive dependencies in _their_ constructors. 
For most classes this should work with no further effort.
For other classes that need extra configuration, you can use aliases, scalar injection, setter injection and factory methods.

All generated objects are shared by default.

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

For classes that are complicated to build or where the class needs a lot of stuff that nothing else needs, use a factory method.
 
 ```php
 $fuelPercent = 75;
 $container = new Container();
 $container->addFactory(Car::class, function (EngineInterface $engine) use ($fuelPercent) {
     $result = new Car($engine);
     $result->refuel($fuelPercent);
     return $result;
 });
```

Any interfaces configured for setter injection will be called after the factory has run.

## Setter injection 

Setter injection can be emulated via a factory method.

## PhpStorm integration

[PhpStorm will load metadata from a `.phpstorm.meta.php` file](https://confluence.jetbrains.com/display/PhpStorm/PhpStorm+Advanced+Metadata)

```php
<?php
namespace PHPSTORM_META
{
    override(\Psr\Container\ContainerInterface::get(0), map([0 => '@']));
}
```

Anything returned by `->get('...')` is internally typehinted as an instance of the first argument. For example `->get('DateTime')` (or `->get(DateTime::class)`) will be recognized to return a `DateTime` object.

**Note:** you may need to restart your IDE.

## Ideas for later

- Maybe add a `forget($name)` method to delete a shared instance from the container
- or add a flag `sharedByDefault=true` or add a `sharable` array so the container knows which classes to keep and which to forget. 
- or add an array of regex `neverShare`

# This is NOT a Service Locator
 
Avoid the temptation to pass the container as a dependency to your created classes.
The best way to use this is in your bootstrap code to build the parts of your application into a single unit.
[Auryn has a good example](https://github.com/rdlowrey/auryn#app-bootstrapping).