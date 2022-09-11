<?php
declare(ticks=1);

namespace WillRy\RabbitRun;


use Exception;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Connection\Heartbeat\PCNTLHeartbeatSender;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class Queue
{
    /** @var \PhpAmqpLib\Channel\AMQPChannel Canal de comunicação */
    protected $channel;

    /** @var AMQPStreamConnection Instância de conexão */
    protected $instance;

    /** @var \PDO Conexão do PDO */
    protected $db;

    /** @var string nome da fila */
    protected $queueName;

    /** @var string nome da exchange */
    protected $exchangeName;

    public function __construct()
    {

        register_shutdown_function(function () {
            $this->closeConnection();
        });

        /**
         * Graceful shutdown
         * Faz a execucao parar ao enviar um sinal do linux para matar o script
         */
        if (php_sapi_name() == "cli") {
            \pcntl_signal(SIGTERM, function ($signal) {
                $this->shutdown($signal);
            }, false);
            \pcntl_signal(SIGINT, function ($signal) {
                $this->shutdown($signal);
            }, false);
        }

        return $this;
    }

    public function configRabbit($host, $port, $user, $pass, $vhost)
    {
        Connect::config($host, $port, $user, $pass, $vhost);

        $this->openConnection();

        return $this;
    }

    public function configPDO($driver, $host, $dbname, $user, $pass, $port)
    {
        ConnectPDO::config($driver, $host, $dbname, $user, $pass, $port);

        $this->db = ConnectPDO::getInstance();

        return $this;
    }

    /**
     * Desliga conexões e canais
     *
     * @throws Exception
     */
    public function closeConnection()
    {
        if (!empty($this->instance) && $this->instance->isConnected()) {
            $this->instance->close();
        }

        if (!empty($this->channel) && $this->channel->is_open()) {
            $this->channel->close();
        }


    }

    /**
     * Garante o desligamento correto dos workers
     * @param $signal
     */
    public function shutdown($signal)
    {
        $data = date('Y-m-d H:i:s');
        switch ($signal) {
            case SIGTERM:
                print "Caught SIGTERM {$data}" . PHP_EOL;
                exit;
            case SIGKILL:
                print "Caught SIGKILL {$data}" . PHP_EOL;;
                exit;
            case SIGINT:
                print "Caught SIGINT {$data}" . PHP_EOL;;
                exit;
        }
    }

    /**
     * Inicializa a fila e exchange, vinculano os 2
     * @param string $name
     * @return $this
     */
    public function createQueue(string $name)
    {
        $this->openConnection();

        $this->queueName = "{$name}";

        $this->exchangeName = "{$name}_exchange";

        $this->exchange($this->exchangeName);

        $this->queue($name);

        $this->bind($name, $this->exchangeName);

        return $this;
    }

    /**
     * Cria uma exchange
     *
     * @param string $exchange
     * @param string $type
     * @param bool $passive
     * @param bool $durable
     * @param bool $auto_delete
     * @return $this
     */
    public function exchange(
        string $exchange,
        string $type = "direct",
        bool   $passive = false,
        bool   $durable = true,
        bool   $auto_delete = false
    )
    {
        $this->channel->exchange_declare(
            $exchange,
            $type,
            $passive,
            $durable,
            $auto_delete,
        );
        return $this;
    }

    /**
     * Cria uma fila
     *
     * @param string $queue
     * @param false $passive
     * @param false $durable
     * @param false $exclusive
     * @param bool $auto_delete
     * @return $this
     */
    public function queue(
        string $queue = '',
        bool   $passive = false,
        bool   $durable = true,
        bool   $exclusive = false,
        bool   $auto_delete = false
    )
    {
        $this->channel->queue_declare(
            $queue,
            $passive,
            $durable,
            $exclusive,
            $auto_delete,
            false,
            new AMQPTable(['x-queue-type' => 'quorum'])
        );
        return $this;
    }

    /**
     * Vincula a fila e a exchange criada
     * @return $this
     */
    public function bind(
        $queue,
        $exchange
    )
    {
        $this->channel->queue_bind($queue, $exchange);
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
        int   $max_retries = 10
    )
    {
        $tag = $this->randomTag(30);

        try {
            $this->openConnection();

            $payload = [
                "payload" => $payload,
                'queue' => $this->queueName,
                'tag' => $tag
            ];


            $stmt = $this->db->prepare("INSERT INTO jobs(tag, queue, payload, requeue_error, max_retries) VALUES(?,?,?,?,?)");
            $stmt->bindValue(1, $payload['tag']);
            $stmt->bindValue(2, $payload['queue']);
            $stmt->bindValue(3, json_encode($payload));
            $stmt->bindValue(4, $requeue_on_error);
            $stmt->bindValue(5, $max_retries);
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

    public function randomTag($len = 30): string
    {
        do {
            $pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $string = substr(str_shuffle(str_repeat($pool, (int)ceil($len / strlen($pool)))), 0, $len);

            $stmt = $this->db->prepare("SELECT * FROM jobs WHERE tag = ?");
            $stmt->bindValue(1, $string);
            $stmt->execute();

        } while ($stmt->rowCount() > 0);

        return $string;
    }

    public function randomConsumer($len = 30): string
    {
        $pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        return substr(str_shuffle(str_repeat($pool, (int)ceil($len / strlen($pool)))), 0, $len);
    }

    /**
     * Abre a conexão com o RabbitMQ
     * @return $this
     */
    public function openConnection()
    {
        $this->instance = Connect::getInstance();

        $this->channel = Connect::getChannel();

        return $this;
    }


    /**
     * Fecha a conexão com o RabbitMQ
     * @throws Exception
     */
    function cleanup_connection()
    {
        Connect::closeChannel();
        Connect::closeInstance();
    }


    /**
     * Mantém a conexão, mesmo em caso de erro
     * @param $callback
     * @throws Exception
     */
    public function loopConnection($callback)
    {
        while (true) {
            try {
                $this->openConnection();
                $callback();
            } catch (AMQPRuntimeException $e) {
                echo "RuntimeException :" . $e->getMessage() . PHP_EOL;
                $this->cleanup_connection();
                sleep(2);
            } catch (\RuntimeException $e) {
                echo 'Runtime exception ' . PHP_EOL;
                $this->cleanup_connection();
                sleep(2);
            } catch (\ErrorException $e) {
                echo 'Error exception ' . PHP_EOL;
                $this->cleanup_connection();
                sleep(2);
            } catch (Exception $e) {
                echo 'Exception ' . $e->getMessage() . PHP_EOL;
                $this->cleanup_connection();
                sleep(2);
            }
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
                    print_r("[INCOMING]" . PHP_EOL);
                    $incomeData = json_decode($message->getBody(), true);

                    $taskID = !empty($incomeData['tag']) ? $incomeData['tag'] : null;

                    $stmt = $this->db->prepare("SELECT * FROM jobs WHERE tag = ? limit 1");
                    $stmt->bindValue(1, $taskID, \PDO::PARAM_STR);
                    $stmt->execute();
                    $databaseData = $stmt->fetch(\PDO::FETCH_ASSOC);

                    if (empty($databaseData)) {
                        $message->nack();
                        return print_r("[IGNORED]: $taskID" . PHP_EOL);
                    }

                    if ($databaseData["status"] === 'canceled') {
                        (new Task($message, $databaseData))->nackCancel();
                        return print_r("[CANCELED]: $taskID" . PHP_EOL);
                    }

                    if ($databaseData["status"] === "success") {
                        $message->ack();
                        return print_r("[SUCCESS]: $taskID" . PHP_EOL);
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
