<?php

namespace Framework\Database;

use Framework\Support\Traits\ForwardsCalls;
use Framework\Database\Query\Builder as QueryBuilder;

/**
 * @mixin \Framework\Database\Query\Builder
 */
class Builder
{
    use ForwardsCalls;

    /**
     * The base query builder instance.
     *
     * @var \Framework\Database\Query\Builder
     */
    protected $query;

    /**
     * The model being queried.
     *
     * @var \Framework\Database\Model
     */
    protected $model;

    /**
     * Create a new Eloquent query builder instance.
     *
     * @param  \Framework\Database\Query\Builder  $query
     * @return void
     */
    public function __construct(QueryBuilder $query)
    {
        $this->query = $query;
    }

    /**
     * Execute the query and get the first result.
     *
     * @param  array|string  $columns
     * @return \Framework\Database\Model|object|static|null
     */
    public function first($columns = ['*'])
    {
        return $this->take(1)->get($columns)->first();
    }

    /**
     * Create a collection of models from plain arrays.
     *
     * @param  array  $items
     * @return \Framework\Database\Collection
     */
    public function hydrate(array $items)
    {
        $instance = $this->newModelInstance();

        return $instance->newCollection(array_map(function ($item) use ($items, $instance) {
            $model = $instance->newFromBuilder($item);

            if (count($items) > 1) {
                // $model->preventsLazyLoading = Model::preventsLazyLoading();
            }

            return $model;
        }, $items));
    }

    /**
     * Find a model by its primary key.
     *
     * @param  mixed  $id
     * @param  array|string  $columns
     * @return \Framework\Database\Model|\Framework\Database\Collection|static[]|static|null
     */
    public function find($id, $columns = ['*'])
    {
        $this->query->where('id', '=', $id);

        return $this->first();
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array|string  $columns
     * @return \Framework\Database\Collection|static[]
     */
    public function get($columns = ['*'])
    {
        $builder = $this->applyScopes();

        $models = $builder->getModels($columns);

        return $builder->getModel()->newCollection($models);
    }

    /**
     * Get the hydrated models without eager loading.
     *
     * @param  array|string  $columns
     * @return \Framework\Database\Model[]|static[]
     */
    public function getModels($columns = ['*'])
    {
        return $this->model->hydrate(
            $this->query->get($columns)
        )->all();
    }

    /**
     * Update records in the database.
     *
     * @param  array  $values
     * @return int
     */
    public function update(array $values)
    {
        return $this->toBase()->update($this->addUpdatedAtColumn($values));
    }

    /**
     * Add the "updated at" column to an array of values.
     *
     * @param  array  $values
     * @return array
     */
    protected function addUpdatedAtColumn(array $values)
    {
        return $values;
    }

    /**
     * Delete records from the database.
     *
     * @return mixed
     */
    public function delete()
    {
        return $this->toBase()->delete();
    }

    /**
     * Apply the scopes to the Eloquent builder instance and return it.
     *
     * @return static
     */
    public function applyScopes()
    {
        $builder = clone $this;

        return $builder;
    }

    /**
     * Create a new instance of the model being queried.
     *
     * @param  array  $attributes
     * @return \Framework\Database\Model|static
     */
    public function newModelInstance($attributes = [])
    {
        return $this->model->newInstance($attributes)->setConnection(
            $this->query->getConnection()->getName()
        );
    }

    /**
     * Get the underlying query builder instance.
     *
     * @return \Framework\Database\Query\Builder
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Set the underlying query builder instance.
     *
     * @param  \Framework\Database\Query\Builder  $query
     * @return $this
     */
    public function setQuery($query)
    {
        $this->query = $query;

        return $this;
    }

    /**
     * Get a base query builder instance.
     *
     * @return \Framework\Database\Query\Builder
     */
    public function toBase()
    {
        return $this->applyScopes()->getQuery();
    }

    /**
     * Get the model instance being queried.
     *
     * @return \Framework\Database\Model|static
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Set a model instance for the model being queried.
     *
     * @param  \Framework\Database\Model  $model
     * @return $this
     */
    public function setModel(Model $model)
    {
        $this->model = $model;

        $this->query->from($model->getTable());

        return $this;
    }

    /**
     * Dynamically handle calls into the query instance.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $this->forwardCallTo($this->query, $method, $parameters);

        return $this;
    }

    /**
     * Clone the Eloquent query builder.
     *
     * @return static
     */
    public function clone()
    {
        return clone $this;
    }

    /**
     * Force a clone of the underlying query builder when cloning.
     *
     * @return void
     */
    public function __clone()
    {
        $this->query = clone $this->query;
    }
}
