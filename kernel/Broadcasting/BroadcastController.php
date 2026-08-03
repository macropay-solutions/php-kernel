<?php

namespace MacropaySolutions\Kernel\Broadcasting;

use MacropaySolutions\Framework\Routing\Controller;
use MacropaySolutions\Kernel\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class BroadcastController extends Controller
{
    /**
     * Authenticate the request for channel access.
     *
     * @param \MacropaySolutions\Kernel\Http\Request $request
     * @return \MacropaySolutions\Kernel\Http\Response
     */
    public function authenticate(Request $request)
    {
        if ($request->hasSession()) {
            $request->session()->reflash();
        }

        return \app(\MacropaySolutions\Kernel\Contracts\Broadcasting\Factory::class)->auth($request);
    }

    /**
     * Authenticate the current user.
     *
     * See: https://pusher.com/docs/channels/server_api/authenticating-users/#user-authentication.
     *
     * @param \MacropaySolutions\Kernel\Http\Request $request
     * @return \MacropaySolutions\Kernel\Http\Response
     */
    public function authenticateUser(Request $request)
    {
        if ($request->hasSession()) {
            $request->session()->reflash();
        }

        return \app(\MacropaySolutions\Kernel\Contracts\Broadcasting\Factory::class)->resolveAuthenticatedUser($request)
            ?? throw new AccessDeniedHttpException();
    }
}
