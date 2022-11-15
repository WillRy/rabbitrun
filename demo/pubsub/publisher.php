<?php

require_once __DIR__ . '/../../vendor/autoload.php';

$worker = (new \WillRy\RabbitRun\PubSub\PubSub())
    ->configRabbit(
        "rabbitmq", //rabbitmq host
        "5672", //rabbitmq port
        "admin", //rabbitmq user
        "admin", //rabbitmq password
        "/" //rabbitmq vhost
    );


for ($i = 0; $i <= 10; $i++) {
    $worker
        ->createPubSub("pubsub_teste")
        ->publish(
            [
                "id_email" => rand(),
                "conteudo" => "blablabla"
            ]
        );
    sleep(2);
}
