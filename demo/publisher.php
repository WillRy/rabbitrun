<?php

require_once __DIR__ . '/../vendor/autoload.php';

$worker = (new \WillRy\RabbitRun\Queue())
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

for ($i = 0; $i <= 3000; $i++) {
    $worker
        ->createQueue("queue_teste")
        ->publish(
            [
                "id_email" => rand(),
                "conteudo" => "blablabla"
            ],
        );
}
