<?php

namespace WillRy\RabbitRun\PubSub;

use PhpAmqpLib\Message\AMQPMessage;
use WillRy\RabbitRun\PubSub\Task;
use WillRy\RabbitRun\Traits\Helpers;

class PubSub extends \WillRy\RabbitRun\Base
{
    use Helpers;

    /** @var string nome da queue */
    protected $name;

    /** @var string nome da exchange */
    protected $exchangeName;

    public function __construct()
    {
        parent::__construct();

        $this->name = $this->randomConsumer(12);
    }

    /**
     * Configura o pubsub criando
     * a exchenge e fila
     * @param string $name
     * @return $this
     */
    public function createPubSub(string $name): PubSub
    {
        $this->getConnection();

        $this->exchangeName = "{$name}_exchange";

        $this->exchange($this->exchangeName, 'fanout', false, true, true);

        $this->queue($this->name, false, false, true,true);

        $this->bind($this->name, $this->exchangeName);

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

        $message = new AMQPMessage(
            $json,
            array('content_type' => 'text/plain', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_NON_PERSISTENT)
        );

        $this->channel->basic_publish(
            $message,
            $this->exchangeName
        );

        return $payload;
    }

    /**
     * Consome os itens no pubsub
     * @param $worker
     * @throws \Exception
     */
    public function consume(
        WorkerInterface $worker,
        int             $sleepSeconds = 2
    )
    {

        $this->loopConnection(function () use ($worker, $sleepSeconds) {

            /** como no pubsub ao perder a conexão, a fila exclusiva é excluida, é necessário configurar
             * fila e etc novamente
             */
            $this->createPubSub($this->name);

            $this->channel->basic_qos(null, 1, null);

            $this->channel->basic_consume(
                $this->name,
                $this->name,
                false,
                true,
                false,
                false,
                function (AMQPMessage $message) use ($worker) {
                    print_r("[TASK RECEIVED]" . PHP_EOL);
                    $incomeData = json_decode($message->getBody(), true);

                    try {
                        $worker->handle(new Task($message, $incomeData));
                        return print_r("[SUCCESS]" . PHP_EOL);
                    } catch (\Exception $e) {
                        new Task($message, $incomeData);
                        $worker->error($incomeData);

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
