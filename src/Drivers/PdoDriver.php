<?php

namespace WillRy\RabbitRun\Drivers;

use WillRy\RabbitRun\Drivers\DriverAbstract;

class PdoDriver extends DriverAbstract
{
    protected $db;

    public function __construct(
        $driver,
        $host,
        $dbname,
        $user,
        $pass,
        $port,
        $table = "jobs"
    )
    {
        static::$entity = $table;

        $this->db = \WillRy\RabbitRun\Connections\ConnectPDO::config(
            $driver, $host, $dbname, $user, $pass, $port
        );
    }

    /**
     * Define um item como "parado"
     * @param int $jobID
     */
    public function setStatusStopped($jobID)
    {
        $stmt = $this->db->prepare("UPDATE " . static::$entity . " SET status = ? WHERE id = ?");
        $stmt->bindValue(1, 'stopped');
        $stmt->bindValue(2, $jobID);
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
        array $payload,
        bool  $requeue_on_error = true,
        int   $max_retries = 10,
        bool  $auto_delete_end = false,
        int   $id_owner = null,
        int   $id_object = null
    ): int
    {
        $stmt = $this->db->prepare("INSERT INTO " . static::$entity . "(queue, payload, requeue_error, max_retries, auto_delete_end, id_owner, id_object) VALUES(?,?,?,?,?,?,?)");
        $stmt->bindValue(1, $payload['queue']);
        $stmt->bindValue(2, json_encode($payload));
        $stmt->bindValue(3, $requeue_on_error);
        $stmt->bindValue(4, $max_retries);
        $stmt->bindValue(5, $auto_delete_end, \PDO::PARAM_BOOL);
        $stmt->bindValue(6, $id_owner, \PDO::PARAM_INT);
        $stmt->bindValue(7, $id_object, \PDO::PARAM_INT);
        $stmt->execute();

        return $this->db->lastInsertId();
    }

    /**
     * Remove um item no banco
     * @param int|null $id
     */
    public function remove($id)
    {
        if ($id) {
            $stmt = $this->db->prepare("DELETE FROM " . static::$entity . " WHERE id = ?");
            $stmt->bindValue(1, $id);
            $stmt->execute();
        }
    }

    /**
     * Retorna um item do banco
     * @param int|null $id
     * @return mixed
     */
    public function get($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM " . static::$entity . " WHERE id = ? limit 1");
        $stmt->bindValue(1, $id, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Define um item como "processando"
     * @param int $id
     */
    public function setStatusProcessing($id)
    {
        $stmt = $this->db->prepare("update " . static::$entity . " set start_at = ?, status = ?, end_at = null where id = ?");
        $stmt->bindValue(1, date('Y-m-d H:i:s'));
        $stmt->bindValue(2, "processing");
        $stmt->bindValue(3, $id);
        $stmt->execute();
    }

    /**
     * Salva erro no item
     * @param int $id
     * @param string $msg
     */
    public function setError($id, string $msg)
    {
        $stmt = $this->db->prepare("UPDATE " . static::$entity . " SET last_error = ? WHERE id = ?");
        $stmt->bindValue(1, $msg);
        $stmt->bindValue(2, $id);
        $stmt->execute();
    }

    /**
     * Marca o item como "sucesso"
     * @param int $id
     * @return bool
     */
    public function setStatusSuccess($id)
    {
        $stmt = $this->db->prepare("UPDATE " . static::$entity . " SET end_at = ?, status = ? where id = ?");
        $stmt->bindValue(1, date('Y-m-d H:i:s'));
        $stmt->bindValue(2, 'success');
        $stmt->bindValue(3, $id);
        return $stmt->execute();
    }

    public function updateExecution(
        $id,
        string $status,
        ?int $retries,
        ?string $statusDescription
    )
    {
        $stmt = $this->db->prepare("UPDATE " . static::$entity . " SET end_at = ?, status = ?, retries = ?, status_desc = ? WHERE id = ?");
        $stmt->bindValue(1, date('Y-m-d H:i:s'));
        $stmt->bindValue(2, $status);
        $stmt->bindValue(3, $retries);
        $stmt->bindValue(4, $statusDescription);
        $stmt->bindValue(5, $id);
        return $stmt->execute();
    }
}
