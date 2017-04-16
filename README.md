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

### Scalar injection

Pass an `array_merge($_ENV, $myConfiguration)` to the container constructor and use those values as constructor or setter parameters.

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
### Simple setter injection by interface

eg `$container->inject(EventDispatcherAware::class, 'setEventDispatcher')` will inject the event dispatcher on every class 
implementing the interface. All parameters to the method will be autowired for the call.

## Features considered but not implemented

- Reflection Caching: there appears to be no performance gain from caching ReflectionClass. eg https://github.com/brainfoolong/php-reflection-performance-tests

Pull requests welcome, but bear in mind the above project goals. If you have more complex needs, the other (better written, better supported, more mature) projects mentioned above will be a better choice for you.

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
