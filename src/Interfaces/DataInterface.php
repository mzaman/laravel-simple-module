<?php

namespace LaravelSimpleModule\Interfaces;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DataInterface
{
    /**
     * Find an item by id.
     *
     * @param mixed $id
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function find($id);

    /**
     * Find an item by id or fail.
     *
     * @param mixed $id
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function findOrFail($id);

    /**
     * Get all the model records in the database.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function all();

    /**
     * Count the number of specified model records in the database.
     *
     * @return int
     */
    public function count();

    /**
     * Create an item.
     *
     * @param array $data
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function create(array $data);

    /**
     * Create multiple items.
     *
     * @param array $data
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function createMultiple(array $data);

    /**
     * Delete all items.
     *
     * @return bool|null
     */
    public function delete();

    /**
     * Delete the specified model record from the database.
     *
     * @param mixed $id
     * @return bool|null
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
     * Get the first specified model record from the database.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function first();

    /**
     * Get the first specified model record from the database or throw an exception if not found.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function firstOrFail();

    /**
     * Get all the specified model records in the database.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function get();

    /**
     * Get the specified model record from the database by its ID.
     *
     * @param mixed $id
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function getById($id);

    /**
     * Get the specified model record from the database by its ID or throw an exception if not found.
     *
     * @param mixed $id
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function getByIdOrFail($id);

    /**
     * Get an item by column value.
     *
     * @param mixed $item
     * @param string $column
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function getByColumn($item, $column, array $columns = ['*']);

    /**
     * Set the query limit.
     *
     * @param int $limit
     * @return $this
     */
    public function limit($limit);

    /**
     * Set an ORDER BY clause.
     *
     * @param string $column
     * @param string $direction
     * @return $this
     */
    public function orderBy($column, $direction = 'asc');

    /**
     * Update an item by id.
     *
     * @param mixed $id
     * @param array $data
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function updateById($id, array $data);

    /**
     * Paginate the specified model records.
     *
     * @param int $limit
     * @param array $columns
     * @param string $pageName
     * @param int|null $page
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginate($limit = 25, array $columns = ['*'], $pageName = 'page', $page = null);

    /**
     * Add a simple where clause to the query.
     *
     * @param string $column
     * @param mixed $value
     * @param string $operator
     * @return $this
     */
    public function where($column, $value, $operator = '=');

    /**
     * Add a simple where in clause to the query.
     *
     * @param string $column
     * @param mixed $values
     * @return $this
     */
    public function whereIn($column, $values);

    /**
     * Set Eloquent relationships to eager load.
     *
     * @param string|array $relations
     * @return $this
     */
    public function with($relations);
}
