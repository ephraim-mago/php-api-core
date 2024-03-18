<?php

namespace Framework\Database;

interface ConnectionResolverInterface
{
    /**
     * Get a database connection instance.
     *
     * @param  string|null  $name
     * @return \Framework\Database\ConnectionInterface
     */
    public function connection($name = null);
}
