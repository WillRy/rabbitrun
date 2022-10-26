<?php

namespace WillRy\RabbitRun;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use WillRy\RabbitRun\Connections\Connect;
use WillRy\RabbitRun\Connections\ConnectPDO;
use WillRy\RabbitRun\Traits\Helpers;

class Base
{

    use Helpers;

    /** @var AMQPStreamConnection Instância de conexão */
    protected $instance;

    /** @var \PhpAmqpLib\Channel\AMQPChannel Canal de comunicação */
    protected $channel;

    /** @var \PDO Conexão do PDO */
    protected $db;


    public function __construct()
    {
        register_shutdown_function(function () {
            $this->cleanConnection();
        });

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
                print "Caught SIGKILL {$data}" . PHP_EOL;;
                exit;
            case SIGINT:
                print "Caught SIGINT {$data}" . PHP_EOL;;
                exit;
        }
    }

    /**
     * Configura o rabbitmq e gera conexão ativa
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
     * Configura o PDO e gera conexão ativa
     * @param $driver
     * @param $host
     * @param $dbname
     * @param $user
     * @param $pass
     * @param $port
     * @return $this
     */
    public function configPDO($driver, $host, $dbname, $user, $pass, $port): Base
    {
        ConnectPDO::config($driver, $host, $dbname, $user, $pass, $port);

        $this->db = ConnectPDO::getInstance();

        return $this;
    }

    /**
     * Gera uma conexão no rabbitmq e gera um canal(opcional)
     * @param bool $createChannel
     * @return mixed|AMQPStreamConnection|void
     */
    public function getConnection($createChannel = true)
    {
        $this->instance = Connect::getInstance();

        if ($createChannel) {
            $this->getChannel();
        }

        return $this->instance;
    }

    /**
     * Retorna canal ativo ou cria um novo
     * @return \PhpAmqpLib\Channel\AMQPChannel|void
     */
    public function getChannel()
    {
        $this->channel = Connect::getChannel();
        return $this->channel;
    }

    public function getConnectionWithChannel()
    {
        $this->getConnection();

        $this->getChannel();

        return $this;
    }

    /**
     * Fecha a conexão com o RabbitMQ
     * @throws \Exception
     */
    function cleanConnection()
    {
        Connect::closeChannel();
        Connect::closeInstance();
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
     * @return $this
     */
    public function queue(
        string $queue = '',
        bool   $passive = false,
        bool   $durable = true,
        bool   $exclusive = false,
        bool   $auto_delete = false
    )
    {
        $this->channel->queue_declare(
            $queue,
            $passive,
            $durable,
            $exclusive,
            $auto_delete,
            false
        );
        return $this;
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

    /**
     * Mantém a conexão, mesmo em caso de erro
     * de rede ou conexão
     * @param $callback
     * @throws \Exception
     */
    public function loopConnection($callback)
    {
        while (true) {
            try {
                $this->getConnection();
                $callback();
            } catch (AMQPRuntimeException $e) {
                echo "RuntimeException :" . $e->getMessage() . PHP_EOL;
                $this->cleanConnection();
                sleep(2);
            } catch (\RuntimeException $e) {
                echo 'Runtime exception ' . $e->getMessage() . PHP_EOL;
                $this->cleanConnection();
                sleep(2);
            } catch (\ErrorException $e) {
                echo 'Error exception ' . $e->getMessage() . PHP_EOL;
                $this->cleanConnection();
                sleep(2);
            } catch (\Exception $e) {
                echo 'Exception ' . $e->getMessage() . PHP_EOL;
                $this->cleanConnection();
                sleep(2);
            }
        }
    }

}
