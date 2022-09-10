<?php

namespace WillRy\RabbitRun;

use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * Class Connect Singleton Pattern
 */
class ConnectPDO
{
    /**
     * @const array
     */
    private static array $opt = [];

    private static \PDO $instance;

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
        if (empty(self::$instance)) {
            try {
                $driver = self::$opt["driver"];
                $host = self::$opt["host"];
                $dbname = self::$opt["dbname"];
                $dbuser = self::$opt["user"];
                $dbpass = self::$opt["pass"];

                $conn = new \PDO("$driver:host=$host;dbname=$dbname", $dbuser, $dbpass);
                $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

                self::$instance = $conn;
            } catch (\Exception $exception) {
                die('Connection error PDO' . $exception->getMessage());
            }
        }

        return self::$instance;
    }

    public static function config($driver, $host, $dbname, $user, $pass, $port)
    {
        self::$opt = [
            'driver' => $driver,
            'host' => $host,
            'dbname' => $dbname,
            'user' => $user,
            'pass' => $pass,
            'port' => $port
        ];
    }

}
