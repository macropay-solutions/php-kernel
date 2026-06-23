<?php

namespace Illuminate\Broadcasting;

use Illuminate\Http\Request;
use MacropaySolutions\Framework\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class BroadcastController extends Controller
{
    /**
     * Authenticate the request for channel access.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function authenticate(Request $request)
    {
        if ($request->hasSession()) {
            $request->session()->reflash();
        }

        return \app(\Illuminate\Contracts\Broadcasting\Factory::class)->auth($request);
    }

    /**
     * Authenticate the current user.
     *
     * See: https://pusher.com/docs/channels/server_api/authenticating-users/#user-authentication.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function authenticateUser(Request $request)
    {
        if ($request->hasSession()) {
            $request->session()->reflash();
        }

        return \app(\Illuminate\Contracts\Broadcasting\Factory::class)->resolveAuthenticatedUser($request)
            ?? throw new AccessDeniedHttpException();
    }
}
