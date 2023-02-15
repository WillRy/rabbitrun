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


    public function setError(int $id, string $error)
    {
        $stmt = $this->db->prepare("update " . $this->entity . " set last_error = ? where id = ?");
        $stmt->bindValue(1, $error, \PDO::PARAM_INT);
        $stmt->bindValue(2, $id, \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function delete(int $id)
    {
        $stmt = $this->db->prepare("DELETE FROM " . $this->entity . " where id = ?");
        $stmt->bindValue(1, $id, \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function checkDelete(int $id)
    {
        $stmt = $this->db->prepare("SELECT * FROM " . $this->entity . " WHERE id = ? limit 1");
        $stmt->bindValue(1, $id, \PDO::PARAM_INT);
        $stmt->execute();

        $task = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!empty($task['auto_delete_end'])) {
            $stmt = $this->db->prepare("DELETE FROM " . $this->entity . " where id = ?");
            $stmt->bindValue(1, $id, \PDO::PARAM_INT);
            $stmt->execute();
        }

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


}
