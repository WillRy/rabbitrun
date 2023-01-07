<?php

use PhpAmqpLib\Message\AMQPMessage;

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . "/EmailWorker.php";

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

    $model = new PdoDriver();

    $existeNoBanco = $model->getTask($id);

    //se não existir no banco, ignora
    if (empty($existeNoBanco)) {
        print_r("Não existe no banco" . PHP_EOL);
        return false;
    }

    $aindaTemRetry = $model->hasRetry($id);
    if (empty($aindaTemRetry)) {
        print_r("Não tem retry" . PHP_EOL);
        return false;
    }

    $model = new PdoMonitor();
    $model->setWorkerItem($id);

    var_dump(['recebidos' => $id]);
});
$worker->onExecuting(function (AMQPMessage $message, $dados) {
    $id = $dados['payload']['id'];
    var_dump(['processando' => $id]);

    $number = rand(0, 10) % 2 === 0;
    if ($number) throw new \Exception("Error");

    $message->ack();
});
$worker->onError(function (\Exception $e, $dados) {
    $id = $dados['payload']['id'];

    $model = new PdoDriver();
    $model->sumRetry($id);

    $time = time();

    var_dump(["erro $time" => $id]);
});


$worker
    ->createQueue("queue_teste")
    ->consume();
