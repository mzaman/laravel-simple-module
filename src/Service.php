<?php

namespace LaravelSimpleModule;

use Exception;

class Service implements BaseService
{

    /**
     * Find an item by id
     * @param mixed $id
     * @return Model|null
     */
    public function find($id)
    {
        return $this->repository->find($id);
    }

    /**
     * Find an item by id or fail
     * @param mixed $id
     * @return Model|null
     */
    public function findOrFail($id)
    {
        return $this->repository->findOrFail($id);
    }

    /**
     * Return all items
     * @return Collection|null
     */
    public function all()
    {
        return $this->repository->all();
    }

    /**
     * Create an item
     * @param array|mixed $data
     * @return void
     */
    public function create($data)
    {
        $this->repository->create($data);
    }

    /**
     * Update a model
     * @param int|mixed $id
     * @param array|mixed $data
     * @return void
     */
    public function update($id, array $data)
    {
        $this->repository->update($id, $data);
    }

    /**
     * Delete a model
     * @param int|Model $id
     * @return void
     */
    public function delete($id)
    {
        $this->repository->delete($id);
    }

    /**
     * multiple delete
     * @param array $id
     * @return void
     */
    public function destroy(array $id)
    {
        $this->repository->destroy($id);
    }
}
