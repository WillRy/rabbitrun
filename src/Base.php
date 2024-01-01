<?php

namespace WillRy\RabbitRun;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPIOException;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use WillRy\RabbitRun\Connections\Connect;

class Base
{


    /** @var AMQPStreamConnection Instância de conexão */
    protected $instance;

    /** @var \PhpAmqpLib\Channel\AMQPChannel Canal de comunicação */
    protected $channel;

    /** @var PDO ConexÃ£o do PDO */
    protected $db;

    /** @var bool */
    protected $executing = false;


    public function __construct()
    {

        /**
         * Graceful shutdown
         * Faz a execucao parar ao enviar um sinal do linux para matar o script
         */
        if (php_sapi_name() == "cli") {
            \pcntl_signal(SIGTERM, function ($signal) {
                $this->shutdown($signal);
            }, false);
            \pcntl_signal(SIGINT, function ($signal) {
                $this->shutdown($signal);
            }, false);
        }

        return $this;
    }

    /**
     * Garante o desligamento correto dos workers
     * via sinal no sistema operacional
     * eliminando loops e conexões
     * @param $signal
     */
    public function shutdown($signal)
    {
        $data = date('Y-m-d H:i:s');
        switch ($signal) {
            case SIGTERM:
                print "Caught SIGTERM {$data}" . PHP_EOL;
                exit;
            case SIGKILL:
                print "Caught SIGKILL {$data}" . PHP_EOL;
                exit;
            case SIGINT:
                print "Caught SIGINT {$data}" . PHP_EOL;
                exit;
        }
    }

    /**
     * Configura o rabbitmq e gera conexÃ£o ativa
     * @param $host
     * @param $port
     * @param $user
     * @param $pass
     * @param $vhost
     * @return $this
     */
    public function configRabbit($host, $port, $user, $pass, $vhost): Base
    {
        Connect::config($host, $port, $user, $pass, $vhost);

        $this->getConnection();

        return $this;
    }

    /**
     * Cria uma exchange
     *
     * @param string $exchange
     * @param string $type
     * @param bool $passive
     * @param bool $durable
     * @param bool $auto_delete
     * @return $this
     */
    public function exchange(
        string $exchange,
        string $type = "direct",
        bool   $passive = false,
        bool   $durable = true,
        bool   $auto_delete = false
    )
    {
        $this->channel->exchange_declare(
            $exchange,
            $type,
            $passive,
            $durable,
            $auto_delete,
        );
        return $this;
    }

    /**
     * Cria uma fila
     *
     * @param string $queue
     * @param false $passive
     * @param false $durable
     * @param false $exclusive
     * @param bool $auto_delete
     * @return array|null
     */
    public function queue(
        string $queue = '',
        bool   $passive = false,
        bool   $durable = true,
        bool   $exclusive = false,
        bool   $auto_delete = false
    )
    {
        return $this->channel->queue_declare(
            $queue,
            $passive,
            $durable,
            $exclusive,
            $auto_delete,
            false
        );
    }

    /**
     * Vincula a fila e a exchange criada
     * @return $this
     */
    public function bind(
        $queue,
        $exchange
    )
    {
        $this->channel->queue_bind($queue, $exchange);
        return $this;
    }

    public function randomConsumer($len = 30): string
    {
        $pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        return substr(str_shuffle(str_repeat($pool, (int)ceil($len / strlen($pool)))), 0, $len);
    }

    /**
     * MantÃ©m a conexÃ£o, mesmo em caso de erro
     * de rede ou conexÃ£o
     * @param $callback
     * @throws Exception
     */
    public function loopConnection($callback)
    {
        while (true) {
            try {
                $this->getConnection(true);
                $callback();
            } catch (\Exception $e) {
                echo get_class($e) . ':' . $e->getMessage() . " | file:" . $e->getFile() . " | line:" . $e->getLine() . PHP_EOL;
                $this->cleanConnection();
                sleep(2);
            }
        }
    }

    /**
     * Gera uma conexÃ£o no rabbitmq e gera um canal(opcional)
     * @param bool $createChannel
     * @return AMQPStreamConnection|void
     */
    public function getConnection(bool $createChannel = true)
    {
        $this->instance = Connect::getInstance();

        if ($createChannel) {
            $this->getChannel();
        }

        return $this->instance;
    }

    /**
     * Retorna canal ativo ou cria um novo
     * @return AMQPChannel|void
     */
    public function getChannel()
    {
        $this->channel = Connect::getChannel();
        return $this->channel;
    }

    /**
     * Fecha a conexÃ£o com o RabbitMQ
     * @throws Exception
     */
    public function cleanConnection()
    {
        try {
            Connect::closeChannel();
            Connect::closeInstance();
        } catch (\Exception $e) {
            echo '[ERROR CLOSE CHANNEL|INSTANCE]' . $e->getMessage() . "|file:" . $e->getFile() . "|line:" . $e->getLine() . PHP_EOL;
        }
    }
}