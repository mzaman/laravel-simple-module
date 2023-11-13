<?php


namespace LaravelSimpleModule;

use LaravelSimpleModule\Traits\ResultService;

class ServiceApi implements BaseService
{
    use ResultService;

    protected $title = "";
    protected $create_message = "";
    protected $update_message = "";
    protected $delete_message = "";

    /**
     * Find an item by id
     * @param mixed $id
     * @return Model|null
     */
    public function find($id)
    {
        try {
            $result = $this->repository->find($id);
            return $this->setResult($result)
                        ->setCode(200)
                        ->setStatus(true);
        } catch (\Exception $exception) {
            return $this->exceptionResponse($exception);
        }
    }

    /**
     * Find an item by id or fail
     * @param mixed $id
     * @return Model|null
     */
    public function findOrFail($id)
    {
        try {
            $result = $this->repository->findOrFail($id);
            return $this->setResult($result)
                ->setCode(200)
                ->setStatus(true);
        } catch (\Exception $exception) {
            return $this->exceptionResponse($exception);
        }
    }

    /**
     * Return all items
     * @return Collection|null
     */
    public function all()
    {
        try {
            $result = $this->repository->all();;
            return $this->setResult($result)
                ->setCode(200)
                ->setStatus(true);
        } catch (\Exception $exception) {
            return $this->exceptionResponse($exception);
        }
    }

    /**
     * Create an item
     * @param array|mixed $data
     * @return Model|null
     */
    public function create($data)
    {
        try {
            $this->repository->create($data);
            return $this->setMessage($this->title." ".$this->create_message)
                ->setCode(200)
                ->setStatus(true);
        } catch (\Exception $exception) {
            return $this->exceptionResponse($exception);
        }
    }

    /**
     * Update a model
     * @param int|mixed $id
     * @param array|mixed $data
     * @return bool|mixed
     */
    public function update($id, array $data)
    {
        try {
            $this->repository->update($id, $data);
            return $this->setMessage($this->title." ".$this->update_message)
                ->setCode(200)
                ->setStatus(true);
        } catch (\Exception $exception) {
            return $this->exceptionResponse($exception);
        }
    }

    /**
     * Delete a model
     * @param int|Model $id
     */
    public function delete($id)
    {
        try {
            $this->repository->delete($id);
            return $this->setMessage($this->title." ".$this->delete_message)
                ->setCode(200)
                ->setStatus(true);
        } catch (\Exception $exception) {
            return $this->exceptionResponse($exception);
        }
    }

    /**
     * multiple delete
     * @param array $id
     * @return mixed
     */
    public function destroy(array $id)
    {
        try {
            $this->repository->destroy($id);
            return $this->setMessage($this->title." ".$this->delete_message)
                ->setCode(200)
                ->setStatus(true);
        } catch (\Exception $exception) {
            return $this->exceptionResponse($exception);
        }
    }
}
