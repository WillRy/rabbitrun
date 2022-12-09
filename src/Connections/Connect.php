<?php

namespace WillRy\RabbitRun\Connections;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * Class Connect Singleton Pattern
 */
class Connect
{
    /**
     * @const array
     */
    private static $opt = [];

    /** @var AMQPStreamConnection */
    private static $instance;

    /** @var AMQPChannel */
    private static $channel;

    /**
     * Connect constructor. Private singleton
     */
    private function __construct()
    {
    }

    /**
     * Connect clone. Private singleton
     */
    private function __clone()
    {
    }

    public static function getInstance()
    {
        if (empty(self::$instance) || (!empty(self::$instance) && !self::$instance->isConnected())) {
            try {
                self::$instance = AMQPStreamConnection::create_connection([
                    [
                        'host' => self::$opt["host"],
                        'port' => self::$opt["port"],
                        'user' => self::$opt["user"],
                        'password' => self::$opt["pass"]
                    ]
                ], [
                    'read_write_timeout' => 30,    // needs to be at least 2x heartbeat
                    'heartbeat' => 15
                ]);
            } catch (\Exception $exception) {
                die('Connection error RabbitMQ' . $exception->getMessage());
            }
        }

        return self::$instance;
    }

    public static function getChannel()
    {
        if (empty(self::$channel) || (!empty(self::$channel) && !self::$channel->is_open())) {
            try {
                self::$channel = self::getInstance()->channel();
            } catch (\Exception $exception) {
                die('Connection error RabbitMQ' . $exception->getMessage());
            }
        }

        return self::$channel;
    }

    public static function closeInstance()
    {
        if (!empty(self::$instance) && self::$instance->isConnected()) {
            try {
                self::$instance->close();
            } catch (\Exception $exception) {
                die('Connection error RabbitMQ' . $exception->getMessage());
            }
        }
    }

    public static function closeChannel()
    {
        if (!empty(self::$channel) && self::$channel->is_open()) {
            try {
                self::$channel->close();
            } catch (\Exception $exception) {
                die('Connection error RabbitMQ' . $exception->getMessage());
            }
        }
    }

    public static function config($host, $port, $user, $pass, $vhost)
    {
        self::$opt = [
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'pass' => $pass,
            'vhost' => $vhost,
        ];
    }

}
