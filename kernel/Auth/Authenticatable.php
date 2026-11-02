<?php

namespace MacropaySolutions\Kernel\Auth;

use MacropaySolutions\Kernel\Support\Str;

/**
 * Trait Authenticatable
 *
 * Handles identity, password retrieval, and "Remember Me" token management.
 *
 * @warning AUTOMATIC TOKEN ROTATION & BULK UPDATE CAVEAT:
 * Password modifications on existing model instances automatically rotate the `remember_token`
 * via `setAttribute()` to invalidate existing client remember-me sessions.
 * Mass updates performed directly via the query builder (e.g., `User::query()->update([...])`
 * or `DB::table('users')->update([...])`) bypass Obvious model attribute mutators and setters.
 * When executing bulk or raw database password updates, you MUST explicitly pass a newly generated
 * `remember_token` in the update payload; otherwise, previously issued remember-me cookies will
 * remain valid against the new credentials.
 */
trait Authenticatable
{
    /**
     * The column name of the "remember me" token.
     *
     * @var string
     */
    protected $rememberTokenName = 'remember_token';

    protected string $passwordName = 'password';

    /**
     * Get the name of the unique identifier for the user.
     *
     * @return string
     */
    public function getAuthIdentifierName()
    {
        return $this->getKeyName();
    }

    /**
     * Get the unique identifier for the user.
     *
     * @return mixed
     */
    public function getAuthIdentifier()
    {
        return $this->getAttributeValue($this->getAuthIdentifierName());
    }

    /**
     * Get the unique broadcast identifier for the user.
     *
     * @return mixed
     */
    public function getAuthIdentifierForBroadcasting()
    {
        return $this->getAuthIdentifier();
    }

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->getAttributeValue($this->passwordName);
    }

    /**
     * Get the token value for the "remember me" session.
     *
     * @return string|null
     */
    public function getRememberToken()
    {
        if ('' !== (string)$this->rememberTokenName) {
            return (string)$this->getAttributeValue($this->rememberTokenName);
        }
    }

    /**
     * Set the token value for the "remember me" session.
     *
     * @param string $value
     * @return void
     */
    public function setRememberToken($value)
    {
        if ('' !== (string)$this->rememberTokenName) {
            $this->setAttribute($this->rememberTokenName, $value);
        }
    }

    /**
     * Get the column name for the "remember me" token.
     *
     * @return string
     */
    public function getRememberTokenName()
    {
        return $this->rememberTokenName;
    }

    /**
     * Set a given attribute on the model.
     *
     * Automatically rotates the remember token whenever the password is changed,
     * while protecting against stale form payloads overwriting the new token.
     * 
     * @warning Direct query builder updates (e.g., `User::query()->where(...)->update(['password' => ...])`)
     *  bypass this method entirely. Always supply an explicit `remember_token` in bulk update
     *  arrays to prevent session invalidation bypasses
     *
     */
    final public function setAttribute(string $key, mixed $value): static
    {
        if (!$this->exists) {
            return parent::setAttribute($key, $value);
        }

        if ($key === $this->rememberTokenName) {
            if (
                (string)($this->getRawOriginal($this->rememberTokenName) ?? '') === (string)($value ?? '')
                && $this->isDirty($this->rememberTokenName)
                && $this->isDirty($this->passwordName)
            ) {
                $value = $this->getAttributeFromArray($this->rememberTokenName);
            }
        }

        parent::setAttribute($key, $value);

        if (
            $key === $this->passwordName
            && !$this->originalIsEquivalent($this->passwordName)
            && !$this->isDirty($this->rememberTokenName)
        ) {
            $this->setRememberToken(Str::random(60));
        }

        return $this;
    }
}
