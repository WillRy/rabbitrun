<?php

namespace WillRy\RabbitRun\Queue\Interfaces;

use WillRy\RabbitRun\Queue\Task;

interface WorkerInterface
{
    public function handle(Task $data);

    public function error(array $databaseData, \Exception $error = null);
}
