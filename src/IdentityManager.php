<?php

namespace Sprocketbox\Eloquence;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquence
 *
 * @package Sprocketbox\Eloquence
 */
class IdentityManager
{
    /**
     * The current instance.
     *
     * @var \Sprocketbox\Eloquence\IdentityManager
     */
    protected static IdentityManager $instance;

    /**
     * Get the current Eloquence instance.
     *
     * @return \Sprocketbox\Eloquence\IdentityManager
     */
    public static function getInstance(): IdentityManager
    {
        if (! isset(self::$instance)) {
            self::$instance = new static;
        }

        return self::$instance;
    }

    /**
     * The identity map for models, mapped as identity => model
     *
     * @var array<string, Model>
     */
    protected array $models = [];

    /**
     * Check if the identity is already stored.
     *
     * @param \Sprocketbox\Eloquence\ModelIdentity $identity
     *
     * @return bool
     */
    public function hasIdentity(ModelIdentity $identity): bool
    {
        return isset($this->models[$this->getStringIdentity($identity)]);
    }

    /**
     * Get the stored model for the given identity.
     *
     * @param \Sprocketbox\Eloquence\ModelIdentity $identity
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function getIdentity(ModelIdentity $identity): ?Model
    {
        return $this->models[$this->getStringIdentity($identity)] ?? null;
    }

    /**
     * Set the model for the current identity.
     *
     * @param \Sprocketbox\Eloquence\ModelIdentity $identity
     * @param \Illuminate\Database\Eloquent\Model  $model
     *
     * @return $this
     */
    public function storeIdentity(ModelIdentity $identity, Model $model): IdentityManager
    {
        $this->models[$this->getStringIdentity($identity)] = $model;

        return $this;
    }

    /**
     * Remove a model from the identity map.
     *
     * @param \Sprocketbox\Eloquence\ModelIdentity $identity
     *
     * @return $this
     */
    public function removeIdentity(ModelIdentity $identity): IdentityManager
    {
        if ($this->hasIdentity($identity)) {
            unset($this->models[$this->getStringIdentity($identity)]);
        }

        return $this;
    }

    /**
     * Remove all models from the identity map.
     *
     * @return $this
     */
    public function flushIdentities(): IdentityManager
    {
        $this->models = [];

        return $this;
    }

    /**
     * Get all stored identities.
     *
     * @return array
     */
    public function allIdentities(): array
    {
        return $this->models;
    }

    /**
     * Turn the model identity into a string.
     *
     * @param \Sprocketbox\Eloquence\ModelIdentity $identity
     *
     * @return string
     */
    protected function getStringIdentity(ModelIdentity $identity): string
    {
        return (string) $identity;
    }
}