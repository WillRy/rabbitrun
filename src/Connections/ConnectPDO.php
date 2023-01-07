<?php

namespace WillRy\RabbitRun\Connections;

/**
 * Class Connect Singleton Pattern
 */
class ConnectPDO
{
    /**  @var array */
    private static $opt = [];

    /** @var \PDO */
    private static $instance;

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

    public static function getInstance($forceNew = false)
    {
        if (empty(self::$instance) || $forceNew) {
            try {
                $driver = self::$opt["driver"];
                $host = self::$opt["host"];
                $dbname = self::$opt["dbname"];
                $dbuser = self::$opt["user"];
                $dbpass = self::$opt["pass"];
                $port = self::$opt["port"];

                $conn = new \PDO("$driver:host=$host;dbname=$dbname;port=$port", $dbuser, $dbpass);
                $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $conn->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, false);
                $conn->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);

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

        return ConnectPDO::getInstance();
    }

}
