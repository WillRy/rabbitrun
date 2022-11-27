<?php

namespace WillRy\RabbitRun\Monitor;

use Predis\Client;
use WillRy\RabbitRun\Connections\ConnectRedis;
use WillRy\RabbitRun\Traits\Helpers;

class RedisMonitor extends Monitor
{
    use Helpers;

    /** @var string nome da storage de hsets */
    protected $storage;

    protected $consumerName;

    public function __construct($storage, $host, $port = 6379, $persistent = "1")
    {
        $this->storage = $storage;
        /** @var Client connection */
        $this->connection = ConnectRedis::config($host, $port, $persistent);
    }

    public function setWorker(string $nome, object $dados = null): bool
    {
        try {
            $obj = !empty($dados) ? $dados : new \stdClass();

            $dados->modified = date('Y-m-d H:i:s');
            $this->connection->set($nome, serialize($obj));

//            $this->connection->expire($nome, Monitor::MONITOR_TIMEOUT);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getWorkers(): array
    {
        try {
            $arr = $this->connection->scan(0, 'MATCH', "{$this->storage}*");
            array_shift($arr);

            if (empty($arr[0])) return [];

            $workers = array_values($arr[0]);

            $result = [];
            foreach ($workers as $worker) {
                $item = $this->connection->get($worker);
                $item = !empty($item) ? unserialize($item) : new \stdClass();
                $result[] = $item;
            }

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function pauseWorker(string $nome): bool
    {
        try {
            $nome = $this->getItemName($nome);

            $worker = $this->connection->get($nome);
            if (empty($worker)) return false;

            $worker = unserialize($worker);
            $worker->status = Monitor::STATUS_STOPPED;

            $this->setWorker($nome, $worker);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function startWorker(?string $nome = null): bool
    {
        try {
            $nome = $this->getItemName($nome);

            $worker = $this->connection->get($nome);

            $worker = !empty($worker) ? unserialize($worker) : new \stdClass();

            $worker->status = isset($worker->status) ? $worker->status : Monitor::STATUS_RUNNING;

            $this->setWorker($nome, $worker);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function setWorkerItem(string $nome, $identificador): bool
    {
        try {
            $nome = $this->getItemName($nome);

            $worker = $this->connection->get($nome);

            $worker = !empty($worker) ? unserialize($worker) : new \stdClass();
            $worker->task = $identificador;

            $this->setWorker($nome, $worker);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getItemName(?string $nome = null)
    {
        if (strpos($this->storage . ":" . $this->groupName, $nome) === false) {
            $this->consumerName = $nome ?? $this->randomConsumer(10);
            return $this->storage . ":" . $this->groupName . ":" . $this->consumerName;
        }

        $this->consumerName = $nome ?? $this->randomConsumer(10);
        return $this->consumerName;
    }

    public function workerIsRunning(string $nome): bool
    {
        try {
            $worker = $this->connection->get($this->getItemName($nome));
            if (empty($worker)) return false;

            $worker = unserialize($worker);

            return $worker->status === Monitor::STATUS_RUNNING;
        } catch (\Exception $e) {
            return true;
        }
    }
}
