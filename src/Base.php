<?php

namespace WillRy\RabbitRun;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use WillRy\RabbitRun\Connections\Connect;

class Base
{


    /** @var Connect conexão própria desta instância */
    protected $connect;

    /** @var AMQPStreamConnection Instância de conexão */
    protected $instance;

    /** @var \PhpAmqpLib\Channel\AMQPChannel Canal de comunicação */
    protected $channel;

    /** @var int quantidade de instâncias consumindo no processo */
    protected static $consumers = 0;

    /** @var bool sinal de parada recebido (vale para todas as instâncias do processo) */
    protected static $stopping = false;

    /** @var bool handlers de sinal já registrados no processo */
    private static $signalsRegistered = false;

    protected $confirmSelect = false;

    /** @var float timeout (segundos) para aguardar confirmação do broker */
    protected $confirmTimeout = 5.0;

    /** @var \PhpAmqpLib\Channel\AMQPChannel|null canal que já está em modo confirm */
    protected $confirmedChannel;


    public function __construct()
    {

        /**
         * Graceful shutdown
         * Faz a execucao parar ao enviar um sinal do linux para matar o script
         * Registrado uma única vez, pois o pcntl_signal substitui o handler anterior
         */
        if (php_sapi_name() == "cli" && function_exists('pcntl_async_signals') && !self::$signalsRegistered) {
            self::$signalsRegistered = true;

            // executa os handlers assim que o sinal chega, sem depender de ticks
            \pcntl_async_signals(true);

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
        $name = $signal === SIGTERM ? 'SIGTERM' : 'SIGINT';
        print "Caught {$name} {$data}" . PHP_EOL;

        // nenhuma instância consumindo (ex: script publisher), encerra imediatamente como antes
        if (self::$consumers === 0) {
            exit;
        }

        // consumindo: termina a mensagem atual, fecha as conexões e sai dos loops
        self::$stopping = true;
    }

    /**
     * Configura o rabbitmq e gera conexão ativa
     * @param $host
     * @param $port
     * @param $user
     * @param $pass
     * @param $vhost
     * @param int $heartbeat intervalo em segundos (0 desativa); deve ser maior que a tarefa mais longa
     * @return $this
     */
    public function configRabbit($host, $port, $user, #[\SensitiveParameter] $pass, $vhost, int $heartbeat = 60): Base
    {
        $this->connect = new Connect($host, $port, $user, $pass, $vhost, $heartbeat);

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
     * @param array $arguments argumentos da fila (x-queue-type, x-delivery-limit e etc)
     * @return array|null
     */
    public function queue(
        string $queue = '',
        bool   $passive = false,
        bool   $durable = true,
        bool   $exclusive = false,
        bool   $auto_delete = false,
        array  $arguments = []
    )
    {
        return $this->channel->queue_declare(
            $queue,
            $passive,
            $durable,
            $exclusive,
            $auto_delete,
            false,
            new AMQPTable($arguments)
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
     * Mantém a conexão, mesmo em caso de erro
     * de rede ou conexão
     * @param $callback
     * @throws Exception
     */
    public function loopConnection($callback)
    {
        while (!self::$stopping) {
            try {
                $this->getConnection(true);
                echo ' [x] [  connected  ] ', "\n";
                $callback();
            } catch (\Exception $e) {
                echo get_class($e) . ':' . $e->getMessage() . " | file:" . $e->getFile() . " | line:" . $e->getLine() . PHP_EOL;
                $this->cleanConnection();
                sleep(2);
            }
        }

        $this->cleanConnection();
    }

    /**
     * Gera uma conexão no rabbitmq e gera um canal(opcional)
     * @param bool $createChannel
     * @return AMQPStreamConnection|void
     */
    public function getConnection(bool $createChannel = true)
    {
        $this->instance = $this->connect->getInstance();

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
        $this->channel = $this->connect->getChannel();

        $this->enableConfirmSelect();

        return $this->channel;
    }

    /**
     * Coloca o canal atual em modo confirm uma única vez
     * (confirm_select reinicia o contador de delivery tags)
     */
    protected function enableConfirmSelect()
    {
        if (!$this->confirmSelect || $this->channel === $this->confirmedChannel) {
            return;
        }

        $this->channel->set_nack_handler(function (AMQPMessage $message) {
            throw new AMQPRuntimeException("Mensagem rejeitada pelo broker (nack)");
        });
        $this->channel->confirm_select();

        $this->confirmedChannel = $this->channel;
    }

    /**
     * Fecha a conexão com o RabbitMQ
     * @throws Exception
     */
    public function cleanConnection()
    {
        try {
            $this->connect->closeChannel();
            $this->connect->closeInstance();
        } catch (\Exception $e) {
            echo '[ERROR CLOSE CHANNEL|INSTANCE]' . $e->getMessage() . "|file:" . $e->getFile() . "|line:" . $e->getLine() . PHP_EOL;
        }
    }

    public function configConfirmSelect(bool $confirmSelect = true, float $timeout = 5.0)
    {
        $this->confirmSelect = $confirmSelect;
        $this->confirmTimeout = $timeout;

        if ($this->channel) {
            $this->enableConfirmSelect();
        }

        return $this;
    }
}