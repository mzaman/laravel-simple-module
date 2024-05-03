<?php

namespace LaravelSimpleModule;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface RepositoryInterface
{
    /**
     * Find an item by id.
     *
     * @param mixed $id
     * @return Model|null
     */
    public function find($id);

    /**
     * Find an item by id or fail.
     *
     * @param mixed $id
     * @return Model
     */
    public function findOrFail($id);

    /**
     * Return all items.
     *
     * @return Collection|null
     */
    public function all();

    /**
     * Count the number of items.
     *
     * @return int
     */
    public function count();

    /**
     * Create an item.
     *
     * @param array $data
     * @return Model|null
     */
    public function create(array $data);

    /**
     * Create multiple items.
     *
     * @param array $data
     * @return Collection
     */
    public function createMultiple(array $data);

    /**
     * Delete all items.
     */
    public function delete();

    /**
     * Delete an item by id.
     *
     * @param mixed $id
     */
    public function deleteById($id);

    /**
     * Delete multiple items by ids.
     *
     * @param array $ids
     * @return int
     */
    public function deleteMultipleById(array $ids);

    /**
     * Get the first item.
     *
     * @return Model|null
     */
    public function first();

    /**
     * Get all items.
     *
     * @return Collection|null
     */
    public function get();

    /**
     * Get an item by id.
     *
     * @param mixed $id
     * @return Model|null
     */
    public function getById($id);

    /**
     * Limit the number of items.
     *
     * @param int $limit
     * @return $this
     */
    public function limit($limit);

    /**
     * Order the items.
     *
     * @param string $column
     * @param string $value
     * @return $this
     */
    public function orderBy($column, $value);

    /**
     * Update an item by id.
     *
     * @param mixed $id
     * @param array $data
     * @return bool
     */
    public function updateById($id, array $data);

    /**
     * Add a where clause.
     *
     * @param string $column
     * @param mixed $value
     * @param string $operator
     * @return $this
     */
    public function where($column, $value, $operator = '=');

    /**
     * Add a where in clause.
     *
     * @param string $column
     * @param mixed $values
     * @return $this
     */
    public function whereIn($column, $values);

    /**
     * Add eager loading.
     *
     * @param string|array $relations
     * @return $this
     */
    public function with($relations);
}
