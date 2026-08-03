<?php

namespace MacropaySolutions\Framework\Http;

use MacropaySolutions\Kernel\Http\Base\BinaryFileResponse;
use MacropaySolutions\Kernel\Http\Base\StreamedResponse;
use MacropaySolutions\Kernel\Http\JsonResponse;
use MacropaySolutions\Kernel\Http\Response;
use MacropaySolutions\Kernel\Support\Str;
use MacropaySolutions\Kernel\Support\Traits\Macroable;

class ResponseFactory
{
    use Macroable;

    /**
     * Return a new response from the application.
     *
     * @param string $content
     * @param int $status
     * @param array $headers
     * @return \MacropaySolutions\Kernel\Http\Response
     */
    public function make($content = '', $status = 200, array $headers = [])
    {
//        return new Response($content, $status, $headers);
        return \di(Response::class, [$content, $status, $headers]);
    }

    /**
     * Return a new JSON response from the application.
     *
     * @param mixed $data
     * @param int $status
     * @param array $headers
     * @param int $options
     * @return \MacropaySolutions\Kernel\Http\JsonResponse
     */
    public function json($data = [], $status = 200, array $headers = [], $options = 0)
    {
//        return new JsonResponse($data, $status, $headers, $options);
        return \di(JsonResponse::class, [$data, $status, $headers, $options]);
    }

    /**
     * Create a new JSONP response instance.
     *
     * @param string $callback
     * @param mixed $data
     * @param int $status
     * @param array $headers
     * @param int $options
     * @return \MacropaySolutions\Kernel\Http\JsonResponse
     */
    public function jsonp($callback, $data = [], $status = 200, array $headers = [], $options = 0)
    {
        return $this->json($data, $status, $headers, $options)->setCallback($callback);
    }

    /**
     * Create a new streamed response instance.
     *
     * @param \Closure $callback
     * @param int $status
     * @param array $headers
     * @return \MacropaySolutions\Kernel\Http\Base\StreamedResponse
     */
    public function stream($callback, $status = 200, array $headers = [])
    {
//        return new StreamedResponse($callback, $status, $headers);
        return \di(StreamedResponse::class, [$callback, $status, $headers]);
    }

    /**
     * Create a new streamed response instance as a file download.
     *
     * @param \Closure $callback
     * @param string|null $name
     * @param array $headers
     * @param string|null $disposition
     * @return \MacropaySolutions\Kernel\Http\Base\StreamedResponse
     */
    public function streamDownload($callback, $name = null, array $headers = [], $disposition = 'attachment')
    {
//        $response = new StreamedResponse($callback, 200, $headers);
        $response = \di(StreamedResponse::class, [$callback, 200, $headers]);

        if (!is_null($name)) {
            $response->headers->set(
                'Content-Disposition',
                $response->headers->makeDisposition(
                    $disposition,
                    $name,
                    $this->fallbackName($name)
                )
            );
        }

        return $response;
    }

    /**
     * Create a new file download response.
     *
     * @param \SplFileInfo|string $file
     * @param string $name
     * @param array $headers
     * @param null|string $disposition
     * @return \MacropaySolutions\Kernel\Http\Base\BinaryFileResponse
     */
    public function download($file, $name = null, array $headers = [], $disposition = 'attachment')
    {
//        $response = new BinaryFileResponse($file, 200, $headers, true, $disposition);
        $response = \di(BinaryFileResponse::class, [$file, 200, $headers, true, $disposition]);

        if (!is_null($name)) {
            return $response->setContentDisposition($disposition, $name, $this->fallbackName($name));
        }

        return $response;
    }

    /**
     * Convert the string to ASCII characters that are equivalent to the given name.
     *
     * @param string $name
     * @return string
     */
    protected function fallbackName($name)
    {
        return str_replace('%', '', Str::ascii($name));
    }

    /**
     * Return the raw contents of a binary file.
     *
     * @param \SplFileInfo|string $file
     * @param array $headers
     * @return \MacropaySolutions\Kernel\Http\Base\BinaryFileResponse
     */
    public function file($file, array $headers = [])
    {
//        return new BinaryFileResponse($file, 200, $headers);
        return \di(BinaryFileResponse::class, [$file, 200, $headers]);
    }
}
