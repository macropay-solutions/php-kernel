<?php

namespace MacropaySolutions\Kernel\Queue\Failed;

use MacropaySolutions\Kernel\Support\Str;

trait ResolvesFailedJobUuid
{
    /**
     * The payload's uuid, or a new one when the payload has none (raw payloads, invalid JSON),
     * so the failed job is still recorded.
     */
    protected function resolveFailedJobUuid(string $payload): string
    {
        $decoded = \json_decode($payload, true);
        $uuid = \is_array($decoded) ? ($decoded['uuid'] ?? null) : null;

        if (\is_string($uuid) && '' !== $uuid) {
            return $uuid;
        }

        return (string)Str::uuid();
    }
}
