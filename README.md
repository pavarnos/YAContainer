# YAContainer: Yet Another Container

A PSR11 Dependency Injection Container for PHP 7.1+
 
Why another container when there are already so many? 

I looked at quite a few and learned a lot from them

- I used Symfony DI in a medium sized project for years, but as refactoring progresses the number of classes in 
  the container will quadruple. Compilation is already taking 2-3 seconds which is too long in an edit-test cycle.
- PHP-DI is flexible and powerful but bulky and slow (benchmarks).
- Auryn is clean and simple with clever caching and magic autoloading but deliberately does not follow PSR-11.
- Aura.DI needs a lot of up front configuration.
  
The goals of this project are  
  
- Magic: auto-loading / auto-configuration with a minimum of fuss.
- Minimalism: No compilation, minimal declaration in advance, as little code as possible, only the barest necessary features (subclass to add more)
- Performance: Strips out all but essential features. Assumes that you will get everything by 
  class name eg `get(FooImplementation::class)` so does not need to normalise object names. A decent IDE will ensure 
  that your class names all have correctly matching case. 
- Standards Compliance: So it is easy to wrap (eg to track object creation in for php-debugbar) and sub-class to add 
  extra features in other projects should they be needed,  

## Features not available elsewhere 
(that i could see)

- Scalar injection (see below)
- Setter injection by interface (see below)

## Features considered but not implemented

- Reflection Caching: there appears to be no performance gain from caching ReflectionClass. eg https://github.com/brainfoolong/php-reflection-performance-tests

Pull requests welcome, but bear in mind the above project goals. If you have more complex needs, the other (better written, better supported, more mature) projects mentioned above will be a better choice for you.

## How to use

Create your container and pass in the scalar values and aliases to the constructor eg

```php
$aliases = [
    MyFoo::class => MyCachedFoo::class,
    MyBarInterface::class => MyBarImplementation::class
];
$container = new Container($_ENV,$aliases);
```

This is the quickest way to set up your container with static configuration.

All generated objects are shared by default.

### Scalar injection

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

If a default value is provided for a parameter, it will be respected. If you need to pass a different value, use a factory instead.

### Simple setter injection by interface

`$container->inject(EventDispatcherAware::class, 'setEventDispatcher')` will inject the event dispatcher on every class 
implementing the interface. All parameters to the method will be autowired for the call.

### Factory methods / Callables

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
