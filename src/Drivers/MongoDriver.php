<?php

namespace WillRy\RabbitRun\Drivers;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class MongoDriver extends DriverAbstract
{
    protected $db;

    protected static $database;

    public function __construct(
        $url,
        $datanase = "queue",
        $collection = "jobs"
    )
    {


//        "mongodb://{$user}:{$pass}@{$host}:$port/"
        $connection = \WillRy\RabbitRun\Connections\ConnectMongo::config(
            $url
        );

        static::$entity = $collection;

        static::$database = $collection;

        $this->db = $connection->{static::$database}->{$collection};


    }

    /**
     * Define um item como "parado"
     * @param int $jobID
     * @return array|object|null
     */
    public function setStatusStopped($jobID)
    {
        return $this->db->findOneAndUpdate(
            [
                '_id' => new ObjectId($jobID)
            ],
            [
                '$set' => [
                    'status' => 'stopped'
                ]
            ],
        );
    }

    /**
     * Insere um item no banco
     * @param array $payload
     * @param bool $requeue_on_error
     * @param int $max_retries
     * @param bool $auto_delete_end
     * @param int|null $id_owner
     * @param int|null $id_object
     */
    public function insert(
        array $payload,
        bool  $requeue_on_error = true,
        int   $max_retries = 10,
        bool  $auto_delete_end = false,
        int   $id_owner = null,
        int   $id_object = null
    )
    {
        $item = $this->db->insertOne(
            [
                'queue' => $payload['queue'],
                'status' => 'waiting',
                'retries' => 0,
                'payload' => json_encode($payload),
                'requeue_error' => $requeue_on_error,
                'max_retries' => $max_retries,
                'auto_delete_end' => $auto_delete_end,
                'id_owner' => $id_owner,
                'id_object' => $id_object,
            ],
        );

        return (string)$item->getInsertedId();
    }

    /**
     * Remove um item no banco
     * @param int|null $id
     */
    public function remove($id)
    {
        if ($id) {
            $this->db->deleteOne(
                [
                    '_id' => new ObjectId($id)
                ]
            );
        }
    }

    /**
     * Retorna um item do banco
     * @param int|null|string $id
     * @return mixed
     */
    public function get($id)
    {
        $item = $this->db->findOne(
            [
                '_id' => new ObjectId($id)
            ]
        );
        $dados = (array)$item->getArrayCopy();
        $dados['id'] = $dados['_id'];
        return $dados;
    }

    /**
     * Define um item como "processando"
     * @param int $id
     */
    public function setStatusProcessing($id)
    {
        $this->db->updateOne(
            [
                '_id' => new ObjectId($id)
            ],
            [
                '$set' => [
                    'start_at' => $this->returnMongoDate('now'),
                    'status' => 'processing',
                    'end_at' => null,
                ]
            ],
        );
    }

    /**
     * Salva erro no item
     * @param int $id
     * @param string $msg
     */
    public function setError($id, string $msg)
    {
        $this->db->findOneAndUpdate(
            [
                '_id' => new ObjectId($id)
            ],
            [
                '$set' => [
                    'last_error' => $msg,
                ]
            ],
        );
    }

    /**
     * Marca o item como "sucesso"
     * @param int $id
     * @return bool
     */
    public function setStatusSuccess($id)
    {
        $res = $this->db->updateOne(
            [
                '_id' => new ObjectId($id)
            ],
            [
                '$set' => [
                    'end_at' => $this->returnMongoDate('now'),
                    'status' => 'success',
                ]
            ],
        );
        return $res->isAcknowledged();
    }

    public function updateExecution(
        $id,
        string $status,
        ?int $retries,
        ?string $statusDescription
    )
    {

        $this->db->updateOne(
            [
                '_id' => new ObjectId($id)
            ],
            [
                '$set' => [
                    'end_at' => $this->returnMongoDate('now'),
                    'status' => $status,
                    'retries' => $retries,
                    'status_desc' => $statusDescription,
                ]
            ],
        );
    }

    public function returnMongoDate($dateString)
    {
        $time = (new \DateTime($dateString))
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                ->getTimestamp() * 1000;
        return new UTCDateTime($time);
    }
}
