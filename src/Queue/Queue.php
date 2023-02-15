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

    protected $currentID;

    /** @var string nome do consumer */
    public $consumerName;

    protected \Closure $onReceiveCallback;

    protected \Closure $onExecutingCallback;

    protected \Closure $onErrorCallback;

    protected \Closure $onCheckStatusCallback;

    public function __construct()
    {
        parent::__construct();
    }

    public function shutdown($signal)
    {
        parent::shutdown($signal);
    }

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
     * @param array $job
     * @return array
     * @throws Exception
     */
    public function publish(
        string $queueName,
        array $job
    )
    {
        try {
            $this->createQueue($queueName);
            
            $this->getConnection();

            $payload = [
                "payload" => $job,
                'queue' => $this->queueName,
            ];

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
            throw $e;
        }
    }


    /**
     * Loop de consumo de mensagem
     *
     * @param int $sleepSeconds
     * @throws Exception
     */
    public function consume(
        string $queueName,
        int    $sleepSeconds = 3
    )
    {
        if ($sleepSeconds < 1) $sleepSeconds = 1;

        if (empty($this->onExecutingCallback)) {
            throw new \Exception("Define a onExecuting callback");
        }

        $this->loopConnection(function () use ($sleepSeconds, $queueName) {

            $this->createQueue($queueName);

            $this->channel->basic_qos(null, 1, null);

            $this->channel->basic_consume(
                $this->queueName,
                $this->randomConsumer(),
                false,
                false,
                false,
                false,
                function (AMQPMessage $message) {
                    //se o status for negativo, não executa o consumo
                    $checkStatusCallback = $this->onCheckStatusCallback;
                    $statusBoolean = $checkStatusCallback();

                    if (!$statusBoolean && isset($statusBoolean)) {
                        print_r("[WORKER STOPPED]" . PHP_EOL);
                        return $message->nack(true);
                    }


                    $incomeData = json_decode($message->getBody(), true);
                    $taskID = !empty($incomeData['id']) ? $incomeData['id'] : null;

                    $this->currentID = $taskID;

                    $receiveCallback = $this->onReceiveCallback;
                    $statusBoolean = $receiveCallback($incomeData);

                    if (!$statusBoolean && isset($statusBoolean)) {
                        print_r("[TASK IGNORED BY ON RECEIVE RETURN]" . PHP_EOL);
                        return $message->nack();
                    }


                    try {
                        $executingCallback = $this->onExecutingCallback;
                        $executingCallback($message, $incomeData);

                    } catch (Exception $e) {
                        $message->nack(true);


                        $errorCallback = $this->onErrorCallback;
                        $errorCallback($e, $incomeData);
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

    public function onCheckStatus(\Closure $callback)
    {
        $this->onCheckStatusCallback = $callback;
    }

    public function onReceive(\Closure $callback)
    {
        $this->onReceiveCallback = $callback;
    }

    public function onExecuting(\Closure $callback)
    {
        $this->onExecutingCallback = $callback;
    }

    public function onError(\Closure $callback)
    {
        $this->onErrorCallback = $callback;
    }
}
