<?php

namespace WillRy\RabbitRun\Queue\Interfaces;

use WillRy\RabbitRun\Queue\Job;

interface JobInterface
{
    public function setRequeueOnError(bool $requeue_on_error): Job;

    public function setMaxRetries(int $max_retries): Job;

    public function setAutoDelete(int $auto_delete): Job;

    public function setIdOwner(int $owner);

    public function setIdObject(int $object);

    public function getPayload(): array;

    public function getRequeueOnError(): bool;

    public function getMaxRetries(): int;

    public function getAutoDelete(): bool;

    public function getIdOwner();

    public function getIdObject();
}
