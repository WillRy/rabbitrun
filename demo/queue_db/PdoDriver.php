<?php

use WillRy\RabbitRun\Monitor\Monitor;

class PdoDriver
{
    protected \PDO $db;

    protected string $entity = "jobs";

    protected string $consumerName;

    protected string $groupName;


    public function __construct(
        $table = "jobs",
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

    public function getTask(int $id)
    {
        $stmt = $this->db->prepare("SELECT * FROM " . $this->entity . " WHERE id = ? limit 1");
        $stmt->bindValue(1, $id, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function hasRetry(int $id)
    {
        $stmt = $this->db->prepare("SELECT * FROM " . $this->entity . " WHERE id = ? limit 1");
        $stmt->bindValue(1, $id, \PDO::PARAM_INT);
        $stmt->execute();
        $task = $stmt->fetch(\PDO::FETCH_ASSOC);


        $retries = $task["retries"];
        $maxRetries = $task['max_retries'];

        return $retries < $maxRetries;
    }

    public function sumRetry(int $id)
    {
        $stmt = $this->db->prepare("update " . $this->entity . " set retries = retries + 1 where id = ?");
        $stmt->bindValue(1, $id, \PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Insere um item no banco
     * @param array $payload
     * @param bool $requeue_on_error
     * @param int $max_retries
     * @param bool $auto_delete_end
     * @param int|null $id_owner
     * @param int|null $id_object
     * @return int
     */
    public function insert(
        string $queue_name,
        array  $payload,
        bool   $requeue_on_error = true,
        int    $max_retries = 10,
        bool   $auto_delete_end = false,
        int    $id_owner = null,
        int    $id_object = null
    ): int
    {
        $stmt = $this->db->prepare("INSERT INTO " . $this->entity . "(queue, payload, requeue_error, max_retries, auto_delete_end, id_owner, id_object) VALUES(?,?,?,?,?,?,?)");
        $stmt->bindValue(1, $queue_name);
        $stmt->bindValue(2, json_encode($payload));
        $stmt->bindValue(3, $requeue_on_error);
        $stmt->bindValue(4, $max_retries);
        $stmt->bindValue(5, $auto_delete_end, \PDO::PARAM_BOOL);
        $stmt->bindValue(6, $id_owner, \PDO::PARAM_INT);
        $stmt->bindValue(7, $id_object, \PDO::PARAM_INT);
        $stmt->execute();

        return $this->db->lastInsertId();
    }

    public function getWorkers(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->entity}");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public function pauseWorker(): bool
    {
        $this->insertConsumerIfNotExists(PdoDriver::STATUS_STOPPED);

        $stmt = $this->db->prepare(
            "UPDATE {$this->entity} SET status = :status, jobID = :jobID WHERE name = :name"
        );
        $stmt->bindValue('status', PdoDriver::STATUS_STOPPED);
        $stmt->bindValue('jobID', null);
        $stmt->bindValue('name', $this->consumerName);
        $stmt->execute();

        return true;
    }

    public function startWorker(): bool
    {
        $this->insertConsumerIfNotExists(PdoDriver::STATUS_RUNNING);

        return true;
    }

    public function setWorkerItem($identificador): bool
    {
        $this->insertConsumerIfNotExists(PdoDriver::STATUS_RUNNING);

        $stmt = $this->db->prepare(
            "UPDATE {$this->entity} SET status = :status, jobID = :jobID, modifiedAt = :modifiedAt WHERE name = :name"
        );
        $stmt->bindValue('status', PdoDriver::STATUS_RUNNING);
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

        if (!empty($row->status) && $row->status === PdoDriver::STATUS_RUNNING) {
            return true;
        }

        return false;
    }

    public function insertConsumerIfNotExists(int $status = PdoDriver::STATUS_RUNNING)
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
