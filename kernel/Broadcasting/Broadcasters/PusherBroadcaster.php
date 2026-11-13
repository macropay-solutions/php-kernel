<?php

namespace MacropaySolutions\Kernel\Broadcasting\Broadcasters;

use MacropaySolutions\Kernel\Broadcasting\BroadcastException;
use MacropaySolutions\Kernel\Http\Request;
use MacropaySolutions\Kernel\Support\Arr;
use MacropaySolutions\Kernel\Support\Collection;
use Pusher\ApiErrorException;
use Pusher\Pusher;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PusherBroadcaster extends Broadcaster
{
    use UsePusherChannelConventions;

    /**
     * The Pusher SDK instance.
     *
     * @var \Pusher\Pusher
     */
    protected $pusher;

    /**
     * Create a new broadcaster instance.
     *
     * @param \Pusher\Pusher $pusher
     * @return void
     */
    public function __construct(Pusher $pusher)
    {
        $this->pusher = $pusher;
    }

    /**
     * Resolve the authenticated user payload for an incoming connection request.
     *
     * See: https://pusher.com/docs/channels/library_auth_reference/auth-signatures/#user-authentication
     * See: https://pusher.com/docs/channels/server_api/authenticating-users/#response
     */
    public function resolveAuthenticatedUser(Request $request): ?array
    {
        $socketId = (string)$request->getFiltered('socket_id');

        if (
            1 !== \preg_match('/^\d+\.\d+$/', $socketId)
            || !$user = parent::resolveAuthenticatedUser($request)
        ) {
            return null;
        }

        if (method_exists($this->pusher, 'authenticateUser')) {
            return $this->pusher->authenticateUser($socketId, $user);
        }

        $settings = $this->pusher->getSettings();
        $encodedUser = json_encode($user);
        $decodedString = "{$socketId}::user::{$encodedUser}";

        $auth = $settings['auth_key'] . ':' . hash_hmac(
            'sha256',
            $decodedString,
            $settings['secret']
        );

        return [
            'auth' => $auth,
            'user_data' => $encodedUser,
        ];
    }

    /**
     * Authenticate the incoming request for a given channel.
     *
     * @param Request $request
     * @return mixed
     *
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     */
    public function auth($request)
    {
        $channelName = $this->normalizeChannelName($cn = (string)$request->getFiltered('channel_name'));

        if (
            '' === $cn ||
            1 !== \preg_match('/^\d+\.\d+$/', (string)$request->getFiltered('socket_id')) ||
            ($this->isGuardedChannel($cn) && !$this->retrieveUser($request, $channelName))
        ) {
            throw new AccessDeniedHttpException();
        }

        return parent::verifyUserCanAccessChannel(
            $request,
            $channelName
        );
    }

    /**
     * Return the valid authentication response.
     *
     * @param Request $request
     * @param mixed $result
     * @return mixed
     */
    public function validAuthenticationResponse($request, $result)
    {
        $cn = (string)$request->getFiltered('channel_name');
        $socketId = (string)$request->getFiltered('socket_id');

        if (str_starts_with($cn, 'private')) {
            return $this->decodePusherResponse(
                $request,
                method_exists($this->pusher, 'authorizeChannel')
                    ? $this->pusher->authorizeChannel($cn, $socketId)
                    : $this->pusher->socket_auth($cn, $socketId)
            );
        }

        $channelName = $this->normalizeChannelName($cn);

        $user = $this->retrieveUser($request, $channelName);

        $broadcastIdentifier = method_exists($user, 'getAuthIdentifierForBroadcasting')
            ? $user->getAuthIdentifierForBroadcasting()
            : $user->getAuthIdentifier();

        return $this->decodePusherResponse(
            $request,
            method_exists($this->pusher, 'authorizePresenceChannel')
                ? $this->pusher->authorizePresenceChannel(
                    $cn,
                    $socketId,
                    $broadcastIdentifier,
                    $result
                )
                : $this->pusher->presence_auth(
                    $cn,
                    $socketId,
                    $broadcastIdentifier,
                    $result
                )
        );
    }

    /**
     * Decode the given Pusher response.
     *
     * @param Request $request
     * @param mixed $response
     * @return array
     */
    protected function decodePusherResponse($request, $response)
    {
        $callback = (string)$request->getFiltered('callback');

        if ('' === $callback) {
            return json_decode($response, true);
        }

        return response()->json(json_decode($response, true))
            ->withCallback($callback);
    }

    /**
     * Broadcast the given event.
     *
     * @param array $channels
     * @param string $event
     * @param array $payload
     * @return void
     *
     * @throws \MacropaySolutions\Kernel\Broadcasting\BroadcastException
     */
    public function broadcast(array $channels, $event, array $payload = [])
    {
        $socket = Arr::pull($payload, 'socket');

        $parameters = $socket !== null ? ['socket_id' => $socket] : [];

        $channels = Collection::make($this->formatChannels($channels));

        try {
            $channels->chunk(100)->each(function ($channels) use ($event, $payload, $parameters) {
                $this->pusher->trigger($channels->toArray(), $event, $payload, $parameters);
            });
        } catch (ApiErrorException $e) {
            throw new BroadcastException(
                sprintf('Pusher error: %s.', $e->getMessage())
            );
        }
    }

    /**
     * Get the Pusher SDK instance.
     *
     * @return \Pusher\Pusher
     */
    public function getPusher()
    {
        return $this->pusher;
    }

    /**
     * Set the Pusher SDK instance.
     *
     * @param \Pusher\Pusher $pusher
     * @return void
     */
    public function setPusher($pusher)
    {
        $this->pusher = $pusher;
    }
}
