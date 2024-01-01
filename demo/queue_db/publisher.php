<?php

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . "/PdoDriver.php";

$worker = new \WillRy\RabbitRun\Queue\Queue(
    "rabbitmq",
    "5672",
    "admin",
    "admin",
    "/"
);

$model = new PdoDriver();

for ($i = 0; $i <= 800; $i++) {

    $payload = [
        "id_email" => $i,
        "conteudo" => "blablabla"
    ];

    $id = $model->insert(
        'queue_teste',
        $payload,
        true,
        3,
        true
    );

    $payload['id'] = $id;

    $worker->publish("queue_teste", $payload);
}
