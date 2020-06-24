<?php

namespace Sprocketbox\Eloquence\Query;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Sprocketbox\Eloquence\Concerns\MapsIdentity;
use Sprocketbox\Eloquence\Facades\Eloquence;
use Sprocketbox\Eloquence\ModelIdentity;

class Builder extends EloquentBuilder
{
    /**
     * @var bool
     */
    protected bool  $identityIsMapped       = false;

    protected bool  $refreshIdentityMap     = false;

    protected array $noConstraintEagerLoads = [];

    public function useIdentityMap(): self
    {
        $this->refreshIdentityMap = false;

        return $this;
    }

    public function refreshIdentityMap(): self
    {
        $this->refreshIdentityMap = true;

        return $this;
    }

    /**
     * Find an instance of the model from the identity map or database.
     *
     * @param       $id
     *
     * @param array $columns
     *
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Collection|null
     */
    public function findOrIdentify($id, array $columns = ['*'])
    {
        if (is_array($id) || $id instanceof Arrayable) {
            return $this->findMany($id, $columns);
        }

        $model = $this->identifyModel($id);

        if ($model !== null) {
            return $model;
        }

        return $this->whereKey($id)->first($columns);
    }

    public function find($id, $columns = ['*'])
    {
        if ($this->shouldUseIdentityMap()) {
            return $this->findOrIdentify($id, $columns);
        }

        return parent::find($id, $columns);
    }

    public function findMany($ids, $columns = ['*']): Collection
    {
        $ids = $ids instanceof Arrayable ? $ids->toArray() : $ids;

        if (empty($ids)) {
            return $this->model->newCollection();
        }

        if ($this->shouldUseIdentityMap()) {
            $models = [];
            $newIds = [];

            foreach ($ids as $id) {
                $models[$id] = $this->identifyModel($id);

                if ($models[$id] === null) {
                    $newIds[] = $id;
                }
            }

            $newModels = $this->whereKey($newIds)->get($columns);
            $newModels->each(fn(Model $model) => $models[$model->getKey()] = $model);

            return $this->model->newCollection(array_values($models));
        }

        return parent::findMany($ids, $columns);
    }

    public function setModel(Model $model): self
    {
        if (in_array(MapsIdentity::class, class_uses($model), true)) {
            $this->identityIsMapped = true;
        }

        return parent::setModel($model);
    }

    protected function shouldUseIdentityMap(): bool
    {
        return ! $this->refreshIdentityMap
            && empty($this->query->bindings['where'])
            && empty($this->query->bindings['join'] ?? [])
            && empty($this->query->bindings['having'] ?? []);
    }

    protected function identifyModel($id): ?Model
    {
        if (! $this->identityIsMapped || ! $this->shouldUseIdentityMap()) {
            return null;
        }

        if ($id instanceof ModelIdentity) {
            $identity = $id;
        } else {
            $identity = $this->model->getModelIdentity($id, $this->getConnection()->getName());
        }

        if (Eloquence::hasIdentity($identity)) {
            return Eloquence::getIdentity($identity);
        }

        return null;
    }

    protected function eagerLoadRelation(array $models, $name, Closure $constraints)
    {
        $relation     = $this->getRelation($name);
        $loadedModels = [];
        $newModels    = $models;

        if ($this->eagerLoadHasNoConstraints($name) && $this->shouldUseIdentityMap()) {
            /**
             * This is intentionally empty so that the relation doesn't get caught in the
             * belongs to block below it.
             *
             * @noinspection PhpStatementHasEmptyBodyInspection
             * @noinspection MissingOrEmptyGroupStatementInspection
             */
            if ($relation instanceof BelongsToMany) {
                //
            } else if ($relation instanceof BelongsTo) {
                $loadedModels = $this->eagerLoadBelongsToIdentities($relation, $newModels);
            }
        }

        if (empty($newModels)) {
            $eagerModels = $relation->getRelated()->newCollection($loadedModels);
        } else {
            $relation->addEagerConstraints($newModels);
            $constraints($relation);
            $eagerModels = $relation->getEager()->merge($loadedModels);
        }

        // Once we have the results, we just match those back up to their parent models
        // using the relationship instance. Then we just return the finished arrays
        // of models which have been eagerly hydrated and are readied for return.
        return $relation->match(
            $relation->initRelation($models, $name),
            $eagerModels, $name
        );
    }

    /**
     * Parse a list of relations into individuals.
     *
     * @param array $relations
     *
     * @return array
     */
    protected function parseWithRelations(array $relations): array
    {
        $results = [];

        foreach ($relations as $name => $constraints) {
            // If the "name" value is a numeric key, we can assume that no constraints
            // have been specified. We will just put an empty Closure there so that
            // we can treat these all the same while we are looping through them.
            if (is_numeric($name)) {
                $name = $constraints;

                if (Str::contains($name, ':')) {
                    [$name, $constraints] = $this->createSelectWithConstraint($name);
                } else {
                    $this->noConstraintEagerLoads[] = $name;
                    $constraints                    = static function () {
                        //
                    };
                }
            }

            // We need to separate out any nested includes, which allows the developers
            // to load deep relationships using "dots" without stating each level of
            // the relationship with its own key in the array of eager-load names.
            $results = $this->addNestedWiths($name, $results);

            $results[$name] = $constraints;
        }

        return $results;
    }

    protected function eagerLoadHasNoConstraints(string $name): bool
    {
        return in_array($name, $this->noConstraintEagerLoads, true);
    }

    protected function eagerLoadBelongsToIdentities(BelongsTo $relation, array &$models): array
    {
        $newModels    = [];
        $loadedModels = [];

        foreach ($models as $i => $model) {
            $key = $model->getAttribute($relation->getForeignKeyName());

            if ($key !== null) {
                if (method_exists($relation->getRelated(), 'getModelIdentity')) {
                    $loadedModel = $this->identifyModel(
                        $relation->getRelated()->getModelIdentity($key, $this->getConnection()->getName())
                    );

                    if ($loadedModel !== null) {
                        $loadedModels[] = $loadedModel;
                        continue;
                    }
                }

                $newModels[] = $key;
            }
        }

        $models = $newModels;

        return array_unique($loadedModels);
    }
}