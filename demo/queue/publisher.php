<?php

require_once __DIR__ . '/../../vendor/autoload.php';


$worker = new \WillRy\RabbitRun\Queue\Queue(
    "rabbitmq",
    "5672",
    "admin",
    "admin",
    "/"
);


for ($i = 0; $i <= 0; $i++) {

    $payload = [
        "id" => $i,
        "id_email" => $i,
        "conteudo" => "blablabla"
    ];

    $worker->publish("queue_teste", $payload);
}
