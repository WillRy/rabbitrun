<?php

require_once __DIR__ . '/../../vendor/autoload.php';

$driver = new \WillRy\RabbitRun\Drivers\PdoDriver(
    'mysql',
    'db',
    'env_db',
    'root',
    'root',
    3306
);

//
//$driver = new \WillRy\RabbitRun\Drivers\MongoDriver(
//    "mongodb://root:root@mongo:27017/"
//);

$worker = (new \WillRy\RabbitRun\Queue\Queue($driver))
    ->configRabbit(
        "rabbitmq", //rabbitmq host
        "5672", //rabbitmq port
        "admin", //rabbitmq user
        "admin", //rabbitmq password
        "/" //rabbitmq vhost
    );

for ($i = 0; $i <= 500000; $i++) {
    $job = new \WillRy\RabbitRun\Queue\Job([
        "id_email" => rand(),
        "conteudo" => "blablabla"
    ]);

    /** optional */
    $job->setRequeueOnError(true);
    $job->setMaxRetries(3);
    $job->setAutoDelete(true);

    $worker
        ->createQueue("queue_teste")
        ->publish($job);
}
