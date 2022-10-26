<?php

namespace WillRy\RabbitRun\PubSub;

interface WorkerInterface
{
    public function handle(Task $data);

    public function error(array $databaseData, \Exception $error = null);
}
