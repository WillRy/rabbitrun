<?php

namespace WillRy\RabbitRun\Drivers;

abstract class DriverAbstract
{

    protected static $entity = "job";

    abstract public function setStatusStopped(int $jobID);

    abstract public function insert(
        array $payload,
        bool  $requeue_on_error = true,
        int   $max_retries = 10,
        bool  $auto_delete_end = false,
        int   $id_owner = null,
        int   $id_object = null
    );

    abstract public function remove($id);

    abstract public function get($id);

    abstract public function setStatusProcessing($id);

    abstract public function setError($id, string $msg);

    abstract public function setStatusSuccess($id);

    abstract public function updateExecution(
        $id,
        string $status,
        ?int $retries,
        ?string $statusDescription
    );
}
