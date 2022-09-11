<?php

namespace WillRy\RabbitRun;

use Exception;

interface WorkerInterface
{
    public function handle(Task $data);

    public function error(array $databaseData, \Exception $error = null);
}
