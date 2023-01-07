<?php

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . "/PdoDriver.php";

$worker = (new \WillRy\RabbitRun\Queue\Queue())
    ->configRabbit(
        "rabbitmq", //rabbitmq host
        "5672", //rabbitmq port
        "admin", //rabbitmq user
        "admin", //rabbitmq password
        "/" //rabbitmq vhost
    );

$model = new PdoDriver();

for ($i = 0; $i <= 3; $i++) {

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

    $worker
        ->createQueue("queue_teste")
        ->publish($payload);
}
