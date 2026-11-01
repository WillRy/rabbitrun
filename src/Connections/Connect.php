<?php

namespace WillRy\RabbitRun\Connections;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * Class Connect
 * Mantém a conexão e o canal de uma instância (cada instância tem sua própria conexão)
 */
class Connect
{
    /** @var array */
    private $opt;

    /** @var AMQPStreamConnection|null */
    private $instance;

    /** @var AMQPChannel|null */
    private $channel;

    /**
     * @param int $heartbeat intervalo em segundos (0 desativa); deve ser maior que a tarefa mais longa
     */
    public function __construct($host, $port, $user, #[\SensitiveParameter] $pass, $vhost, int $heartbeat = 60)
    {
        $this->opt = [
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'pass' => $pass,
            'vhost' => $vhost,
            'heartbeat' => $heartbeat,
        ];
    }

    public function getInstance(): AMQPStreamConnection
    {
        if (empty($this->instance) || !$this->instance->isConnected()) {
            $heartbeat = $this->opt["heartbeat"];

            $this->instance = new AMQPStreamConnection(
                $this->opt["host"],
                $this->opt["port"],
                $this->opt["user"],
                $this->opt["pass"],
                $this->opt["vhost"] ?? '/',
                false,
                'AMQPLAIN',
                null,
                'en_US',
                3.0,
                // a lib exige read_write_timeout de pelo menos 2x o heartbeat
                max(3.0, $heartbeat * 2),
                null,
                true,
                $heartbeat
            );
        }

        return $this->instance;
    }

    public function getChannel(): AMQPChannel
    {
        if (empty($this->channel) || !$this->channel->is_open()) {
            $this->channel = $this->getInstance()->channel();
        }

        return $this->channel;
    }

    public function closeInstance()
    {
        if (!empty($this->instance) && $this->instance->isConnected()) {
            $this->instance->close();
        }
    }

    public function closeChannel()
    {
        if (!empty($this->channel) && $this->channel->is_open()) {
            $this->channel->close();
        }
    }
}
