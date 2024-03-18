<?php

namespace Framework\Auth\Api;

use DateTimeInterface;

trait HasApiTokens
{
    /**
     * The access token the user is using for the current request.
     *
     * @var \Framework\Auth\Api\PersonalAccessToken
     */
    protected static $accessToken;

    /**
     * Get the access tokens instance.
     *
     * @return \Framework\Auth\Api\PersonalAccessToken
     */
    public function accessToken()
    {
        return new PersonalAccessToken();
    }

    /**
     * Determine if the current API token has a given scope.
     *
     * @param  string  $token
     * @return bool
     */
    public function tokenCan(string $token)
    {
        return $this->accessToken()->can($token);
    }

    /**
     * Create a new personal access token for the user.
     *
     * @param  string  $name
     * @param  array  $abilities
     * @param  \DateTimeInterface|null  $expiresAt
     * @return \Framework\Auth\Api\PersonalAccessToken
     */
    public function createToken(string $name, DateTimeInterface $expiresAt = null)
    {
        $plainTextToken = $this->generateTokenString();

        return $this->accessToken()->save([
            'name' => $name,
            'token' => hash('sha256', $plainTextToken),
            'expiresAt' => $expiresAt,
        ]);
    }

    /**
     * Generate the token string.
     *
     * @return string
     */
    public function generateTokenString()
    {
        $length = 40;
        $string = '';

        while (($len = strlen($string)) < $length) {

            $size = $length - $len;

            $bytesSize = (int) ceil($size / 3) * 3;

            $bytes = random_bytes($bytesSize);

            $string .= substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
        }

        return hash('crc32b', $string);
    }

    /**
     * Get the access token currently associated with the user.
     *
     * @return \Framework\Auth\Api\PersonalAccessToken
     */
    public function currentAccessToken()
    {
        return $this->accessToken()->loadAccessToken();
    }
}
