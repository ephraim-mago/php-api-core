<?php

namespace Framework\Http;

use Symfony\Component\HttpFoundation\RedirectResponse as BaseRedirectResponse;

class RedirectResponse extends BaseRedirectResponse
{
    use ResponseTrait;
}
