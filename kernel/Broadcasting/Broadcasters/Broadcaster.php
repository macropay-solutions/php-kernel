<?php

namespace MacropaySolutions\Kernel\Broadcasting\Broadcasters;

use MacropaySolutions\Kernel\Container\Container;
use MacropaySolutions\Kernel\Contracts\Broadcasting\Broadcaster as BroadcasterContract;
use MacropaySolutions\Kernel\Contracts\Broadcasting\HasBroadcastChannel;
use MacropaySolutions\Kernel\Http\Request;
use MacropaySolutions\Kernel\Support\Arr;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

abstract class Broadcaster implements BroadcasterContract
{
    /**
     * The callback to resolve the authenticated user information.
     *
     * @var \Closure|null
     */
    protected $authenticatedUserCallback = null;

    /**
     * The registered channel authenticators.
     *
     * @var array
     */
    protected $channels = [];

    /**
     * The registered channel options.
     *
     * @var array
     */
    protected $channelOptions = [];

    /**
     * Resolve the authenticated user payload for the incoming connection request.
     *
     * See: https://pusher.com/docs/channels/library_auth_reference/auth-signatures/#user-authentication.
     */
    public function resolveAuthenticatedUser(Request $request): ?array
    {
        return $this->authenticatedUserCallback?->__invoke($request);
    }

    /**
     * Register the user retrieval callback used to authenticate connections.
     *
     * See: https://pusher.com/docs/channels/library_auth_reference/auth-signatures/#user-authentication.
     */
    public function resolveAuthenticatedUserUsing(\Closure $callback): void
    {
        $this->authenticatedUserCallback = $callback;
    }

    /**
     * Register a channel authenticator.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Broadcasting\HasBroadcastChannel|string $channel
     * @param callable|string $callback
     * @param array $options
     * @return $this
     */
    public function channel($channel, $callback, $options = [])
    {
        if ($channel instanceof HasBroadcastChannel) {
            $channel = $channel->broadcastChannelRoute();
        } elseif (is_string($channel) && class_exists($channel) && is_a($channel, HasBroadcastChannel::class, true)) {
            $channel = (new $channel())->broadcastChannelRoute();
        }

        $this->channels[$channel] = $callback;

        $this->channelOptions[$channel] = $options;

        return $this;
    }

    /**
     * Authenticate the incoming request for a given channel.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     */
    protected function verifyUserCanAccessChannel(Request $request, string $channel): mixed
    {
        foreach ($this->channels as $pattern => $callback) {
            if (!$this->channelNameMatchesPattern($channel, $pattern)) {
                continue;
            }

            $parameters = $this->extractAuthParameters($pattern, $channel);

            $handler = $this->normalizeChannelHandlerToCallable($callback);

            $result = $handler($this->retrieveUser($request, $channel), ...$parameters);

            if ($result === false) {
                throw new AccessDeniedHttpException();
            } elseif ($result) {
                return $this->validAuthenticationResponse($request, $result);
            }
        }

        throw new AccessDeniedHttpException();
    }

    /**
     * Extract the parameters from the given pattern and channel.
     */
    protected function extractAuthParameters(string $pattern, string $channel): array
    {
        return collect($this->extractChannelKeys($pattern, $channel))->reject(function ($value, $key) {
            return is_numeric($key);
        })->values()->all();
    }

    /**
     * Extract the channel keys from the incoming channel name.
     *
     * @param string $pattern
     * @param string $channel
     * @return array
     */
    protected function extractChannelKeys($pattern, $channel)
    {
        preg_match('/^' . preg_replace('/\{(.*?)\}/', '(?<$1>[^\.]+)', $pattern) . '/', $channel, $keys);

        return $keys;
    }

    /**
     * Format the channel array into an array of strings.
     *
     * @param array $channels
     * @return array
     */
    protected function formatChannels(array $channels)
    {
        return array_map(function ($channel) {
            return (string)$channel;
        }, $channels);
    }

    /**
     * Normalize the given callback into a callable.
     *
     * @param mixed $callback
     * @return callable
     */
    protected function normalizeChannelHandlerToCallable($callback)
    {
        return is_callable($callback) ? $callback : function (...$args) use ($callback) {
            return Container::getInstance()
                ->make($callback)
                ->join(...$args);
        };
    }

    /**
     * Retrieve the authenticated user using the configured guard (if any).
     *
     * @param \MacropaySolutions\Kernel\Http\Request $request
     * @param string $channel
     * @return mixed
     */
    protected function retrieveUser($request, $channel)
    {
        $options = $this->retrieveChannelOptions($channel);

        $guards = $options['guards'] ?? null;

        if (is_null($guards)) {
            return $request->user();
        }

        foreach (Arr::wrap($guards) as $guard) {
            if ($user = $request->user($guard)) {
                return $user;
            }
        }
    }

    /**
     * Retrieve options for a certain channel.
     *
     * @param string $channel
     * @return array
     */
    protected function retrieveChannelOptions($channel)
    {
        foreach ($this->channelOptions as $pattern => $options) {
            if (!$this->channelNameMatchesPattern($channel, $pattern)) {
                continue;
            }

            return $options;
        }

        return [];
    }

    /**
     * Check if the channel name from the request matches a pattern from registered channels.
     *
     * @param string $channel
     * @param string $pattern
     * @return bool
     */
    protected function channelNameMatchesPattern($channel, $pattern)
    {
        return preg_match('/^' . preg_replace('/\{(.*?)\}/', '([^\.]+)', $pattern) . '$/', $channel);
    }

    /**
     * Get all the registered channels.
     *
     * @return \MacropaySolutions\Kernel\Support\Collection
     */
    public function getChannels()
    {
        return collect($this->channels);
    }
}
