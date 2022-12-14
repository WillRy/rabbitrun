<?php

namespace WillRy\RabbitRun\PubSub;


use Exception;
use PhpAmqpLib\Message\AMQPMessage;
use WillRy\RabbitRun\Traits\Helpers;

class PubSub extends \WillRy\RabbitRun\Base
{
    /** @var string nome da fila */
    protected string $queueName;

    /** @var string nome da exchange */
    protected string $exchangeBaseName;

    protected string $exchangeName;

    public \Closure $onReceiveCallback;

    public \Closure $onExecutingCallback;

    public \Closure $onErrorCallback;

    public \Closure $onCheckStatusCallback;

    public function __construct()
    {
        parent::__construct();

        $this->queueName = $this->randomConsumer(12);
    }

    /**
     * Configura o pubsub criando
     * a exchenge e fila
     * @param string $name
     * @return $this
     */
    public function createPubSubPublisher(string $name): PubSub
    {
        $this->getConnection();

        $this->exchangeName = "{$name}_exchange";

        $this->exchangeBaseName = "{$name}";

        $this->exchange($this->exchangeName, 'fanout', false, false, false);

        return $this;
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

    /**
     * Faz publicacao no pubsub
     * @param array $payload
     * @return array
     */
    public function publish(array $payload = [])
    {
        $json = json_encode($payload);

        $message = new AMQPMessage($json);

        $this->channel->basic_publish(
            $message,
            $this->exchangeName
        );

        return $payload;
    }

    /**
     * Loop de consumo de mensagem
     *
     * @param int $sleepSeconds
     * @throws Exception
     */
    public function consume(
        int $sleepSeconds = 3
    )
    {
        if ($sleepSeconds < 1) $sleepSeconds = 1;

        $this->loopConnection(function () use ($sleepSeconds) {

            /** como no pubsub ao perder a conex??o, a fila exclusiva ?? excluida, ?? necess??rio configurar
             * fila e etc novamente
             */
            $this->createPubSubConsumer($this->exchangeBaseName);

            $this->channel->basic_qos(null, 1, null);

            $this->channel->basic_consume(
                $this->queueName,
                '',
                false,
                true,
                false,
                false,
                function (AMQPMessage $message) {
                    //se o status for negativo, n??o executa o consumo
                    $checkStatusCallback = $this->onCheckStatusCallback;
                    $statusBoolean = $checkStatusCallback();

                    if (!$statusBoolean && isset($statusBoolean)) {
                        print_r("[WORKER STOPPED]" . PHP_EOL);
                        return false;
                    }

                    $incomeData = json_decode($message->getBody(), true);

                    $receiveCallback = $this->onReceiveCallback;
                    $statusBoolean = $receiveCallback($incomeData);

                    if (!$statusBoolean && isset($statusBoolean)) {
                        print_r("[TASK IGNORED BY ON RECEIVE RETURN]" . PHP_EOL);
                        return false;
                    }


                    try {
                        $executingCallback = $this->onExecutingCallback;
                        $executingCallback($message, $incomeData);


                    } catch (Exception $e) {
                        print_r("[ERROR]" . PHP_EOL);

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
