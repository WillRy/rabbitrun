<?php

namespace WillRy\RabbitRun\Monitor;

abstract class Monitor
{
    protected $connection;

    const STATUS_STOPPED = 0;
    const STATUS_RUNNING = 1;

    public $groupName = "workers";


    abstract public function getWorkers(): array;

    abstract public function pauseWorker(string $nome): bool;

    abstract public function startWorker(?string $nome = null): bool;

    abstract public function setWorkerItem(string $nome, $identificador): bool;

    abstract public function workerIsRunning(string $nome): bool;

    abstract public function getItemName();
}
