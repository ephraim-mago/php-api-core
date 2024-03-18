<?php

namespace Framework\Contracts\Core;
use Framework\Contracts\Container\Container;

interface Application extends Container
{
    /**
     * Get the base path of the Laravel installation.
     *
     * @param  string  $path
     * @return string
     */
    public function basePath($path = '');

    /**
     * Get the path to the bootstrap directory.
     *
     * @param  string  $path
     * @return string
     */
    public function bootstrapPath($path = '');

    /**
     * Get the path to the application configuration files.
     *
     * @param  string  $path
     * @return string
     */
    public function configPath($path = '');

    /**
     * Get the path to the database directory.
     *
     * @param  string  $path
     * @return string
     */
    public function databasePath($path = '');

    /**
     * Get the path to the public directory.
     *
     * @param  string  $path
     * @return string
     */
    public function publicPath($path = '');

    /**
     * Get the path to the storage directory.
     *
     * @param  string  $path
     * @return string
     */
    public function storagePath($path = '');

    /**
     * Register all of the configured providers.
     *
     * @return void
     */
    public function registerConfiguredProviders();

    /**
     * Register a service provider with the application.
     *
     * @param  \Framework\Support\ServiceProvider|string  $provider
     * @param  bool  $force
     * @return \Framework\Support\ServiceProvider
     */
    public function register($provider, $force = false);

    /**
     * Run the given array of bootstrap classes.
     *
     * @param  array  $bootstrappers
     * @return void
     */
    public function bootstrapWith(array $bootstrappers);

    /**
     * Get the registered service provider instances if any exist.
     *
     * @param  \Framework\Support\ServiceProvider|string  $provider
     * @return array
     */
    public function getProviders($provider);

    /**
     * Determine if the application has been bootstrapped before.
     *
     * @return bool
     */
    public function hasBeenBootstrapped();

    /**
     * Determine if middleware has been disabled for the application.
     *
     * @return bool
     */
    public function shouldSkipMiddleware();
}
