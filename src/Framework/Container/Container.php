<?php

namespace Framework\Container;

use Closure;
use TypeError;
use ArrayAccess;
use LogicException;
use ReflectionClass;
use ReflectionFunction;
use ReflectionException;
use ReflectionParameter;
use Framework\Contracts\Container\BindingResolutionException;
use Framework\Contracts\Container\Container as ContainerContract;

class Container implements ArrayAccess, ContainerContract
{
    /**
     * The current globally available container (if any).
     *
     * @var static
     */
    protected static $instance;

    /**
     * An array of the types that have been resolved.
     *
     * @var bool[]
     */
    protected $resolved = [];

    /**
     * The container's bindings.
     *
     * @var array[]
     */
    protected $bindings = [];

    /**
     * The container's method bindings.
     *
     * @var \Closure[]
     */
    protected $methodBindings = [];

    /**
     * The container's shared instances.
     *
     * @var object[]
     */
    protected $instances = [];

    /**
     * The registered type aliases.
     *
     * @var string[]
     */
    protected $aliases = [];

    /**
     * The registered aliases keyed by the abstract name.
     *
     * @var array[]
     */
    protected $abstractAliases = [];

    /**
     * The stack of concretions currently being built.
     *
     * @var array[]
     */
    protected $buildStack = [];

    /**
     * The parameter override stack.
     *
     * @var array[]
     */
    protected $with = [];

    /**
     * Determine if the given abstract type has been bound.
     *
     * @param  string  $abstract
     * @return bool
     */
    public function bound($abstract)
    {
        return isset($this->bindings[$abstract]) ||
            isset($this->instances[$abstract]) ||
            $this->isAlias($abstract);
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function has(string $id): bool
    {
        return $this->bound($id);
    }

    /**
     * Determine if the given abstract type has been resolved.
     *
     * @param  string  $abstract
     * @return bool
     */
    public function resolved($abstract)
    {
        if ($this->isAlias($abstract)) {
            $abstract = $this->getAlias($abstract);
        }

        return isset($this->resolved[$abstract]) ||
            isset($this->instances[$abstract]);
    }

    /**
     * Determine if a given type is shared.
     *
     * @param  string  $abstract
     * @return bool
     */
    public function isShared($abstract)
    {
        return isset($this->instances[$abstract]) ||
            (isset($this->bindings[$abstract]['shared']) &&
                $this->bindings[$abstract]['shared'] === true);
    }

    /**
     * Determine if a given string is an alias.
     *
     * @param  string  $name
     * @return bool
     */
    public function isAlias($name)
    {
        return isset($this->aliases[$name]);
    }

    /**
     * Register a binding with the container.
     *
     * @param  string  $abstract
     * @param  \Closure|string|null  $concrete
     * @param  bool  $shared
     * @return void
     *
     * @throws \TypeError
     */
    public function bind($abstract, $concrete = null, $shared = false)
    {
        $this->dropStaleInstances($abstract);

        if (is_null($concrete)) {
            $concrete = $abstract;
        }

        if (!$concrete instanceof Closure) {
            if (!is_string($concrete)) {
                throw new TypeError(self::class . '::bind(): Argument #2 ($concrete) must be of type Closure|string|null');
            }

            $concrete = $this->getClosure($abstract, $concrete);
        }

        $this->bindings[$abstract] = compact('concrete', 'shared');

        if ($this->resolved($abstract)) {
            $this->rebound($abstract);
        }
    }

    /**
     * Get the Closure to be used when building a type.
     *
     * @param  string  $abstract
     * @param  string  $concrete
     * @return \Closure
     */
    protected function getClosure($abstract, $concrete)
    {
        return function ($container, $parameters = []) use ($abstract, $concrete) {
            if ($abstract == $concrete) {
                return $container->build($concrete);
            }

            return $container->resolve(
                $concrete,
                $parameters,
                $raiseEvents = false
            );
        };
    }

    /**
     * Determine if the container has a method binding.
     *
     * @param  string  $method
     * @return bool
     */
    public function hasMethodBinding($method)
    {
        return isset($this->methodBindings[$method]);
    }

    /**
     * Bind a callback to resolve with Container::call.
     *
     * @param  array|string  $method
     * @param  \Closure  $callback
     * @return void
     */
    public function bindMethod($method, $callback)
    {
        $this->methodBindings[$this->parseBindMethod($method)] = $callback;
    }

    /**
     * Get the method to be bound in class@method format.
     *
     * @param  array|string  $method
     * @return string
     */
    protected function parseBindMethod($method)
    {
        if (is_array($method)) {
            return $method[0].'@'.$method[1];
        }

        return $method;
    }

    /**
     * Get the method binding for the given method.
     *
     * @param  string  $method
     * @param  mixed  $instance
     * @return mixed
     */
    public function callMethodBinding($method, $instance)
    {
        return call_user_func($this->methodBindings[$method], $instance, $this);
    }

    /**
     * Register a shared binding in the container.
     *
     * @param  string  $abstract
     * @param  \Closure|string|null  $concrete
     * @return void
     */
    public function singleton($abstract, $concrete = null)
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Register an existing instance as shared in the container.
     *
     * @param  string  $abstract
     * @param  mixed  $instance
     * @return mixed
     */
    public function instance($abstract, $instance)
    {
        $this->removeAbstractAlias($abstract);

        $isBound = $this->bound($abstract);

        unset($this->aliases[$abstract]);

        $this->instances[$abstract] = $instance;

        if ($isBound) {
            $this->rebound($abstract);
        }

        return $instance;
    }

    /**
     * Remove an alias from the contextual binding alias cache.
     *
     * @param  string  $searched
     * @return void
     */
    protected function removeAbstractAlias($searched)
    {
        if (!isset($this->aliases[$searched])) {
            return;
        }

        foreach ($this->abstractAliases as $abstract => $aliases) {
            foreach ($aliases as $index => $alias) {
                if ($alias == $searched) {
                    unset($this->abstractAliases[$abstract][$index]);
                }
            }
        }
    }

    /**
     * Alias a type to a different name.
     *
     * @param  string  $abstract
     * @param  string  $alias
     * @return void
     *
     * @throws \LogicException
     */
    public function alias($abstract, $alias)
    {
        if ($alias === $abstract) {
            throw new LogicException("[{$abstract}] is aliased to itself.");
        }

        $this->aliases[$alias] = $abstract;

        $this->abstractAliases[$abstract][] = $alias;
    }

    /**
     * Fire the "rebound" callbacks for the given abstract type.
     *
     * @param  string  $abstract
     * @return void
     */
    protected function rebound($abstract)
    {
        $this->make($abstract);
    }

    /**
     * Call the given Closure / class@method and inject its dependencies.
     *
     * @param  callable|string  $callback
     * @param  array<string, mixed>  $parameters
     * @param  string|null  $defaultMethod
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function call($callback, array $parameters = [], $defaultMethod = null)
    {
        $pushedToBuildStack = false;

        if (($className = $this->getClassForCallable($callback)) && ! in_array(
            $className,
            $this->buildStack,
            true
        )) {
            $this->buildStack[] = $className;

            $pushedToBuildStack = true;
        }

        $result = BoundMethod::call($this, $callback, $parameters, $defaultMethod);

        if ($pushedToBuildStack) {
            array_pop($this->buildStack);
        }

        return $result;
    }

    /**
     * Get the class name for the given callback, if one can be determined.
     *
     * @param  callable|string  $callback
     * @return string|false
     */
    protected function getClassForCallable($callback)
    {
        if (PHP_VERSION_ID >= 80200) {
            if (is_callable($callback) &&
                ! ($reflector = new ReflectionFunction($callback(...)))->isAnonymous()) {
                return $reflector->getClosureScopeClass()->name ?? false;
            }

            return false;
        }

        if (! is_array($callback)) {
            return false;
        }

        return is_string($callback[0]) ? $callback[0] : get_class($callback[0]);
    }

    /**
     * Resolve the given type from the container.
     *
     * @param  string|callable  $abstract
     * @param  array  $parameters
     * @return mixed
     *
     * @throws \Framework\Contracts\Container\BindingResolutionException
     */
    public function make($abstract, array $parameters = [])
    {
        return $this->resolve($abstract, $parameters);
    }

    /**
     * {@inheritdoc}
     *
     * @return mixed
     */
    public function get(string $id)
    {
        try {
            return $this->resolve($id);
        } catch (BindingResolutionException $e) {
            if ($this->has($id)) {
                throw $e;
            }

            throw new EntryNotFoundException($id, is_int($e->getCode()) ? $e->getCode() : 0, $e);
        }
    }

    /**
     * Resolve the given type from the container.
     *
     * @param  string|callable  $abstract
     * @param  array  $parameters
     * @param  bool  $raiseEvents
     * @return mixed
     *
     * @throws \Framework\Contracts\Container\BindingResolutionException
     * @throws \Framework\Contracts\Container\CircularDependencyException
     */
    protected function resolve($abstract, $parameters = [], $raiseEvents = true)
    {
        $abstract = $this->getAlias($abstract);

        $needsContextualBuild = !empty($parameters);

        if (isset($this->instances[$abstract]) && !$needsContextualBuild) {
            return $this->instances[$abstract];
        }

        $this->with[] = $parameters;

        $concrete = $this->getConcrete($abstract);

        $object = $this->isBuildable($concrete, $abstract)
            ? $this->build($concrete)
            : $this->make($concrete);

        if ($this->isShared($abstract) && !$needsContextualBuild) {
            $this->instances[$abstract] = $object;
        }

        $this->resolved[$abstract] = true;

        array_pop($this->with);

        return $object;
    }

    /**
     * Get the concrete type for a given abstract.
     *
     * @param  string|callable  $abstract
     * @return mixed
     */
    protected function getConcrete($abstract)
    {
        // If we don't have a registered resolver or concrete for the type, we'll just
        // assume each type is a concrete name and will attempt to resolve it as is
        // since the container should be able to resolve concretes automatically.
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }

        return $abstract;
    }

    /**
     * Determine if the given concrete is buildable.
     *
     * @param  mixed  $concrete
     * @param  string  $abstract
     * @return bool
     */
    protected function isBuildable($concrete, $abstract)
    {
        return $concrete === $abstract || $concrete instanceof Closure;
    }

    /**
     * Instantiate a concrete instance of the given type.
     *
     * @param  \Closure|string  $concrete
     * @return mixed
     *
     * @throws \Framework\Contracts\Container\BindingResolutionException
     * @throws \Framework\Contracts\Container\CircularDependencyException
     */
    public function build($concrete)
    {
        if ($concrete instanceof Closure) {
            return $concrete($this, $this->getLastParameterOverride());
        }

        try {
            $reflector = new ReflectionClass($concrete);
        } catch (ReflectionException $e) {
            throw new BindingResolutionException("Target class [$concrete] does not exist.", 0, $e);
        }

        if (!$reflector->isInstantiable()) {
            return $this->notInstantiable($concrete);
        }

        $this->buildStack[] = $concrete;

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            array_pop($this->buildStack);

            return new $concrete;
        }

        $dependencies = $constructor->getParameters();

        try {
            $instances = $this->resolveDependencies($dependencies);
        } catch (BindingResolutionException $e) {
            array_pop($this->buildStack);

            throw $e;
        }

        array_pop($this->buildStack);

        return $reflector->newInstanceArgs($instances);
    }

    /**
     * Resolve all of the dependencies from the ReflectionParameters.
     *
     * @param  \ReflectionParameter[]  $dependencies
     * @return array
     *
     * @throws \Framework\Contracts\Container\BindingResolutionException
     */
    protected function resolveDependencies(array $dependencies)
    {
        $results = [];

        foreach ($dependencies as $dependency) {
            if ($this->hasParameterOverride($dependency)) {
                $results[] = $this->getParameterOverride($dependency);

                continue;
            }

            $result = is_null(Util::getParameterClassName($dependency))
                ? $this->resolvePrimitive($dependency)
                : $this->resolveClass($dependency);

            if ($dependency->isVariadic()) {
                $results = array_merge($results, $result);
            } else {
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * Determine if the given dependency has a parameter override.
     *
     * @param  \ReflectionParameter  $dependency
     * @return bool
     */
    protected function hasParameterOverride($dependency)
    {
        return array_key_exists(
            $dependency->name,
            $this->getLastParameterOverride()
        );
    }

    /**
     * Get a parameter override for a dependency.
     *
     * @param  \ReflectionParameter  $dependency
     * @return mixed
     */
    protected function getParameterOverride($dependency)
    {
        return $this->getLastParameterOverride()[$dependency->name];
    }

    /**
     * Get the last parameter override.
     *
     * @return array
     */
    protected function getLastParameterOverride()
    {
        return count($this->with) ? end($this->with) : [];
    }

    /**
     * Resolve a non-class hinted primitive dependency.
     *
     * @param  \ReflectionParameter  $parameter
     * @return mixed
     *
     * @throws \Framework\Contracts\Container\BindingResolutionException
     */
    protected function resolvePrimitive(ReflectionParameter $parameter)
    {
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->isVariadic()) {
            return [];
        }

        $this->unresolvablePrimitive($parameter);
    }

    /**
     * Resolve a class based dependency from the container.
     *
     * @param  \ReflectionParameter  $parameter
     * @return mixed
     *
     * @throws \Framework\Contracts\Container\BindingResolutionException
     */
    protected function resolveClass(ReflectionParameter $parameter)
    {
        try {
            return $parameter->isVariadic()
                ? $this->resolveVariadicClass($parameter)
                : $this->make(Util::getParameterClassName($parameter));
        } catch (BindingResolutionException $e) {
            if ($parameter->isDefaultValueAvailable()) {
                array_pop($this->with);

                return $parameter->getDefaultValue();
            }

            if ($parameter->isVariadic()) {
                array_pop($this->with);

                return [];
            }

            throw $e;
        }
    }

    /**
     * Resolve a class based variadic dependency from the container.
     *
     * @param  \ReflectionParameter  $parameter
     * @return mixed
     */
    protected function resolveVariadicClass(ReflectionParameter $parameter)
    {
        $className = Util::getParameterClassName($parameter);

        return $this->make($className);
    }

    /**
     * Throw an exception that the concrete is not instantiable.
     *
     * @param  string  $concrete
     * @return void
     *
     * @throws \Framework\Contracts\Container\BindingResolutionException
     */
    protected function notInstantiable($concrete)
    {
        if (!empty($this->buildStack)) {
            $previous = implode(', ', $this->buildStack);

            $message = "Target [$concrete] is not instantiable while building [$previous].";
        } else {
            $message = "Target [$concrete] is not instantiable.";
        }

        throw new BindingResolutionException($message);
    }

    /**
     * Throw an exception for an unresolvable primitive.
     *
     * @param  \ReflectionParameter  $parameter
     * @return void
     *
     * @throws \Framework\Contracts\Container\BindingResolutionException
     */
    protected function unresolvablePrimitive(ReflectionParameter $parameter)
    {
        $message = "Unresolvable dependency resolving [$parameter] in class {$parameter->getDeclaringClass()->getName()}";

        throw new BindingResolutionException($message);
    }

    /**
     * Get the alias for an abstract if available.
     *
     * @param  string  $abstract
     * @return string
     */
    public function getAlias($abstract)
    {
        return isset($this->aliases[$abstract])
            ? $this->getAlias($this->aliases[$abstract])
            : $abstract;
    }

    /**
     * Drop all of the stale instances and aliases.
     *
     * @param  string  $abstract
     * @return void
     */
    protected function dropStaleInstances($abstract)
    {
        unset($this->instances[$abstract], $this->aliases[$abstract]);
    }

    /**
     * Remove a resolved instance from the instance cache.
     *
     * @param  string  $abstract
     * @return void
     */
    public function forgetInstance($abstract)
    {
        unset($this->instances[$abstract]);
    }

    /**
     * Get the globally available instance of the container.
     *
     * @return static
     */
    public static function getInstance()
    {
        if (is_null(static::$instance)) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /**
     * Set the shared instance of the container.
     *
     * @param  \Framework\Contracts\Container\Container|null  $container
     * @return \Framework\Contracts\Container\Container|static
     */
    public static function setInstance(ContainerContract $container = null)
    {
        return static::$instance = $container;
    }

    /**
     * Determine if a given offset exists.
     *
     * @param  string  $key
     * @return bool
     */
    public function offsetExists($key): bool
    {
        return $this->bound($key);
    }

    /**
     * Get the value at a given offset.
     *
     * @param  string  $key
     * @return mixed
     */
    public function offsetGet($key): mixed
    {
        return $this->make($key);
    }

    /**
     * Set the value at a given offset.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($key, $value): void
    {
        $this->bind($key, $value instanceof Closure ? $value : fn () => $value);
    }

    /**
     * Unset the value at a given offset.
     *
     * @param  string  $key
     * @return void
     */
    public function offsetUnset($key): void
    {
        unset($this->bindings[$key], $this->instances[$key], $this->resolved[$key]);
    }

    /**
     * Dynamically access container services.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this[$key];
    }

    /**
     * Dynamically set container services.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this[$key] = $value;
    }
}
