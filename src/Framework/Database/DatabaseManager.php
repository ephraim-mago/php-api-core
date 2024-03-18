<?php

namespace Framework\Database;

use PDO;

class DatabaseManager implements ConnectionResolverInterface
{
    /**
     * The application instance.
     *
     * @var \Framework\Contracts\Core\Application
     */
    protected $app;

    /**
     * Create a new database manager instance.
     *
     * @param  \Framework\Contracts\Core\Application  $app
     * @return void
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Get a database connection instance.
     *
     * @param  string|null  $name
     * @return \Framework\Database\Connection
     */
    public function connection($name = null)
    {
        $name = $name ?? "sqlite";

        return $this->configure(
            $this->makeConnection($name)
        );
    }

    /**
     * Make the database connection instance.
     *
     * @param  string  $name
     * @return \PDO|callable
     */
    protected function makeConnection($name)
    {
        return match ($name) {
            'sqlite' => function () {
                $databaseFile = $this->app->databasePath('database.sqlite');

                return new PDO("sqlite:{$databaseFile}", null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
                    // PDO::MYSQL_ATTR_INIT_COMMAND => 'SET CHARACTER SET UTF8'
                ]);
            },
            default => null,
        };
    }

    /**
     * Prepare the database connection instance.
     *
     * @param  \PDO|callable  $connection
     * @return \Framework\Database\Connection
     */
    protected function configure($connection)
    {
        $pdo = is_callable($connection) ? $connection() : $connection;

        return new Connection($pdo);
    }
}
