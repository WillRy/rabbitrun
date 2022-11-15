<?php

namespace WillRy\RabbitRun\Connections;

use MongoDB\Client;

/**
 * Class Connect Singleton Pattern
 */
class ConnectMongo
{
    /**
     * @const array
     */
    private static $opt = [];

    /**
     * @var Client
     */

    private static $instance;

    /**
     * Connect constructor. Private singleton
     */
    private function __construct()
    {
    }

    public static function getInstance(): ?Client
    {
        if (empty(self::$instance)) {
            self::$instance = new Client(self::$opt["uri"]);
        }

        return self::$instance;
    }

//"mongodb://{$user}:{$pass}@{$host}:$port/"
    public static function config($uri, $port = 27017)
    {
        self::$opt = [
            'scheme' => "tcp",
            'uri' => $uri
        ];

        return ConnectMongo::getInstance();
    }

    /**
     * Connect clone. Private singleton
     */
    private function __clone()
    {
    }
}
