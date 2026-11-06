<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Http;

use MacropaySolutions\Kernel\Auth\GenericUser;
use MacropaySolutions\Kernel\Http\Request;

class HttpTestAuth
{
    /**
     * Resolves the user from a bearer token: "token-<id>".
     */
    public static function userFromToken(Request $request): ?GenericUser
    {
        $token = (string)$request->bearerToken();

        if (!\str_starts_with($token, 'token-')) {
            return null;
        }

        return new GenericUser(['id' => (int)\substr($token, 6)]);
    }

    public static function canEditPost(GenericUser $user, int $postOwnerId): bool
    {
        return $user->getAuthIdentifier() === $postOwnerId;
    }
}
