<?php

use Framework\Support\HigherOrderTapProxy;

if (!function_exists('value')) {
    /**
     * Return the default value of the given value.
     *
     * @param  mixed  $value
     * @param  mixed  ...$args
     * @return mixed
     */
    function value($value, ...$args)
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }
}

if (!function_exists('blank')) {
    /**
     * Determine if the given value is "blank".
     *
     * @param  mixed  $value
     * @return bool
     */
    function blank($value)
    {
        if (is_null($value)) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_numeric($value) || is_bool($value)) {
            return false;
        }

        if ($value instanceof Countable) {
            return count($value) === 0;
        }

        return empty($value);
    }
}

if (!function_exists('class_basename')) {
    /**
     * Get the class "basename" of the given object / class.
     *
     * @param  string|object  $class
     * @return string
     */
    function class_basename($class)
    {
        $class = is_object($class) ? get_class($class) : $class;

        return basename(str_replace('\\', '/', $class));
    }
}

if (!function_exists('class_uses_recursive')) {
    /**
     * Returns all traits used by a class, its parent classes and trait of their traits.
     *
     * @param  object|string  $class
     * @return array
     */
    function class_uses_recursive($class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        $results = [];

        foreach (array_reverse(class_parents($class) ?: []) + [$class => $class] as $class) {
            $results += trait_uses_recursive($class);
        }

        return array_unique($results);
    }
}

if (!function_exists('filled')) {
    /**
     * Determine if a value is "filled".
     *
     * @param  mixed  $value
     * @return bool
     */
    function filled($value)
    {
        return !blank($value);
    }
}

if (!function_exists('object_get')) {
    /**
     * Get an item from an object using "dot" notation.
     *
     * @param  object  $object
     * @param  string|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    function object_get($object, $key, $default = null)
    {
        if (is_null($key) || trim($key) === '') {
            return $object;
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_object($object) || !isset($object->{$segment})) {
                return value($default);
            }

            $object = $object->{$segment};
        }

        return $object;
    }
}

if (!function_exists('preg_replace_array')) {
    /**
     * Replace a given pattern with each value in the array in sequentially.
     *
     * @param  string  $pattern
     * @param  array  $replacements
     * @param  string  $subject
     * @return string
     */
    function preg_replace_array($pattern, array $replacements, $subject)
    {
        return preg_replace_callback($pattern, function () use (&$replacements) {
            foreach ($replacements as $value) {
                return array_shift($replacements);
            }
        }, $subject);
    }
}

if (!function_exists('tap')) {
    /**
     * Call the given Closure with the given value then return the value.
     *
     * @param  mixed  $value
     * @param  callable|null  $callback
     * @return mixed
     */
    function tap($value, $callback = null)
    {
        if (is_null($callback)) {
            return new HigherOrderTapProxy($value);
        }

        $callback($value);

        return $value;
    }
}

if (! function_exists('throw_if')) {
    /**
     * Throw the given exception if the given condition is true.
     *
     * @template TException of \Throwable
     *
     * @param  mixed  $condition
     * @param  TException|class-string<TException>|string  $exception
     * @param  mixed  ...$parameters
     * @return mixed
     *
     * @throws TException
     */
    function throw_if($condition, $exception = 'RuntimeException', ...$parameters)
    {
        if ($condition) {
            if (is_string($exception) && class_exists($exception)) {
                $exception = new $exception(...$parameters);
            }

            throw is_string($exception) ? new RuntimeException($exception) : $exception;
        }

        return $condition;
    }
}

if (! function_exists('throw_unless')) {
    /**
     * Throw the given exception unless the given condition is true.
     *
     * @template TException of \Throwable
     *
     * @param  mixed  $condition
     * @param  TException|class-string<TException>|string  $exception
     * @param  mixed  ...$parameters
     * @return mixed
     *
     * @throws TException
     */
    function throw_unless($condition, $exception = 'RuntimeException', ...$parameters)
    {
        throw_if(! $condition, $exception, ...$parameters);

        return $condition;
    }
}

if (! function_exists('trait_uses_recursive')) {
    /**
     * Returns all traits used by a trait and its traits.
     *
     * @param  object|string  $trait
     * @return array
     */
    function trait_uses_recursive($trait)
    {
        $traits = class_uses($trait) ?: [];

        foreach ($traits as $trait) {
            $traits += trait_uses_recursive($trait);
        }

        return $traits;
    }
}

if (! function_exists('transform')) {
    /**
     * Transform the given value if it is present.
     *
     * @template TValue of mixed
     * @template TReturn of mixed
     * @template TDefault of mixed
     *
     * @param  TValue  $value
     * @param  callable(TValue): TReturn  $callback
     * @param  TDefault|callable(TValue): TDefault|null  $default
     * @return ($value is empty ? ($default is null ? null : TDefault) : TReturn)
     */
    function transform($value, callable $callback, $default = null)
    {
        if (filled($value)) {
            return $callback($value);
        }

        if (is_callable($default)) {
            return $default($value);
        }

        return $default;
    }
}

if (! function_exists('windows_os')) {
    /**
     * Determine whether the current environment is Windows based.
     *
     * @return bool
     */
    function windows_os()
    {
        return PHP_OS_FAMILY === 'Windows';
    }
}

if (! function_exists('with')) {
    /**
     * Return the given value, optionally passed through the given callback.
     *
     * @template TValue
     * @template TReturn
     *
     * @param  TValue  $value
     * @param  (callable(TValue): (TReturn))|null  $callback
     * @return ($callback is null ? TValue : TReturn)
     */
    function with($value, callable $callback = null)
    {
        return is_null($callback) ? $value : $callback($value);
    }
}
