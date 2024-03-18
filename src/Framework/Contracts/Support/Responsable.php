<?php

namespace Framework\Contracts\Support;

interface Responsable
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Framework\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request);
}
