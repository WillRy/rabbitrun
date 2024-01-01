<?php

namespace WillRy\RabbitRun\PubSub;


use Exception;
use PhpAmqpLib\Message\AMQPMessage;
use WillRy\RabbitRun\Base;

class PubSub extends Base
{
    public \Closure $onReceiveCallback;
    
    public \Closure $onExecutingCallback;

    public \Closure $onErrorCallback;

    public \Closure $onCheckStatusCallback;

    /** @var string nome da fila */
    protected string $queueName;
    /** @var string nome da exchange */
    protected string $exchangeBaseName;
    protected string $exchangeName;

    public function __construct($host, $port, $user, $pass, $vhost)
    {
        parent::__construct();

        $this->configRabbit($host, $port, $user, $pass, $vhost);

        $this->queueName = $this->randomConsumer(12);
    }

    /**
     * Faz publicacao no pubsub
     * @param array $payload
     * @return array
     */
    public function publish(string $queueName, array $payload = [])
    {
        $this->createPubSubPublisher($queueName);

        $json = json_encode($payload);

        $message = new AMQPMessage($json);

        $this->channel->basic_publish(
            $message,
            $this->exchangeName
        );

        return $payload;
    }

    /**
     * Configura o pubsub criando
     * a exchenge e fila
     * @param string $name
     * @return $this
     */
    public function createPubSubPublisher(string $name): PubSub
    {
        $this->exchangeName = "{$name}_exchange";

        $this->exchangeBaseName = "{$name}";

        $this->exchange($this->exchangeName, 'fanout', false, false, false);

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
    )
    {

        $this->loopConnection(function () use ($sleepSeconds, $queueName) {

            /** como no pubsub ao perder a conexÃ£o, a fila exclusiva Ã© excluida, Ã© necessÃ¡rio configurar
             * fila e etc novamente
             */
            $this->createPubSubConsumer($queueName);

            $this->channel->basic_qos(null, 1, null);

            $this->channel->basic_consume(
                $this->queueName,
                '',
                false,
                true,
                false,
                false,
                function (AMQPMessage $message) {
                    $statusBoolean = $this->executeStatusCallback($message);

                    if (!$statusBoolean) {
                        return false;
                    }

                    $incomeData = json_decode($message->getBody(), true);

                    $statusBoolean = $this->executeReceiveCallback($message, $incomeData);

                    if (!$statusBoolean) {
                        return false;
                    }


                    try {
                        $executingCallback = $this->onExecutingCallback;
                        $executingCallback($message, $incomeData);
                    } catch (Exception $e) {
                        print_r("[ERROR]" . PHP_EOL);
                        $this->executeErrorCallback($e, $incomeData);
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

    /**
     * Configura o pubsub criando
     * a exchenge e fila
     * @param string $name
     * @return $this
     */
    public function createPubSubConsumer(string $name): PubSub
    {
        $this->getConnection();

        $this->exchangeName = "{$name}_exchange";

        $this->exchangeBaseName = "{$name}";

        $this->exchange($this->exchangeName, 'fanout', false, false, false);

        $defaultQueueName = !empty($this->queueName) ? $this->queueName : '';
        list($queueName, ,) = $this->queue($defaultQueueName, false, false, true, true);

        $this->queueName = $queueName;


        $this->bind($this->queueName, $this->exchangeName);

        return $this;
    }

    public function executeStatusCallback()
    {
        if (empty($this->onCheckStatusCallback)) {
            return true;
        }

        $checkStatusCallback = $this->onCheckStatusCallback;
        $statusBoolean = $checkStatusCallback();

        if (!$statusBoolean && isset($statusBoolean)) {
            print_r("[WORKER STOPPED]" . PHP_EOL);
            return false;
        }

        return true;
    }

    public function executeReceiveCallback($incomeData)
    {
        if (empty($this->onReceiveCallback)) {
            return true;
        }

        $receiveCallback = $this->onReceiveCallback;
        $statusBoolean = $receiveCallback($incomeData);
        if (!$statusBoolean && isset($statusBoolean)) {
            print_r("[TASK IGNORED BY ON RECEIVE RETURN]" . PHP_EOL);
            return false;
        }


        return true;
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