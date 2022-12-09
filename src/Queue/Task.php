<?php


namespace WillRy\RabbitRun\Queue;


use PhpAmqpLib\Message\AMQPMessage;
use WillRy\RabbitRun\Drivers\DriverAbstract;

class Task
{

    /** @var string
     * ID que identifica a Task
     */
    public $id;

    /** @var object|null
     * Dados do item da fila
     */
    public $data;

    /** @var AMQPMessage Mensagem */
    protected $message;

    /** @var array Mensagem */
    public $dataBaseData;

    /** @var DriverAbstract */
    public $driver;

    public function __construct(DriverAbstract $driver, AMQPMessage $message, array $dataBaseData)
    {
        $this->driver = $driver;

        $this->message = $message;

        $this->dataBaseData = $dataBaseData;

        $this->hydrate(json_decode($message->getBody(), true));
    }

    /**
     * Insere todos os itens no $data da classe
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        if (empty($this->data)) $this->data = new \stdClass();
        $this->data->$name = $value;
    }

    /**
     * Pega todos os itens no $data da classe
     * @param $name
     * @return null
     */
    public function __get($name)
    {
        if (!empty($this->data->$name)) return $this->data->$name;
        return null;
    }


    /**
     * Popular dados da task na classe
     * @param array $payload
     * @return $this
     */
    public function hydrate(array $payload): Task
    {
        foreach ($payload as $key => $item) {
            $this->$key = $item;
        }

        return $this;
    }

    /**
     * Excluir o item da fila
     */
    public function autoDelete()
    {
        $this->message->nack(false);
    }

    /**
     * Marca o item como processado com sucesso
     */
    public function ack()
    {
        $data = $this->dataBaseData;

        $this->driver->setStatusSuccess($data['id']);

        $this->message->ack();

        if ($data['auto_delete_end']) {
            $this->driver->remove($data['id']);
        }
    }

    public function nackCancel(?string $statusDescription = null)
    {
        $this->nack("canceled", $statusDescription);
    }

    public function nackError()
    {
        $this->nack("error");
    }

    /**
     * Marca o item como processado com erro ou cancelado
     */
    public function nack($status = 'error', ?string $statusDescription = null)
    {
        $data = $this->dataBaseData;

        $retries = $data["retries"];
        $maxRetries = $data['max_retries'];

        $requeue = $status === 'error' && $data['requeue_error'];

        $isAutoDelete = $data['auto_delete_end'];

        if ($requeue && empty($maxRetries)) { // se tem que repor na fila e é infinito as tentativas
            $retries++;
            $this->message->nack(true);
        } else if ($requeue && $retries < $maxRetries) { // se tem que repor na fila e tem numero maximo de tentativas
            $retries++;
            $this->message->nack(true);
        } else { // esgotou numero de tentativas ou não tem que repor na fila
            $this->message->nack();

            if ($isAutoDelete) {
                $this->driver->remove($data['id']);
            }
        }

        $status = $status === 'error' ? $status : 'canceled';

        return $this->driver->updateExecution(
            $data['id'],
            $status,
            $retries,
            $statusDescription
        );

    }

    /**
     * Retorna todos os dados do item na fila
     * @return object|null
     */
    public function getData(): ?object
    {
        return $this->data;
    }

    /**
     * Retorna os dados da tarefa no banco
     * @return array
     */
    public function getDatabaseData(): array
    {
        return $this->dataBaseData;
    }

    /**
     * Retorna o payload de um item na fila
     * @return object|array|null
     */
    public function getPayload(): ?array
    {
        return $this->payload;
    }

}
