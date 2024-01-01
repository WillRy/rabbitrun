<?php

declare(ticks=1);

namespace WillRy\RabbitRun\Queue;


use Exception;
use PhpAmqpLib\Message\AMQPMessage;
use WillRy\RabbitRun\Base;

class Queue extends Base
{
    /** @var string nome do consumer */
    public $consumerName;
    
    /** @var string nome da fila */
    protected $queueName;
    
    /** @var string nome da exchange */
    protected $exchangeName;

    protected \Closure $onReceiveCallback;

    protected \Closure $onExecutingCallback;

    protected \Closure $onErrorCallback;

    protected \Closure $onCheckStatusCallback;

    public function __construct($host, $port, $user, $pass, $vhost)
    {
        parent::__construct();

        $this->configRabbit($host, $port, $user, $pass, $vhost);
    }

    public function shutdown($signal)
    {
        parent::shutdown($signal);
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
        array  $job
    ) {
        try {
            $this->createQueue($queueName);


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
     * Inicializa a fila e exchange, vinculano os 2
     * @param string $name
     * @return $this
     */
    public function createQueue(string $name)
    {

        $this->queueName = "{$name}";

        $this->exchangeName = "{$name}_exchange";

        $this->exchange($this->exchangeName);

        $this->queue($name);

        $this->bind($name, $this->exchangeName);

        return $this;
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
    ) {
        $this->validateExecuteCallback();


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
                    pcntl_sigprocmask(SIG_BLOCK, [SIGTERM, SIGINT]);

                    $incomeData = json_decode($message->getBody(), true);

                    //se o status for negativo, nÃ£o executa o consumo
                    $statusBoolean = $this->executeStatusCallback($message);

                    if (!$statusBoolean) {
                        return false;
                    }

                    $receiveBoolean = $this->executeReceiveCallback($message, $incomeData);

                    if (!$receiveBoolean) {
                        return false;
                    }

                    try {
                        $this->executeMessage($message, $incomeData);
                    } catch (Exception $e) {
                        $message->nack(true);
                        $this->executeErrorCallback($e, $incomeData);
                    }

                    pcntl_sigprocmask(SIG_UNBLOCK, [SIGTERM, SIGINT]);
                }
            );

            // Loop as long as the channel has callbacks registered
            while ($this->channel->is_open()) {
                $this->channel->wait(null, false);
                sleep($sleepSeconds);

                // Despachar eventos de "finalizar" script
                pcntl_signal_dispatch();
            }
        });
    }

    public function validateExecuteCallback()
    {
        if (!empty($this->onExecutingCallback)) {
            return true;
        }

        throw new Exception("Define a onExecuting callback");
    }

    public function executeStatusCallback(AMQPMessage $message)
    {
        if (empty($this->onCheckStatusCallback)) {
            return true;
        }

        $checkStatusCallback = $this->onCheckStatusCallback;
        $statusBoolean = $checkStatusCallback();

        if (!$statusBoolean && isset($statusBoolean)) {
            print_r("[WORKER STOPPED]" . PHP_EOL);
            $message->nack(true);
            pcntl_sigprocmask(SIG_UNBLOCK, [SIGTERM, SIGINT]);
            return false;
        }

        return true;
    }

    public function executeReceiveCallback(AMQPMessage $message, $incomeData)
    {
        if (empty($this->onReceiveCallback)) {
            return true;
        }

        $receiveCallback = $this->onReceiveCallback;
        $statusBoolean = $receiveCallback($incomeData);
        if (!$statusBoolean && isset($statusBoolean)) {
            print_r("[TASK IGNORED BY ON RECEIVE RETURN]" . PHP_EOL);
            $message->nack();
            pcntl_sigprocmask(SIG_UNBLOCK, [SIGTERM, SIGINT]);
            return false;
        }


        return true;
    }

    public function executeMessage(AMQPMessage $message, $incomeData)
    {
        $onExecutingCallback = $this->onExecutingCallback;
        $onExecutingCallback($message, $incomeData);
    }

    public function executeErrorCallback(Exception $e, $incomeData)
    {
        if (empty($this->onErrorCallback)) {
            return false;
        }

        $errorCallback = $this->onErrorCallback;
        $errorCallback($e, $incomeData);
        return true;
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
