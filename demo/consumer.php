<?php

require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . "/Consumers/EmailWorker.php";


$worker = (new \WillRy\RabbitRun\Queue\Queue())
    ->configRabbit(
        "rabbitmq",
        "5672",
        "admin",
        "admin",
        "/"
    )->configPDO(
        'mysql',
        'db',
        'env_db',
        'root',
        'root',
        3306
    );

$worker
    ->createQueue("queue_teste")
    ->consume(
        new EmailWorker()
    );
