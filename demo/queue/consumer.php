<?php

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . "/EmailWorker.php";


$driver = new \WillRy\RabbitRun\Drivers\PdoDriver(
    'mysql',
    'db',
    'env_db',
    'root',
    'root',
    3306
);

//$driver = new \WillRy\RabbitRun\Drivers\MongoDriver(
//    "mongodb://root:root@mongo:27017/"
//);

$worker = (new \WillRy\RabbitRun\Queue\Queue($driver))
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
        new EmailWorker()
    );
