<?php

namespace WillRy\RabbitRun\Queue\Interfaces;

use WillRy\RabbitRun\Queue\Task;

use Exception;

interface WorkerInterface
{
    public function handle(Task $data);

    public function error(array $databaseData, \Exception $error = null);
}
