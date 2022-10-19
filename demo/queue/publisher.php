<?php

require_once __DIR__ . '/../../vendor/autoload.php';

$worker = (new \WillRy\RabbitRun\Queue\Queue())
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

$requeue_on_error = true;
$max_retries = 3;
$auto_delete = true;

for ($i = 0; $i <= 9; $i++) {
    $worker
        ->createQueue("queue_teste")
        ->publish(
            [
                "id_email" => rand(),
                "conteudo" => "blablabla"
            ],
            $requeue_on_error,
            $max_retries,
            $auto_delete
        );
}
