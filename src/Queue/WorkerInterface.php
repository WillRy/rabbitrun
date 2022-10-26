<?php

namespace WillRy\RabbitRun\Queue;

interface WorkerInterface
{
    public function handle(Task $data);

    public function error(array $databaseData, \Exception $error = null);
}
