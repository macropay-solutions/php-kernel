<?php

namespace MacropaySolutions\Framework\Exceptions;

use Exception;
use MacropaySolutions\Kernel\Auth\Access\AuthorizationException;
use MacropaySolutions\Kernel\Auth\AuthenticationException;
use MacropaySolutions\Kernel\Console\View\Components\BulletList;
use MacropaySolutions\Kernel\Console\View\Components\Error;
use MacropaySolutions\Kernel\Contracts\Debug\ExceptionHandler;
use MacropaySolutions\Kernel\Contracts\Support\Responsable;
use MacropaySolutions\Kernel\Database\MultipleRecordsFoundException;
use MacropaySolutions\Kernel\Database\Obvious\ModelNotFoundException;
use MacropaySolutions\Kernel\Database\RecordsNotFoundException;
use MacropaySolutions\Kernel\Http\Base\HttpException;
use MacropaySolutions\Kernel\Http\Base\HttpExceptionInterface;
use MacropaySolutions\Kernel\Http\Base\NotFoundHttpException;
use MacropaySolutions\Kernel\Http\Exceptions\HttpResponseException;
use MacropaySolutions\Kernel\Http\JsonResponse;
use MacropaySolutions\Kernel\Http\Response;
use MacropaySolutions\Kernel\Session\TokenMismatchException;
use MacropaySolutions\Kernel\Support\Arr;
use MacropaySolutions\Kernel\Validation\ValidationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Throwable;

class Handler implements ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [];

    /**
     * A list of the internal exception types that should not be reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $internalDontReport = [
        AuthenticationException::class,
        AuthorizationException::class,
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        MultipleRecordsFoundException::class,
        RecordsNotFoundException::class,
        SuspiciousOperationException::class,
        TokenMismatchException::class,
        ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * @param \Throwable $e
     * @return void
     *
     * @throws \Exception
     */
    public function report(Throwable $e)
    {
        if ($this->shouldntReport($e)) {
            return;
        }

        if (method_exists($e, 'report')) {
            if ($e->report() !== false) {
                return;
            }
        }

        try {
            $logger = app(LoggerInterface::class);
        } catch (Exception $ex) {
            throw $e; // throw the original exception
        }

        $logger->error($e->getMessage(), ['exception' => $e]);
    }

    /**
     * Determine if the exception should be reported.
     *
     * @param \Throwable $e
     * @return bool
     */
    public function shouldReport(Throwable $e)
    {
        return !$this->shouldntReport($e);
    }

    /**
     * Determine if the exception is in the "do not report" list.
     *
     * @param \Throwable $e
     * @return bool
     */
    protected function shouldntReport(Throwable $e)
    {
        foreach (\array_merge($this->dontReport, $this->internalDontReport) as $type) {
            if ($e instanceof $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param \MacropaySolutions\Kernel\Http\Request $request
     * @param \Throwable $e
     * @return \MacropaySolutions\Kernel\Http\Base\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $e)
    {
        if (method_exists($e, 'render')) {
            return $e->render($request);
        } elseif ($e instanceof Responsable) {
            return $e->toResponse($request);
        }

        $e = $this->prepareException($e);

        if ($e instanceof HttpResponseException) {
            return $e->getResponse();
        }

        if ($e instanceof ValidationException && $e->getResponse()) {
            return $e->getResponse();
        }

        return $request->expectsJson()
            ? $this->prepareJsonResponse($request, $e)
            : $this->prepareResponse($request, $e);
    }

    /**
     * Prepare exception for rendering.
     */
    protected function prepareException(Throwable $e): Throwable
    {
        return match (true) {
            $e instanceof ModelNotFoundException => new NotFoundHttpException($e->getMessage(), $e),
            $e instanceof AuthorizationException => new HttpException($e->status() ?? 403, $e->getMessage()),
            $e instanceof TokenMismatchException => new HttpException(419, $e->getMessage(), $e),
            $e instanceof SuspiciousOperationException => new NotFoundHttpException('Bad hostname provided.', $e),
            $e instanceof RecordsNotFoundException => new NotFoundHttpException('Not found.', $e),
            default => $e,
        };
    }

    /**
     * Prepare a JSON response for the given exception.
     *
     * @param \MacropaySolutions\Kernel\Http\Request $request
     * @param \Throwable $e
     * @return \MacropaySolutions\Kernel\Http\JsonResponse
     */
    protected function prepareJsonResponse($request, Throwable $e)
    {
//        return new JsonResponse(
        return \di(JsonResponse::class, [
            $this->convertExceptionToArray($e),
            $this->isHttpException($e) ? $e->getStatusCode() : 500,
            $this->isHttpException($e) ? $e->getHeaders() : [],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        ]);
    }

    /**
     * Convert the given exception to an array.
     *
     * @param \Throwable $e
     * @return array
     */
    protected function convertExceptionToArray(Throwable $e)
    {
        return config('app.debug', false) ? [
            'message' => $e->getMessage(),
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => collect($e->getTrace())->map(function ($trace) {
                return Arr::except($trace, ['args']);
            })->all(),
        ] : [
            'message' => $this->isHttpException($e) ? $e->getMessage() : 'Server Error',
        ];
    }

    /**
     * Prepare a response for the given exception.
     *
     * @param \MacropaySolutions\Kernel\Http\Request $request
     * @param \Throwable $e
     * @return \MacropaySolutions\Kernel\Http\Response
     */
    protected function prepareResponse($request, Throwable $e)
    {
//        $response = new Response(
        $response = \di(Response::class, [
            \config('app.debug', false) ? $this->renderExceptionWithSymfony($e, true) : '<html>' .
                '<body style="display: grid; place-content: center; height: 100vh; margin: 0; text-align: center;">' .
                '<h1>' . (
                    $status = $this->isHttpException($e) ?
                        $e->getStatusCode() :
                        \min($e->getCode() ? $e->getCode() : 500, 500)
                ) . ': ' . __(Response::$statusTexts[$status] ?? [
                    419 => 'Page Expired'
                ][$status] ?? 'Server Error') . '</h1></body></html>',
            $this->isHttpException($e) ? $e->getStatusCode() : 500,
            $this->isHttpException($e) ? $e->getHeaders() : [],
        ]);

        $response->exception = $e;

        return $response;
    }

    /**
     * Render an exception to a string using Symfony.
     *
     * @param \Throwable $e
     * @param bool $debug
     * @return string
     */
    protected function renderExceptionWithSymfony(Throwable $e, $debug)
    {
        $renderer = new HtmlErrorRenderer($debug);

        return $renderer->render($e)->getAsString();
    }

    /**
     * Render an exception to the console.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param \Throwable $e
     * @return void
     */
    public function renderForConsole($output, Throwable $e)
    {
        if ($e instanceof CommandNotFoundException) {
            $message = str($e->getMessage())->explode('.')->first();

            if (!empty($alternatives = $e->getAlternatives())) {
                $message .= '. Did you mean one of these?';

                with(new Error($output))->render($message);
                with(new BulletList($output))->render($e->getAlternatives());

                $output->writeln('');
            } else {
                with(new Error($output))->render($message);
            }

            return;
        }

        (new ConsoleApplication())->renderThrowable($e, $output);
    }

    /**
     * Determine if the given exception is an HTTP exception.
     *
     * @param \Throwable $e
     * @return bool
     */
    protected function isHttpException(Throwable $e)
    {
        return $e instanceof HttpExceptionInterface;
    }
}
