<?php

namespace App\Domain\Shared\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

/**
 * Generic relationship eager loader with caching and fallback logic.
 * 
 * Provides a consistent interface for:
 * - Checking if relationship is already eager loaded
 * - Loading relationship if not available
 * - Caching loaded data
 * - Fallback queries when needed
 * 
 * Used across all resources to ensure consistent loading patterns
 * and prevent N+1 query problems.
 * 
 * Example:
 * ```php
 * $users = $loader->ensureLoaded($user, 'accessProfiles');
 * $profiles = $user->accessProfiles;
 * ```
 */
class RelationshipEagerLoader
{
    /**
     * Cache for loaded relationships.
     * 
     * @var array<string, array>
     */
    private array $cache = [];

    /**
     * Ensure a relationship is loaded on a model.
     * 
     * If the relationship is not already eager loaded, it will be loaded
     * and cached for subsequent calls.
     * 
     * @param Model $model
     * @param string|array $relations Relation name or array of relation names
     * @return Model The model with loaded relationships
     */
    public function ensureLoaded(Model $model, string|array $relations): Model
    {
        $relations = is_string($relations) ? [$relations] : $relations;

        foreach ($relations as $relation) {
            if (!$model->relationLoaded($relation)) {
                $model->load($relation);
            }
        }

        return $model;
    }

    /**
     * Ensure relationships are loaded on a collection.
     * 
     * @param Collection $collection
     * @param string|array $relations
     * @return Collection
     */
    public function ensureLoadedMany(Collection $collection, string|array $relations): Collection
    {
        $relations = is_string($relations) ? [$relations] : $relations;

        $unloadedRelations = [];
        foreach ($relations as $relation) {
            // Check if at least one model doesn't have the relationship loaded
            if ($collection->contains(fn($model) => !$model->relationLoaded($relation))) {
                $unloadedRelations[] = $relation;
            }
        }

        if (!empty($unloadedRelations)) {
            $collection->load($unloadedRelations);
        }

        return $collection;
    }

    /**
     * Get related models, loading if necessary.
     * 
     * Returns the related models from a relationship, automatically loading
     * if not already eager loaded.
     * 
     * @param Model $model
     * @param string $relation
     * @return Model|Collection|null Related model(s)
     */
    public function get(Model $model, string $relation): Model|Collection|null
    {
        $this->ensureLoaded($model, $relation);
        return $model->{$relation};
    }

    /**
     * Count related models, using loaded relationship if available.
     * 
     * @param Model $model
     * @param string $relation
     * @return int Count of related models
     */
    public function count(Model $model, string $relation): int
    {
        if ($model->relationLoaded($relation)) {
            return $model->{$relation}->count();
        }

        return $model->{$relation}()->count();
    }

    /**
     * Pluck a column from related models.
     * 
     * @param Model $model
     * @param string $relation
     * @param string $column Column to pluck
     * @param string|null $key Optional key for the result array
     * @return Collection Plucked values
     */
    public function pluck(Model $model, string $relation, string $column, string $key = null): Collection
    {
        if ($model->relationLoaded($relation)) {
            return $model->{$relation}->pluck($column, $key);
        }

        return $model->{$relation}()->pluck($column, $key);
    }

    /**
     * Check if a model has a loaded relationship.
     * 
     * @param Model $model
     * @param string $relation
     * @return bool
     */
    public function isLoaded(Model $model, string $relation): bool
    {
        return $model->relationLoaded($relation);
    }

    /**
     * Unload a relationship from a model.
     * 
     * @param Model $model
     * @param string $relation
     * @return Model
     */
    public function unload(Model $model, string $relation): Model
    {
        $relations = $model->getRelations();
        unset($relations[$relation]);
        $model->setRelations($relations);

        return $model;
    }

    /**
     * Get cache key for a model's relationship.
     * 
     * @param Model $model
     * @param string $relation
     * @return string
     */
    private function getCacheKey(Model $model, string $relation): string
    {
        return sprintf('%s.%s.%s', $model::class, $model->getKey(), $relation);
    }
}
