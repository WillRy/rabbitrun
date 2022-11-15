<?php

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/Process.php';



$worker = (new \WillRy\RabbitRun\PubSub\PubSub())
    ->configRabbit(
        "rabbitmq",
        "5672",
        "admin",
        "admin",
        "/"
    );

$worker
    ->createPubSub("pubsub_teste")
    ->consume(
        new Process()
    );
