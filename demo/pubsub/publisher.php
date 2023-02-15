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


//$id = rand(0, 9999);
//$payload = [
//    "id" => $id,
//    "id_email" => $id,
//    "conteudo" => "blablabla"
//];
//
//$worker
//    ->createPubSubPublisher("pub_teste")
//    ->publish($payload);
//

for ($i = 0; $i <= 50; $i++) {

    $payload = [
        "id" => $i,
        "id_email" => $i,
        "conteudo" => "blablabla"
    ];

    $worker->publish("pub_teste", $payload);
}
