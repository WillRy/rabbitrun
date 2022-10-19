<?php

require_once __DIR__ . '/../../vendor/autoload.php';

$worker = (new \WillRy\RabbitRun\PubSub\PubSub())
    ->configRabbit(
        "rabbitmq", //rabbitmq host
        "5672", //rabbitmq port
        "admin", //rabbitmq user
        "admin", //rabbitmq password
        "/" //rabbitmq vhost
    )->configPDO(
        'mysql', //pdo driver
        'db', //pdo host
        'env_db', //pdo db_name
        'root', //pdo username
        'root', //pdo password
        3306 //pdo port
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
