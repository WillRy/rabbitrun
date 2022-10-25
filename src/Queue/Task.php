<?php


namespace WillRy\RabbitRun\Queue;


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

        if ($data['auto_delete_end']) {
            $stmt = $this->db->prepare("DELETE FROM ".Queue::$table." WHERE id = ?");
            $stmt->bindValue(1, $data['id']);
            return $stmt->execute();
        }

        $stmt = $this->db->prepare("UPDATE ".Queue::$table." SET end_at = ?, status = ? where id = ?");
        $stmt->bindValue(1, date('Y-m-d H:i:s'));
        $stmt->bindValue(2, 'success');
        $stmt->bindValue(3, $data['id']);
        return $stmt->execute();
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
                $stmt = $this->db->prepare("DELETE FROM ".Queue::$table." WHERE id = ?");
                $stmt->bindValue(1, $data['id']);
                return $stmt->execute();
            }
        }

        $status = $status === 'error' ? $status : 'canceled';

        $stmt = $this->db->prepare("UPDATE ".Queue::$table." SET end_at = ?, status = ?, retries = ?, status_desc = ? WHERE id = ?");
        $stmt->bindValue(1, date('Y-m-d H:i:s'));
        $stmt->bindValue(2, $status);
        $stmt->bindValue(3, $retries);
        $stmt->bindValue(4, $statusDescription);
        $stmt->bindValue(5, $data['id']);
        return $stmt->execute();

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
    public function getPayload(): ?array
    {
        return $this->payload;
    }

}
