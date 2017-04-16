<?php
declare(strict_types=1);

namespace LSS\YAContainer;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionFunctionAbstract;
use function interface_exists;
use function is_scalar;

class Container implements \Psr\Container\ContainerInterface
{
    /**
     * All classes are shared by default
     * @var array class name => class instance
     */
    private $shared = [];

    /**
     * eg MyFooInterface::class => MyFooImplementation::class
     * @var array alias name => real name
     */
    private $alias = [];

    /**
     * scalar / builtin parameter values
     * @var array name => value
     */
    private $scalar = [];

    /**
     * @var array a stack of class name => depth for error reporting and to prevent circular dependencies
     */
    private $building = [];

    /**
     * Container constructor.
     * @param array $scalar see addScalar()
     * @param array $alias  see addAlias()
     */
    public function __construct(array $scalar = [], array $alias = [])
    {
        $this->alias  = $alias;
        $this->scalar = $scalar;
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $name Identifier of the entry to look for.
     *
     * @throws NotFoundExceptionInterface  No entry was found for **this** identifier.
     * @throws ContainerExceptionInterface Error while retrieving the entry.
     *
     * @return mixed Entry.
     */
    public function get($name)
    {
        if (isset($this->alias[$name])) {
            // resolve aliases
            $name = $this->alias[$name];
        }
        if (isset($this->shared[$name])) {
            return $this->shared[$name];
        }

        // circular dependency check
        if (isset($this->building[$name])) {
            throw new ContainerException($this->building, 'Circular dependency while building ' . $name);
        }
        $this->building[$name] = count($this->building);

        $result = $this->makeClass($name);

        $this->shared[$name] = $result;
        unset($this->building[$name]);
        return $result;
    }

    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     *
     * `has($id)` returning true does not mean that `get($id)` will not throw an exception.
     * It does however mean that `get($id)` will not throw a `NotFoundExceptionInterface`.
     *
     * @param string $name Identifier of the entry to look for.
     *
     * @return bool
     */
    public function has($name)
    {
        return class_exists($name) || interface_exists($name) || isset($this->alias[$name]) || isset($this->scalar[$name]);
    }

    /**
     * Alias must resolve to a real instantiable class in one step. An alias cannot refer to another alias.
     * Aliases only work for class names: they are not used for scalars. If you want to alias a scalar, add it again
     * with the aliased name
     * @param string $alias
     * @param string $realName
     * @return self
     */
    public function addAlias(string $alias, string $realName): self
    {
        assert(!isset($this->alias[$realName]));
        $this->alias[$alias] = $realName;
        return $this;
    }

    /**
     * Scalars values are used when the constructor / function parameter has no class type hint and is a scalar value
     * @param string                $name
     * @param int|string|float|bool $value
     * @return \LSS\YAContainer\Container
     */
    public function addScalar(string $name, $value): self
    {
        assert(is_scalar($value));
        $this->scalar[$name] = $value;
        return $this;
    }

    /**
     * @param string $name
     * @return mixed the built class
     * @throws \LSS\YAContainer\ContainerException
     */
    private function makeClass(string $name)
    {
        try {
            $classInfo = new ReflectionClass($name);
            if (!$classInfo->isInstantiable()) {
                // is it abstract, an interface, has a private constructor etc
                throw new ContainerException($this->building, 'Class is not instantiable ' . $name);
            }

            $constructorInfo = $classInfo->getConstructor();
            if (empty($constructorInfo)) {
                // no constructor specified
                return new $name();
            }

            $arguments = $this->collectArguments($constructorInfo);
            // It is faster to call it directly than to use reflection http://stackoverflow.com/a/24648651/117647
            return new $name(...$arguments);

        } catch (ReflectionException $exception) {
            throw new ContainerException($this->building, 'Reflection Exception: Can not build ' . $name, 0,
                $exception);
        }
    }

    /**
     * resolve all the arguments for the method and return them
     * @param \ReflectionFunctionAbstract $functionInfo
     * @return array
     * @throws \LSS\YAContainer\ContainerException
     */
    private function collectArguments(ReflectionFunctionAbstract $functionInfo): array
    {
        $result = [];
        foreach ($functionInfo->getParameters() as $parameterInfo) {
            // resolve the argument to a value
            if ($parameterInfo->isOptional()) {
                // accept default values for all arguments because all subsequent arguments must also have a default value
                break;
            }
            $typeInfo = $parameterInfo->getType();
            if (empty($typeInfo) || $typeInfo->isBuiltin()) {
                // handle scalar
                $parameterName = $parameterInfo->getName();
                if (!isset($this->scalar[$parameterName])) {
                    throw new ContainerException($this->building, 'Scalar value not found: ' . $parameterName);
                }
                $result[] = $this->scalar[$parameterName];
                continue;
            }
            $typeHint = $parameterInfo->getClass();
            $result[] = $this->get($typeHint->getName());
        }
        return $result;
    }
}