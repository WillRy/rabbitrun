<?php

namespace WillRy\RabbitRun\Monitor;

use WillRy\RabbitRun\Connections\ConnectPDO;

class PDOMonitor extends Monitor
{


    public function __construct(string $groupName, string $consumerName = null, string $entityName = 'rabbit_monitor')
    {
        $this->entityName = $entityName;
        $this->consumerName = !empty($consumerName) ? $consumerName : $this->randomConsumer(10);
        $this->groupName = $groupName;
        $this->connection = ConnectPDO::getInstance();
    }

    public function getWorkers(): array
    {
        $stmt = $this->connection->prepare("SELECT * FROM {$this->entityName}");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public function pauseWorker(): bool
    {
        $this->insertConsumerIfNotExists(Monitor::STATUS_STOPPED);

        $stmt = $this->connection->prepare(
            "UPDATE {$this->entityName} SET status = :status, jobID = :jobID WHERE name = :name"
        );
        $stmt->bindValue('status', Monitor::STATUS_STOPPED);
        $stmt->bindValue('jobID', null);
        $stmt->bindValue('name', $this->consumerName);
        $stmt->execute();

        return true;
    }

    public function startWorker(): bool
    {
        $this->insertConsumerIfNotExists(Monitor::STATUS_RUNNING);

        return true;
    }

    public function setWorkerItem($identificador): bool
    {
        $this->insertConsumerIfNotExists(Monitor::STATUS_RUNNING);

        $stmt = $this->connection->prepare(
            "UPDATE {$this->entityName} SET status = :status, jobID = :jobID, modifiedAt = :modifiedAt WHERE name = :name"
        );
        $stmt->bindValue('status', Monitor::STATUS_RUNNING);
        $stmt->bindValue('jobID', $identificador);
        $stmt->bindValue('name', $this->consumerName);
        $stmt->bindValue('modifiedAt', date('Y-m-d H:i:s'));
        $stmt->execute();

        return true;
    }

    public function workerIsRunning(): bool
    {
        $stmt = $this->connection->prepare("SELECT * FROM {$this->entityName} WHERE name = :name");
        $stmt->bindValue('name', $this->consumerName);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_OBJ);

        if (!empty($row->status) && $row->status === Monitor::STATUS_RUNNING) {
            return true;
        }

        return false;
    }

    public function insertConsumerIfNotExists(int $status = Monitor::STATUS_RUNNING)
    {
        $stmt = $this->connection->prepare("SELECT * FROM {$this->entityName} WHERE name = :name");
        $stmt->bindValue('name', $this->consumerName);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_OBJ);

        if (empty($row)) {
            $stmt = $this->connection->prepare(
                "INSERT INTO {$this->entityName}(name, status, jobID, groupName) VALUES(:name, :status, :jobID, :groupName)"
            );
            $stmt->bindValue('name', $this->consumerName);
            $stmt->bindValue('status', $status);
            $stmt->bindValue('jobID', null);
            $stmt->bindValue('groupName', $this->groupName);
            $stmt->execute();
        }

        return true;
    }
}
