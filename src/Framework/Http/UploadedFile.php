<?php

namespace Framework\Http;

use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;

class UploadedFile extends SymfonyUploadedFile
{
    /**
     * Create a new file instance from a base instance.
     *
     * @param  \Symfony\Component\HttpFoundation\File\UploadedFile  $file
     * @param  bool  $test
     * @return static
     */
    public static function createFromBase(SymfonyUploadedFile $file, $test = false)
    {
        return $file instanceof static ? $file : new static(
            $file->getPathname(),
            $file->getClientOriginalName(),
            $file->getClientMimeType(),
            $file->getError(),
            $test
        );
    }
}
