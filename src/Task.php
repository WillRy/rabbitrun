<?php


namespace WillRy\RabbitRun;


use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

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
    public $dataBaseData;

    public function __construct(AMQPMessage $message, array $dataBaseData)
    {
        $this->instance = Connect::getInstance();

        $this->db = ConnectPDO::getInstance();

        $this->message = $message;

        $this->dataBaseData = $dataBaseData;

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

        $this->message->ack();

        $stmt = $this->db->prepare("UPDATE jobs SET end_at = ?, status = ? where tag = ?");
        $stmt->bindValue(1, date('Y-m-d H:i:s'));
        $stmt->bindValue(2, 'success');
        $stmt->bindValue(3, $data['tag']);
        $stmt->execute();
    }

    public function nackCancel()
    {
        $this->nack("canceled");
    }

    public function nackError()
    {
        $this->nack("error");
    }

    /**
     * Marca o item como processado com erro ou cancelado
     */
    public function nack($status = 'error')
    {
        $data = $this->dataBaseData;

        $retries = $data["retries"];
        $maxRetries = $data['max_retries'];

        $requeue = $status === 'error' && $data['requeue_error'];

        // se tem que repor e é infinito as tentativas
        if ($requeue && empty($maxRetries)) {
            $retries++;
            $this->message->nack(true);
        } else if ($requeue && $retries < $maxRetries) {
            $retries++;
            $this->message->nack(true);
        } else {
            $this->message->nack();
        }

        $status = $status === 'error' ? $status : 'canceled';


        $stmt = $this->db->prepare("UPDATE jobs SET end_at = ?, status = ?, retries = ? WHERE id = ?");
        $stmt->bindValue(1, date('Y-m-d H:i:s'));
        $stmt->bindValue(2, $status);
        $stmt->bindValue(3, $retries);
        $stmt->bindValue(4, $data['id']);
        $stmt->execute();

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
     * @return object|null
     */
    public function getPayload(): ?object
    {
        return $this->payload;
    }

}
