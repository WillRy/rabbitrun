<?php
declare(ticks=1);

namespace WillRy\RabbitRun\Queue;


use Exception;
use PhpAmqpLib\Message\AMQPMessage;
use WillRy\RabbitRun\Base;
use WillRy\RabbitRun\Drivers\DriverAbstract;
use WillRy\RabbitRun\Monitor\Monitor;
use WillRy\RabbitRun\Queue\Interfaces\JobInterface;
use WillRy\RabbitRun\Queue\Interfaces\WorkerInterface;

class Queue extends Base
{
    /** @var string nome da fila */
    protected $queueName;

    /** @var string nome da exchange */
    protected $exchangeName;

    protected $currentID;

    /** @var DriverAbstract */
    public $driver;

    /** @var Monitor */
    public $monitor;

    /** @var string nome do consumer */
    public $consumerName;


    public function __construct(DriverAbstract $driver)
    {

        $this->driver = $driver;

        parent::__construct();
    }

    public function shutdown($signal)
    {
        /** sinaliza que a execução foi finalizada enquanto executava um item */
        if (!empty($this->currentID)) {
            $this->driver->setStatusStopped($this->currentID);
            $this->setMonitorTask(null);
        }

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
     * @param JobInterface $job
     * @return array
     * @throws Exception
     */
    public function publish(
        JobInterface $job
    )
    {

        $id = null;

        try {
            $this->getConnection();

            $payload = [
                "payload" => $job->getPayload(),
                'queue' => $this->queueName,
            ];


            $id = $this->driver->insert(
                $payload,
                $job->getRequeueOnError(),
                $job->getMaxRetries(),
                $job->getAutoDelete(),
                $job->getIdOwner(),
                $job->getIdObject()
            );

            $payload["id"] = $id;

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
            $this->driver->remove($id);
            throw $e;
        }

    }


    /**
     * Loop de consumo de mensagem
     *
     * @param WorkerInterface $worker
     * @param int $sleepSeconds
     * @throws Exception
     */
    public function consume(
        WorkerInterface $worker,
        int             $sleepSeconds = 3,
        string          $consumerName = null,
        ?Monitor        $monitor = null
    )
    {
        if ($sleepSeconds < 1) $sleepSeconds = 1;

        if (!empty($monitor)) {
            $this->monitor = $monitor;
        }

        $this->loopConnection(function () use ($worker, $sleepSeconds, $consumerName) {
            $this->channel->basic_qos(null, 1, null);

            $this->channel->basic_consume(
                $this->queueName,
                $this->randomConsumer(),
                false,
                false,
                false,
                false,
                function (AMQPMessage $message) use ($worker, $consumerName) {
                    print_r("[TASK RECEIVED]" . PHP_EOL);
                    $incomeData = json_decode($message->getBody(), true);

                    $taskID = !empty($incomeData['id']) ? $incomeData['id'] : null;

                    $databaseData = $this->driver->get($taskID);


                    if (empty($databaseData)) {
                        $message->nack();
                        return print_r("[IGNORED - NOT FOUND IN DATABASE]: $taskID" . PHP_EOL);
                    }

                    if ($databaseData["status"] === 'canceled') {
                        (new Task($this->driver, $message, $databaseData))->nackCancel();
                        return print_r("[CANCELED - MANUALLY CANCELED]: $taskID" . PHP_EOL);
                    }

                    if ($databaseData["status"] === "success") {
                        $message->ack();
                        return print_r("[SUCCESSFULLY PROCESSED]: $taskID" . PHP_EOL);
                    }


                    $this->currentID = $taskID;

                    $this->driver->setStatusProcessing($this->currentID);

                    $this->setMonitorTask($this->currentID);


                    try {
                        $worker->handle(new Task($this->driver, $message, $databaseData));

                        $this->setMonitorTask(null);

                        return print_r("[SUCCESS]: $taskID" . PHP_EOL);
                    } catch (Exception $e) {
                        $task = new Task($this->driver, $message, $databaseData);
                        $task->nackError();

                        $worker->error($databaseData, $e);

                        $this->driver->setError($taskID, $e->getMessage());

                        $this->setMonitorTask(null);

                        return print_r("[ERROR]: " . $e->getMessage() . PHP_EOL);
                    }
                }
            );

            // Loop as long as the channel has callbacks registered
            while ($this->channel->is_open()) {
                $this->startMonitor();

                if ($this->monitor && !$this->monitor->workerIsRunning()) {
                    print_r("[WORKER PAUSED]: " . $this->monitor->getItemName() . PHP_EOL);
                    continue;
                }

                $this->channel->wait(null, false);
                sleep($sleepSeconds);
            }


        });

    }

    public function startMonitor()
    {
        if (empty($this->monitor)) return false;

        $this->monitor->startWorker();

        return true;
    }

    public function pauseWorkerMonitor()
    {
        if (empty($this->monitor)) return false;

        $this->monitor->pauseWorker();

        return true;
    }

    public function setMonitorTask($taskID = null)
    {
        if (empty($this->monitor)) return false;

        $this->monitor->setWorkerItem(
            $taskID
        );

        return true;
    }
}
