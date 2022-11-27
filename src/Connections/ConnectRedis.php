<?php

namespace WillRy\RabbitRun\Connections;

use Predis\Client;

/**
 * Class Connect Singleton Pattern
 */
class ConnectRedis
{
    /**
     * @const array
     */
    private static $opt = [];

    /** @var Client */
    private static $instanceRedis;

    /**
     * Connect constructor. Private singleton
     */
    private function __construct()
    {
    }

    public static function getInstance()
    {
        if (empty(self::$instanceRedis)) {
            try {
                $redis = new Client([
                    'host' => self::$opt["host"],
                    'port' => self::$opt["port"],
                    'persistent' => 1
                ]);

                self::$instanceRedis = $redis;
            } catch (\Exception $e) {
                self::$instanceRedis = null;
            }
        }
        return self::$instanceRedis;
    }

    public static function config($host, $port = 6379, $persistent = "1")
    {
        self::$opt = [
            'scheme' => "tcp",
            'host' => $host,
            'port' => $port,
            'persistent' => $persistent,
        ];

        return self::getInstance();
    }

    /**
     * Connect clone. Private singleton
     */
    private function __clone()
    {
    }
}
