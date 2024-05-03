<?php


namespace LaravelSimpleModule\Implementations;

use Exception;
use Illuminate\Database\Eloquent\Model;
use LaravelSimpleModule\Repository;

class Eloquent implements Repository
{
    /**
     * Model instance.
     *
     * @var Model
     */
    protected $model;

    /**
     * Eloquent constructor.
     *
     * @param Model $model
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Find an item by id.
     *
     * @param mixed $id
     * @return Model|null
     */
    public function find($id)
    {
        return $this->model->find($id);
    }

    /**
     * Find an item by id or fail.
     *
     * @param mixed $id
     * @return Model
     */
    public function findOrFail($id)
    {
        return $this->model->findOrFail($id);
    }

    /**
     * Return all items.
     *
     * @return Collection|null
     */
    public function all()
    {
        return $this->model->all();
    }

    /**
     * Create an item.
     *
     * @param array|mixed $data
     * @return Model|null
     */
    public function create($data)
    {
        return $this->model->create($data);
    }

    /**
     * Create one or more new model records in the database.
     *
     * @param array $data
     * @return Collection
     */
    public function createMultiple(array $data)
    {
        $models = new Collection();

        foreach ($data as $d) {
            $models->push($this->create($d));
        }

        return $models;
    }

    /**
     * Delete an item.
     *
     * @param Model|int $id
     * @return mixed
     */
    public function delete($id)
    {
        return $this->model->findOrFail($id)->delete();
    }

    /**
     * Delete multiple records.
     *
     * @param array $ids
     * @return int
     */
    public function deleteMultipleById(array $ids)
    {
        return $this->model->destroy($ids);
    }

    /**
     * Get the first specified model record from the database.
     *
     * @return Model
     */
    public function first()
    {
        return $this->model->firstOrFail();
    }

    /**
     * Get all the specified model records in the database.
     *
     * @return Collection
     */
    public function get()
    {
        return $this->model->get();
    }

    /**
     * Get the specified model record from the database.
     *
     * @param mixed $id
     * @return Model
     */
    public function getById($id)
    {
        return $this->model->findOrFail($id);
    }

    /**
     * Set the query limit.
     *
     * @param int $limit
     * @return $this
     */
    public function limit($limit)
    {
        return $this->model->limit($limit);
    }

    /**
     * Set an ORDER BY clause.
     *
     * @param string $column
     * @param string $direction
     * @return $this
     */
    public function orderBy($column, $direction = 'asc')
    {
        return $this->model->orderBy($column, $direction);
    }

    /**
     * Update the specified model record in the database.
     *
     * @param mixed $id
     * @param array $data
     * @return Model
     */
    public function updateById($id, array $data)
    {
        $model = $this->getById($id);
        $model->update($data);
        return $model;
    }

    /**
     * Add a simple where clause to the query.
     *
     * @param string $column
     * @param mixed $value
     * @param string $operator
     * @return $this
     */
    public function where($column, $value, $operator = '=')
    {
        return $this->model->where($column, $operator, $value);
    }

    /**
     * Add a simple where in clause to the query.
     *
     * @param string $column
     * @param array $values
     * @return $this
     */
    public function whereIn($column, $values)
    {
        return $this->model->whereIn($column, $values);
    }

    /**
     * Set Eloquent relationships to eager load.
     *
     * @param mixed $relations
     * @return $this
     */
    public function with($relations)
    {
        return $this->model->with($relations);
    }
}