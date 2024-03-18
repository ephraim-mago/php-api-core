<?php

namespace Framework\Session;

use InvalidArgumentException;
use Framework\Contracts\Container\Container;

/**
 * @mixin \Framework\Session\Store
 */
class SessionManager
{
    /**
     * The container instance.
     *
     * @var \Framework\Contracts\Container\Container
     */
    protected $container;

    /**
     * The configuration repository instance.
     *
     * @var \Framework\Contracts\Config\Repository
     */
    protected $config;

    /**
     * The array of created "drivers".
     *
     * @var array
     */
    protected $drivers = [];

    /**
     * Create a new manager instance.
     *
     * @param  \Framework\Contracts\Container\Container  $container
     * @return void
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->config = $container->make('config');
    }

    /**
     * Get a driver instance.
     *
     * @param  string|null  $driver
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function driver($driver = null)
    {
        $driver = $driver ?: $this->getDefaultDriver();

        if (is_null($driver)) {
            throw new InvalidArgumentException(sprintf(
                'Unable to resolve NULL driver for [%s].',
                static::class
            ));
        }

        // If the given driver has not been created before, we will create the instances
        // here and cache it so we can return it next time very quickly. If there is
        // already a driver created by this name, we'll just return that instance.
        if (!isset($this->drivers[$driver])) {
            $this->drivers[$driver] = $this->createDriver($driver);
        }

        return $this->drivers[$driver];
    }

    /**
     * Create a new driver instance.
     *
     * @param  string  $driver
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    protected function createDriver($driver)
    {
        $method = 'create' . ucfirst($driver) . 'Driver';

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        throw new InvalidArgumentException("Driver [$driver] not supported.");
    }

    /**
     * Create an instance of the file session driver.
     *
     * @return \Framework\Session\Store
     */
    protected function createFileDriver()
    {
        return $this->createNativeDriver();
    }

    /**
     * Create an instance of the file session driver.
     *
     * @return \Framework\Session\Store
     */
    protected function createNativeDriver()
    {
        $lifetime = $this->config->get('session')['lifetime'] ?? null;

        return $this->buildSession(new FileSessionHandler(
            $this->container->make('files'),
            $this->config->get('session')['files'],
            $lifetime
        ));
    }

    /**
     * Build the session instance.
     *
     * @param  \SessionHandlerInterface  $handler
     * @return \Framework\Session\Store
     */
    protected function buildSession($handler)
    {
        return $this->config->get('session')['encrypt'] ?? null
            ? $this->buildEncryptedSession($handler)
            : new Store(
                $this->config->get('session')['cookie'],
                $handler,
                $id = null,
                $this->config->get('session')['serialization'] ?? 'php'
            );
    }

    /**
     * Get the session configuration.
     *
     * @return array
     */
    public function getSessionConfig()
    {
        return $this->config->get('session');
    }

    /**
     * Get the default session driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->getSessionConfig()['driver'];
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->driver()->$method(...$parameters);
    }
}
