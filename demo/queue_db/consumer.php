<?php

use PhpAmqpLib\Message\AMQPMessage;

require_once __DIR__ . '/../../vendor/autoload.php';


require_once __DIR__ . "/PdoDriver.php";
require_once __DIR__ . "/PdoMonitor.php";


$worker = (new \WillRy\RabbitRun\Queue\Queue())
    ->configRabbit(
        "rabbitmq",
        "5672",
        "admin",
        "admin",
        "/"
    );

$consumerName = $argv[1] ?? rand(1, 99999);

$model = new PdoMonitor("rabbit_monitor", $consumerName, 'servicos');
$model->insertConsumerIfNotExists();

$worker->onCheckStatus(function () use ($consumerName) {
    $model = new PdoMonitor("rabbit_monitor", $consumerName, 'servicos');
    return $model->workerIsRunning();
});

$worker->onReceive(function ($dados) {
    $id = $dados['payload']['id'];
    echo ' [x] [  receive  ] ', $id, "\n";


    $model = new PdoDriver();

    $existeNoBanco = $model->getTask($id);

    //se não existir no banco, ignora
    if (empty($existeNoBanco)) {
        echo ' [x] [  não existe no banco  ] ', $id, "\n";
        return false;
    }

    $aindaTemRetry = $model->hasRetry($id);
    if (empty($aindaTemRetry)) {
        echo ' [x] [  esgotou o retry  ] ', $id, "\n";
        return false;
    }

    return true;
});
$worker->onExecuting(function (AMQPMessage $message, $dados) {
    $payload = $dados['payload'];

    echo ' [x] [  executing  ] ', $payload['id'], "\n";

    $message->ack();

    $model = new PdoDriver();
    $model->checkDelete($payload['id']);

    echo ' [x] [  success  ] ', $payload['id'], "\n";
});
$worker->onError(function (\Exception $e, $dados) {
    $payload = $dados['payload'];
    $id = $payload['id'];

    echo ' [x] [  error  ] ', $payload['id'], $e->getMessage(), "\n";

    $model = new PdoDriver();
    $model->sumRetry($id);
    $model->setError($id, $e->getMessage());
});


$worker->consume("queue_teste");
