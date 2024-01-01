<?php

require_once __DIR__ . '/../../vendor/autoload.php';

$worker = new \WillRy\RabbitRun\PubSub\PubSub(
    "rabbitmq",
    "5672",
    "admin",
    "admin",
    "/"
);


for ($i = 0; $i <= 50; $i++) {

    $payload = [
        "id" => $i,
        "id_email" => $i,
        "conteudo" => "blablabla"
    ];

    $worker->publish("pub_teste", $payload);
}
