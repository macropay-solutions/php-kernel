<?php

namespace MacropaySolutions\Kernel\Contracts\Http;

interface Kernel
{
    /**
     * Bootstrap the application for HTTP requests.
     *
     * @return void
     */
    public function bootstrap();

    /**
     * Handle an incoming HTTP request.
     *
     * @param \MacropaySolutions\Kernel\Http\Base\Request $request
     * @return \MacropaySolutions\Kernel\Http\Base\Response
     */
    public function handle($request);

    /**
     * Perform any final actions for the request lifecycle.
     *
     * @param \MacropaySolutions\Kernel\Http\Base\Request $request
     * @param \MacropaySolutions\Kernel\Http\Base\Response $response
     * @return void
     */
    public function terminate($request, $response);

    /**
     * Get the Kernel application instance.
     *
     * @return \MacropaySolutions\Kernel\Contracts\Foundation\Application
     */
    public function getApplication();
}
