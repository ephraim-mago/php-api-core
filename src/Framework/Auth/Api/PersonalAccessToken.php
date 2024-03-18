<?php

namespace Framework\Auth\Api;

use Framework\Contracts\Support\Jsonable;
use Framework\Contracts\Support\Arrayable;
use Framework\Support\Facades\File;

class PersonalAccessToken implements Arrayable, Jsonable
{
    protected $name;

    protected $token;

    protected $expiresAt;

    protected $tokensPath;

    /**
     * Save the personal access.
     *
     * @param array $attributes
     * @return $this
     */
    public function save(array $attributes)
    {
        if (isset($attributes['name']) && isset($attributes['token'])) {
            foreach ($attributes as $key => $value) {
                $this->{$key} = $value;
            }

            File::append($this->generate(), $this->toJson());
        } else {
            throw new \Exception("The personal access token required the attributes name and token.");
        }

        return $this;
    }

    /**
     * Determine if the current API token has a given scope and validate.
     *
     * @param  string  $token
     * @return bool
     */
    public function can(string $token)
    {
        if (array_key_exists($token, $tokens = $this->tokens())) {
            $tokenFile = File::json($tokens[$token]);

            // $this->setAccessToken($tokenFile);

            return hash_equals($tokenFile["accessToken"], $token) ? $token : false;
        }

        return false;
    }

    /**
     * Get the access tokens.
     *
     * @return array
     */
    public function tokens()
    {
        $files = File::allFiles($this->getPath());
        $tokens = [];

        foreach ($files as $file) {
            $tokens[$file->getFilename()] = $file->getRealPath();
        }

        return $tokens;
    }

    /**
     * Get the name in the personal access.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get the token in the personal access.
     *
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * Get the token folder path.
     *
     * @return string
     */
    public function getPath()
    {
        if (is_null($this->tokensPath)) {
            $this->tokensPath = storage_path("/core/tokens");
        }
        return $this->tokensPath;
    }

    /**
     * Set the access token in the instance.
     *
     * @param  array $attributes
     * @return self
     */
    public function loadAccessToken()
    {
        $token = request()->bearerToken();
        $tokens = $this->tokens();
        $tokenFile = File::json($tokens[$token]);

        [$id, $token] = explode('|', $tokenFile['plainTextToken'], 2);

        $this->name = $id;
        $this->token = $token;

        return $this;
    }

    /**
     * Set the access token in the instance.
     *
     * @param  array $attributes
     * @return void
     */
    public function deleteAccessToken()
    {
        $tokenFile = $this->getPath() . '/' . $this->getToken();

        if (File::exists($tokenFile)) {
            File::delete($tokenFile);
            $this->name = "";
            $this->token = "";
        }
    }

    /**
     * Generate new filename in the personal token.
     *
     * @return string
     */
    public function generate()
    {
        return $this->getPath() . '/' . $this->token;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'accessToken' => $token = $this->token,
            'plainTextToken' => $this->name . '|' . $token,
        ];
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }
}
