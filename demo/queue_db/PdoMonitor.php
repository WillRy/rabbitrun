<?php


class PdoMonitor
{
    protected \PDO $db;

    protected string $entity = "rabbit_monitor";

    protected string $consumerName;

    protected string $groupName;

    const STATUS_STOPPED = 0;

    const STATUS_RUNNING = 1;

    public function __construct(
        $table = "rabbit_monitor",
        $consumerName = null,
        $groupName = null
    )
    {
        $this->entity = $table;
        $this->consumerName = !empty($consumerName) ? $consumerName : rand(0, 999);
        $this->groupName = !empty($groupName) ? $groupName : rand(0, 999);

        \WillRy\RabbitRun\Connections\ConnectPDO::config(
            "mysql", "db", "env_db", "root", "root", 3306
        );

        $this->db = \WillRy\RabbitRun\Connections\ConnectPDO::getInstance(true);
    }

    public function getWorkers(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->entity}");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public function pauseWorker(): bool
    {
        $this->insertConsumerIfNotExists(PdoMonitor::STATUS_STOPPED);

        $stmt = $this->db->prepare(
            "UPDATE {$this->entity} SET status = :status, jobID = :jobID WHERE name = :name"
        );
        $stmt->bindValue('status', PdoMonitor::STATUS_STOPPED);
        $stmt->bindValue('jobID', null);
        $stmt->bindValue('name', $this->consumerName);
        $stmt->execute();

        return true;
    }

    public function startWorker(): bool
    {
        $this->insertConsumerIfNotExists(PdoMonitor::STATUS_RUNNING);

        return true;
    }

    public function setWorkerItem($identificador): bool
    {
        $this->insertConsumerIfNotExists(PdoMonitor::STATUS_RUNNING);

        $stmt = $this->db->prepare(
            "UPDATE {$this->entity} SET status = :status, jobID = :jobID, modifiedAt = :modifiedAt WHERE name = :name"
        );
        $stmt->bindValue('status', PdoMonitor::STATUS_RUNNING);
        $stmt->bindValue('jobID', $identificador);
        $stmt->bindValue('name', $this->consumerName);
        $stmt->bindValue('modifiedAt', date('Y-m-d H:i:s'));
        $stmt->execute();

        return true;
    }

    public function workerIsRunning(): bool
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->entity} WHERE name = :name");
        $stmt->bindValue('name', $this->consumerName);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_OBJ);

        if (!empty($row->status) && $row->status === PdoMonitor::STATUS_RUNNING) {
            return true;
        }

        return false;
    }

    public function insertConsumerIfNotExists(int $status = PdoMonitor::STATUS_RUNNING)
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->entity} WHERE name = :name");
        $stmt->bindValue('name', $this->consumerName);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_OBJ);

        if (empty($row)) {
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->entity}(name, status, jobID, groupName) VALUES(:name, :status, :jobID, :groupName)"
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
