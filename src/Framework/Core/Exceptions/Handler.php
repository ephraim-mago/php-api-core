<?php

namespace Framework\Core\Exceptions;

use Framework\Http\RedirectResponse;
use Throwable;
use Framework\Http\Response;
use Framework\Http\JsonResponse;
use Framework\Auth\AuthenticationException;
use Framework\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Framework\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;

class Handler implements ExceptionHandlerContract
{
    /**
     * Report or log an exception.
     *
     * @param  \Throwable  $e
     * @return void
     *
     * @throws \Throwable
     */
    public function report(Throwable $e)
    {
        $file = storage_path('logs/app.log');

        $context = $this->buildExceptionContext($e);

        file_put_contents($file, date('Y-m-d H:i:s') . ' - ' . $e->getMessage() . ' : ' . json_encode($context) . "\n", FILE_APPEND);
    }

    /**
     * Create the context array for logging the given exception.
     *
     * @param  \Throwable  $e
     * @return array
     */
    protected function buildExceptionContext(Throwable $e)
    {
        return array_merge(
            $this->exceptionContext($e),
            $this->context(),
            ['exception' => $e->getTrace()]
        );
    }

    /**
     * Get the default exception context variables for logging.
     *
     * @param  \Throwable  $e
     * @return array
     */
    protected function exceptionContext(Throwable $e)
    {
        if (method_exists($e, 'context')) {
            return $e->context();
        }

        return [];
    }

    /**
     * Get the default context variables for logging.
     *
     * @return array
     */
    protected function context()
    {
        try {
            return array_filter([
                'userId' => rand(),
                // 'userId' => Auth::id(),
            ]);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Framework\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $e)
    {
        return match (true) {
            $e instanceof HttpResponseException => $e->getResponse(),
            $e instanceof AuthenticationException => $this->unauthenticated($request, $e),
            // $e instanceof ValidationException => $this->convertValidationExceptionToResponse($e, $request),
            default => $this->renderExceptionResponse($request, $e),
        };
    }

    /**
     * Render a default exception response if any.
     *
     * @param  \Framework\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Framework\Http\Response|\Framework\Http\JsonResponse
     * @return \Framework\Http\Response|\Framework\Http\JsonResponse|\Framework\Http\RedirectResponse
     */
    protected function renderExceptionResponse($request, Throwable $e)
    {
        return $this->shouldReturnJson($request, $e)
            ? $this->prepareJsonResponse($request, $e)
            : $this->prepareResponse($request, $e);
    }

    /**
     * Convert an authentication exception into a response.
     *
     * @param  \Framework\Http\Request  $request
     * @param  \Framework\Auth\AuthenticationException  $exception
     * @return \Framework\Http\Response|\Framework\Http\JsonResponse|\Framework\Http\RedirectResponse
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        return $this->shouldReturnJson($request, $exception)
                    ? response()->json(['message' => $exception->getMessage()], 401)
                    : new RedirectResponse('/login');
                    // : redirect()->guest($exception->redirectTo() ?? route('login'));
    }

    /**
     * Determine if the exception handler response should be JSON.
     *
     * @param  \Framework\Http\Request  $request
     * @param  \Throwable  $e
     * @return bool
     */
    protected function shouldReturnJson($request, Throwable $e)
    {
        return $request->expectsJson();
    }

    /**
     * Prepare a response for the given exception.
     *
     * @param  \Framework\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Framework\Http\Response|\Framework\Http\JsonResponse
     */
    protected function prepareResponse($request, Throwable $e)
    {
        return $this->toBaseResponse(
            $this->convertExceptionToResponse($e),
            $e
        )->prepare($request);
    }

    /**
     * Create a Symfony response for the given exception.
     *
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function convertExceptionToResponse(Throwable $e)
    {
        return new SymfonyResponse(
            sprintf(
                '%s in %s on line %s',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ),
            $this->isHttpException($e) ? $e->getStatusCode() : 500,
            $this->isHttpException($e) ? $e->getHeaders() : []
        );
    }

    /**
     * Map the given exception into an Application response.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @param  \Throwable  $e
     * @return \Framework\Http\Response
     */
    protected function toBaseResponse($response, Throwable $e)
    {
        $response = new Response(
            $response->getContent(),
            $response->getStatusCode(),
            $response->headers->all()
        );

        return $response->withException($e);
    }

    /**
     * Prepare a JSON response for the given exception.
     *
     * @param  \Framework\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Framework\Http\JsonResponse
     */
    protected function prepareJsonResponse($request, Throwable $e)
    {
        return new JsonResponse(
            $this->convertExceptionToArray($e),
            $this->isHttpException($e) ? $e->getStatusCode() : 500,
            $this->isHttpException($e) ? $e->getHeaders() : [],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Convert the given exception to an array.
     *
     * @param  \Throwable  $e
     * @return array
     */
    protected function convertExceptionToArray(Throwable $e)
    {
        return config('app')['debug'] ? [
            'message' => $e->getMessage(),
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTrace(),
        ] : [
            'message' => $this->isHttpException($e) ? $e->getMessage() : 'Server Error',
        ];
    }

    /**
     * Determine if the given exception is an HTTP exception.
     *
     * @param  \Throwable  $e
     * @return bool
     */
    protected function isHttpException(Throwable $e)
    {
        return $e instanceof HttpExceptionInterface;
    }
}
