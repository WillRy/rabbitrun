<?php

namespace WillRy\RabbitRun\Monitor;

use WillRy\RabbitRun\Traits\Helpers;

abstract class Monitor
{
    use Helpers;

    protected $connection;

    const STATUS_STOPPED = 0;
    const STATUS_RUNNING = 1;

    public $groupName = "workers";
    public $consumerName = "";
    public $entityName = "rabbit_monitor";


    abstract public function getWorkers(): array;

    abstract public function pauseWorker(): bool;

    abstract public function startWorker(): bool;

    abstract public function setWorkerItem($identificador): bool;

    abstract public function workerIsRunning(): bool;
}
