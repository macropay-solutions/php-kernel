<?php

namespace MacropaySolutions\Kernel\Validation;

use Exception;
use MacropaySolutions\Kernel\Contracts\Validation\UncompromisedVerifier;
use MacropaySolutions\Kernel\Support\Collection;
use MacropaySolutions\Kernel\Support\Str;

class NotPwnedVerifier implements UncompromisedVerifier
{
    /**
     * The HTTP factory instance.
     */
    protected \GuzzleHttp\Client $factory;

    /**
     * The number of seconds the request can run before timing out.
     */
    protected int $timeout;

    /**
     * Create a new uncompromised verifier.
     */
    public function __construct(\GuzzleHttp\Client $factory, ?int $timeout = null)
    {
        $this->factory = $factory;
        $this->timeout = $timeout ?? 30;
    }

    /**
     * Verify that the given data has not been compromised in public breaches.
     */
    public function verify(array $data): bool
    {
        $value = $data['value'];
        $threshold = $data['threshold'];

        if (empty($value = (string)$value)) {
            return false;
        }

        [$hash, $hashPrefix] = $this->getHash($value);

        return !$this->search($hashPrefix)
            ->contains(function ($line) use ($hash, $hashPrefix, $threshold) {
                [$hashSuffix, $count] = explode(':', $line);

                return $hashPrefix . $hashSuffix == $hash && $count > $threshold;
            });
    }

    /**
     * Get the hash and its first 5 chars.
     *
     * @param string $value
     * @return array
     */
    protected function getHash($value)
    {
        $hash = strtoupper(sha1((string)$value));

        $hashPrefix = substr($hash, 0, 5);

        return [$hash, $hashPrefix];
    }

    /**
     * Search by the given hash prefix and returns all occurrences of leaked passwords.
     */
    protected function search(string $hashPrefix): Collection
    {
        try {
            $response = $this->factory->get('https://api.pwnedpasswords.com/range/' . $hashPrefix, [
                'headers' => [
                    'Add-Padding' => true,
                ],
                'timeout' => $this->timeout,
            ]);
        } catch (Exception $e) {
            report($e);
        }

        $body = '';

        if (isset($response) && \str_starts_with((string)$response->getStatusCode(), '2')) {
            $body = $response->getBody()->getContents();
        }

        return Str::of($body)->trim()->explode("\n")->filter(function ($line) {
            return str_contains($line, ':');
        });
    }
}
