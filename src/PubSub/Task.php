<?php


namespace WillRy\RabbitRun\PubSub;


use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use WillRy\RabbitRun\Connections\Connect;
use WillRy\RabbitRun\Connections\ConnectPDO;

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

    /** @var AMQPStreamConnection
     * Instancia com a conexão
     */
    protected $instance;

    protected $db;

    /** @var AMQPMessage Mensagem */
    protected $message;

    /** @var array Mensagem */
    public $pubData;

    public function __construct(AMQPMessage $message, array $pubData)
    {
        $this->instance = Connect::getInstance();

        $this->db = ConnectPDO::getInstance();

        $this->message = $message;

        $this->pubData = $pubData;

        $this->hydrate(json_decode($message->getBody(), true));

//        if($cancel) $this->nack(false);
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
        return $this->pubData;
    }

    /**
     * Retorna o payload de um item na fila
     * @return object|null
     */
    public function getPayload(): ?object
    {
        return $this->payload;
    }

}
