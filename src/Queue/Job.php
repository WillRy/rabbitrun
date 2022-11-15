<?php

namespace WillRy\RabbitRun\Queue;

use WillRy\RabbitRun\Queue\Interfaces\JobInterface;

class Job implements JobInterface
{
    protected $payload = [];

    protected $requeue_on_error = true;

    protected $max_retries = 3;

    protected $auto_delete = true;

    protected $owner_id = null;

    protected $object_id = null;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
        return $this;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setRequeueOnError(bool $requeue_on_error): Job
    {
        $this->requeue_on_error = $requeue_on_error;
        return $this;
    }

    public function setMaxRetries(int $max_retries): Job
    {
        $this->max_retries = $max_retries;
        return $this;
    }

    public function setAutoDelete(int $auto_delete): Job
    {
        $this->auto_delete = $auto_delete;
        return $this;
    }

    public function setIdOwner(int $owner): Job
    {
        $this->owner_id = $owner;
        return $this;
    }

    public function setIdObject(int $object): Job
    {
        $this->object_id = $object;
        return $this;
    }

    /**
     * @return bool
     */
    public function getRequeueOnError(): bool
    {
        return $this->requeue_on_error;
    }

    /**
     * @return int
     */
    public function getMaxRetries(): int
    {
        return $this->max_retries;
    }

    /**
     * @return bool
     */
    public function getAutoDelete(): bool
    {
        return $this->auto_delete;
    }

    public function getIdOwner()
    {
        return $this->owner_id;
    }

    public function getIdObject()
    {
        return $this->object_id;
    }
}
