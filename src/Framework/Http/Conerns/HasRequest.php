<?php

namespace Framework\Http\Conerns;

use Framework\Http\UploadedFile;
use Symfony\Component\HttpFoundation\InputBag;

trait HasRequest
{
    /**
     * Determine if the request is sending JSON.
     *
     * @return bool
     */
    public function isJson()
    {
        return str_contains(
            $this->header('CONTENT_TYPE') ?? '',
            '/json'
        ) || str_contains(
            $this->header('CONTENT_TYPE') ?? '',
            '+json'
        );
    }

    /**
     * Determine if the current request probably expects a JSON response.
     *
     * @return bool
     */
    public function expectsJson()
    {
        return ($this->ajax() && !$this->pjax() && $this->acceptsAnyContentType()) || $this->wantsJson();
    }

    /**
     * Determine if the current request is asking for JSON.
     *
     * @return bool
     */
    public function wantsJson()
    {
        $acceptable = $this->getAcceptableContentTypes();

        return isset($acceptable[0]) && (
            str_contains($acceptable[0], '/json') || str_contains($acceptable[0], '+json')
        );
    }

    /**
     * Determine if the current request accepts any content type.
     *
     * @return bool
     */
    public function acceptsAnyContentType()
    {
        $acceptable = $this->getAcceptableContentTypes();

        return count($acceptable) === 0 || (
            isset($acceptable[0]) && ($acceptable[0] === '*/*' || $acceptable[0] === '*')
        );
    }

    /**
     * Determine if the request is attempting to be precognitive.
     *
     * @return bool
     */
    public function isAttemptingPrecognition()
    {
        return $this->header('Precognition') === 'true';
    }

    /**
     * Determine if the request is precognitive.
     *
     * @return bool
     */
    public function isPrecognitive()
    {
        return $this->attributes->get('precognitive', false);
    }

    /**
     * Get all of the input and files for the request.
     *
     * @param  array|mixed|null  $keys
     * @return array
     */
    public function all($keys = null)
    {
        $input = array_replace_recursive($this->input(), $this->allFiles());

        if (!$keys) {
            return $input;
        }

        $results = [];

        foreach (is_array($keys) ? $keys : func_get_args() as $key) {
            if (isset($input[$key])) {
                $results[$key] = $input[$key];
            }
        }

        return $results;
    }

    /**
     * Retrieve an input item from the request.
     *
     * @param  string|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function input($key = null, $default = null)
    {
        $input = array_merge($this->getInputSource()->all(), $this->query->all());

        if (is_null($key)) {
            return $input;
        }

        return isset($input[$key]) ? $input[$key] : $default;
    }

    /**
     * Get an array of all of the files on the request.
     *
     * @return array
     */
    public function allFiles()
    {
        $files = $this->files->all();

        return $this->convertedFiles = $this->convertedFiles ?? $this->convertUploadedFiles($files);
    }

    /**
     * Convert the given array of Symfony UploadedFiles to custom Laravel UploadedFiles.
     *
     * @param  array  $files
     * @return array
     */
    protected function convertUploadedFiles(array $files)
    {
        return array_map(function ($file) {
            if (is_null($file) || (is_array($file) && empty(array_filter($file)))) {
                return $file;
            }

            return is_array($file)
                ? $this->convertUploadedFiles($file)
                : UploadedFile::createFromBase($file);
        }, $files);
    }

    /**
     * Retrieve a header from the request.
     *
     * @param  string|null  $key
     * @param  string|array|null  $default
     * @return string|array|null
     */
    public function header($key = null, $default = null)
    {
        return $this->retrieveItem('headers', $key, $default);
    }

    /**
     * Get the bearer token from the request headers.
     *
     * @return string|null
     */
    public function bearerToken()
    {
        $header = $this->header('Authorization', '');

        $position = strrpos($header, 'Bearer ');

        if ($position !== false) {
            $header = substr($header, $position + 7);

            return str_contains($header, ',') ? strstr($header, ',', true) : $header;
        }
    }

    /**
     * Retrieve a parameter item from a given source.
     *
     * @param  string  $source
     * @param  string|null  $key
     * @param  string|array|null  $default
     * @return string|array|null
     */
    protected function retrieveItem($source, $key, $default)
    {
        if (is_null($key)) {
            return $this->$source->all();
        }

        if ($this->$source instanceof InputBag) {
            return $this->$source->all()[$key] ?? $default;
        }

        return $this->$source->get($key, $default);
    }
}
