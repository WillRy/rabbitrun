<?php

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . "/EmailWorker.php";


/**
 * Driver que irá espelhar os itens da fila para consultas de status/situação
 * Pode ser: PDO e MongoDB
 * @var  $driver
 */
$driver = new \WillRy\RabbitRun\Drivers\PdoDriver(
    'mysql',
    'db',
    'env_db',
    'root',
    'root',
    3306
);

/**
 * Driver que irá espelhar os itens da fila para consultas de status/situação
 * Pode ser: PDO e MongoDB
 * @var  $driver
 */
//$driver = new \WillRy\RabbitRun\Drivers\MongoDriver(
//    "mongodb://root:root@mongo:27017/"
//);


/**
 * Monitor[OPCIONAL] que irá conter os status de cada worker, podendo ser iniciado, pausado
 * e indica também qual task está executando no mommento
 * Pode ser: PDO e MongoDB
 * @var $monitor
 */
//$monitor = new \WillRy\RabbitRun\Monitor\RedisMonitor(
//    'workers',
//    'redis'
//);


/**
 * Opcional
 * @var string $consumerName nome do consumer para ser usado no monitor
 */
$consumerName = $_SERVER['argv'][1] ?? null;

$monitor = null;

$worker = (new \WillRy\RabbitRun\Queue\Queue($driver, $monitor))
    ->configRabbit(
        "rabbitmq",
        "5672",
        "admin",
        "admin",
        "/"
    );

$worker
    ->createQueue("queue_teste")
    ->consume(
        new EmailWorker(),
        3,
        $consumerName
    );
