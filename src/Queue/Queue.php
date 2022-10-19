<?php
declare(ticks=1);

namespace WillRy\RabbitRun\Queue;


use Exception;
use PhpAmqpLib\Message\AMQPMessage;
use WillRy\RabbitRun\Base;

class Queue extends Base
{
    /** @var string nome da fila */
    protected $queueName;

    /** @var string nome da exchange */
    protected $exchangeName;


    /**
     * Inicializa a fila e exchange, vinculano os 2
     * @param string $name
     * @return $this
     */
    public function createQueue(string $name)
    {
        $this->getConnection();

        $this->queueName = "{$name}";

        $this->exchangeName = "{$name}_exchange";

        $this->exchange($this->exchangeName);

        $this->queue($name);

        $this->bind($name, $this->exchangeName);

        return $this;
    }


    /**
     * Publica mensagem
     *
     * @param array $payload
     * @return array
     * @throws Exception
     */
    public function publish(
        array $payload = [],
        bool  $requeue_on_error = true,
        int   $max_retries = 10,
        bool  $auto_delete_end = false
    )
    {
        $tag = $this->randomTag(30);

        try {
            $this->getConnection();

            $payload = [
                "payload" => $payload,
                'queue' => $this->queueName,
                'tag' => $tag
            ];


            $stmt = $this->db->prepare("INSERT INTO jobs(tag, queue, payload, requeue_error, max_retries, auto_delete_end) VALUES(?,?,?,?,?,?)");
            $stmt->bindValue(1, $payload['tag']);
            $stmt->bindValue(2, $payload['queue']);
            $stmt->bindValue(3, json_encode($payload));
            $stmt->bindValue(4, $requeue_on_error);
            $stmt->bindValue(5, $max_retries);
            $stmt->bindValue(6, $auto_delete_end, \PDO::PARAM_BOOL);
            $stmt->execute();

            $payload["id"] = $this->db->lastInsertId();

            $json = json_encode($payload);

            $message = new AMQPMessage(
                $json,
                array('content_type' => 'text/plain', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT)
            );

            $this->channel->basic_publish(
                $message,
                $this->exchangeName
            );

            return $payload;
        } catch (Exception $e) {
            $stmt = $this->db->prepare("DELETE FROM jobs WHERE tag = ?");
            $stmt->bindValue(1, $tag);
            $stmt->execute();

            throw $e;
        }

    }


    /**
     * Loop de consumo de mensagem
     *
     * @param WorkerInterface $worker
     * @param callable|null $getDatabaseData
     * @throws Exception
     */
    public function consume(
        WorkerInterface $worker,
        int             $sleepSeconds = 3
    )
    {
        if ($sleepSeconds < 1) $sleepSeconds = 1;

        $this->loopConnection(function () use ($worker, $sleepSeconds) {
            $this->channel->basic_qos(null, 1, null);

            $this->channel->basic_consume(
                $this->queueName,
                $this->randomConsumer(),
                false,
                false,
                false,
                false,
                function (AMQPMessage $message) use ($worker) {
                    print_r("[TASK RECEIVED]" . PHP_EOL);
                    $incomeData = json_decode($message->getBody(), true);

                    $taskID = !empty($incomeData['tag']) ? $incomeData['tag'] : null;

                    $stmt = $this->db->prepare("SELECT * FROM jobs WHERE tag = ? limit 1");
                    $stmt->bindValue(1, $taskID, \PDO::PARAM_STR);
                    $stmt->execute();
                    $databaseData = $stmt->fetch(\PDO::FETCH_ASSOC);

                    if (empty($databaseData)) {
                        $message->nack();
                        return print_r("[IGNORED - NOT FOUND IN DATABASE]: $taskID" . PHP_EOL);
                    }

                    if ($databaseData["status"] === 'canceled') {
                        (new Task($message, $databaseData))->nackCancel();
                        return print_r("[CANCELED - MANUALLY CANCELED]: $taskID" . PHP_EOL);
                    }

                    if ($databaseData["status"] === "success") {
                        $message->ack();
                        return print_r("[SUCCESSFULLY PROCESSED]: $taskID" . PHP_EOL);
                    }

                    $stmt = $this->db->prepare("update jobs set start_at = ?, status = ?, end_at = null where tag = ?");
                    $stmt->bindValue(1, date('Y-m-d H:i:s'));
                    $stmt->bindValue(2, "processing");
                    $stmt->bindValue(3, $taskID);
                    $stmt->execute();

                    try {
                        $worker->handle(new Task($message, $databaseData));
                        return print_r("[SUCCESS]: $taskID" . PHP_EOL);
                    } catch (Exception $e) {
                        $task = new Task($message, $databaseData);
                        $task->nackError();

                        $worker->error($databaseData);


                        $stmt = $this->db->prepare("UPDATE jobs SET last_error = ? WHERE tag = ?");
                        $stmt->bindValue(1, $e->getMessage());
                        $stmt->bindValue(2, $taskID);
                        $stmt->execute();

                        return print_r("[ERROR]: " . $e->getMessage() . PHP_EOL);
                    }
                }
            );

            // Loop as long as the channel has callbacks registered
            while ($this->channel->is_open()) {
                $this->channel->wait(null, false);
                sleep($sleepSeconds);
            }


        });

    }
}