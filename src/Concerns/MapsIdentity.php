<?php /** @noinspection PhpUndefinedMethodInspection */

namespace Sprocketbox\Eloquence\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Laravel\Nova\Fields\BelongsToMany;
use LogicException;
use Sprocketbox\Eloquence\Facades\Eloquence;
use Sprocketbox\Eloquence\ModelIdentity;
use Sprocketbox\Eloquence\Query\Builder;

/**
 * MapsIdentity
 *
 * This trait provides identity map functionality for Eloquent models.
 *
 * @package Sprocketbox\Eloquence\Concerns
 */
trait MapsIdentity
{
    /**
     * Boots the trait.
     */
    public static function bootMapsIdentity(): void
    {
        // Add a deleted event so the identity is removed from the map
        static::deleted(fn(Model $model) => Eloquence::removeIdentity($model->getModelIdentity()));
        // Add a created event so newly created models are stored
        static::created(fn(Model $model) => Eloquence::storeIdentity($model->getModelIdentity(), $model));
    }

    /**
     * Override the default newFromBuilder method to use the identity map.
     *
     * @see          \Illuminate\Database\Eloquent\Model::newFromBuilder()
     *
     * @param array $attributes
     * @param null  $connection
     *
     * @return \Illuminate\Database\Eloquent\Model
     * @noinspection PhpIncompatibleReturnTypeInspection
     */
    public function newFromBuilder($attributes = [], $connection = null): Model
    {
        $attributes = (array) $attributes;
        $key        = $attributes[$this->getKeyName()] ?? null;
        $identity   = $model = null;

        if ($key !== null) {
            $identity = $this->getModelIdentity($key, $connection);

            if (Eloquence::hasIdentity($identity)) {
                $model = Eloquence::getIdentity($identity);
                /** @noinspection NullPointerExceptionInspection */
                $this->updateModelAttributes($model, $attributes);

                return $model;
            }
        }

        $model = parent::newFromBuilder($attributes, $connection);

        if ($identity !== null) {
            Eloquence::storeIdentity($model->getModelIdentity(), $model);
        }

        return $model;
    }

    /**
     * Get the model identity.
     *
     * @param null        $id
     * @param string|null $connection
     *
     * @return \Sprocketbox\Eloquence\ModelIdentity
     */
    public function getModelIdentity($id = null, ?string $connection = null): ModelIdentity
    {
        $connection = $connection ?? $this->getConnectionName() ?? static::getConnectionResolver()->getDefaultConnection();

        return new ModelIdentity(static::class, $id ?? $this->getKey(), $connection);
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param \Illuminate\Database\Query\Builder $query
     *
     * @return \Illuminate\Database\Eloquent\Builder|\Sprocketbox\Eloquence\Query\Builder|static
     */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }

    /**
     * Change the original attributes to match the new attributes, and re-add the dirty records.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param array                               $attributes
     */
    protected function updateModelAttributes(Model $model, array $attributes = []): void
    {
        if (! $this->areAttributesMoreRecent($model, $attributes)) {
            return;
        }

        $dirtyAttributes = $model->getDirty();
        $model->setRawAttributes($attributes, true);
        $model->setRawAttributes(array_merge($model->getAttributes(), $dirtyAttributes), false);
    }

    /**
     * Check if the provided attributes are newer.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param array                               $attributes
     *
     * @return bool
     */
    protected function areAttributesMoreRecent(Model $model, array $attributes): bool
    {
        if (! $this->usesTimestamps()) {
            return true;
        }

        $updatedAt = $attributes[$this->getUpdatedAtColumn()];

        if ($updatedAt !== null) {
            $format = $this->getDateFormat();

            if (is_numeric($updatedAt)) {
                $updatedAt = Date::createFromTimestamp($updatedAt);
            } else if (Date::hasFormat($updatedAt, $format)) {
                $updatedAt = Date::createFromFormat($format, $updatedAt);
            }

            return $model->getAttribute($this->getUpdatedAtColumn())->isBefore($updatedAt);
        }

        return true;
    }

    /**
     * Get a relationship value from a method.
     *
     * @param string $method
     *
     * @return mixed
     *
     * @throws \LogicException
     */
    protected function getRelationshipFromMethod($method)
    {
        $relation = $this->$method();

        if (! $relation instanceof Relation) {
            if (is_null($relation)) {
                throw new LogicException(sprintf(
                    '%s::%s must return a relationship instance, but "null" was returned. Was the "return" keyword used?', static::class, $method
                ));
            }

            throw new LogicException(sprintf(
                '%s::%s must return a relationship instance.', static::class, $method
            ));
        }

        return tap($this->getRelationshipResults($relation), function ($results) use ($method) {
            $this->setRelation($method, $results);
        });
    }

    protected function getRelationshipResults(Relation $relation)
    {
        if ($relation instanceof BelongsToMany) {
            return $relation->getResults();
        }

        if ($relation instanceof BelongsTo) {
            $related = $relation->getRelated();

            if (method_exists($related, 'getModelIdentity')) {
                $identity = $related->getModelIdentity($this->getAttribute($relation->getForeignKeyName()));

                if (Eloquence::hasIdentity($identity)) {
                    return Eloquence::getIdentity($identity);
                }
            }
        }

        return $relation->getResults();
    }
}