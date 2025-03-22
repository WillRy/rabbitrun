<?php

use PhpAmqpLib\Message\AMQPMessage;

require_once __DIR__ . '/../../vendor/autoload.php';

$worker = new \WillRy\RabbitRun\Queue\Queue(
    "rabbitmq",
    "5672",
    "admin",
    "admin",
    "/"
);


/**
 * Executa ao pegar um item na fila
 * Se retornar false, o item é descartado
 *
 * Se não retornar nada ou verdadeiro, o item é processado no método onExecuting
 */
$worker->onReceive(function ($dados) {
    echo ' [x] [  receive  ] ', json_encode($dados), "\n";
});

/**
 * Método que processa o item da fila
 * É sempre necessária dar um destino a mensagem
 *
 * Fazer um $message->ack para marcar como "sucesso"
 * Fazer um $message->nack() para descartar
 * Fazer um $message->nack(true) para repor na fila
 *
 * Se alguma exception não for tratada, o item será recolocado
 * na fila
 */
$worker->onExecuting(function (AMQPMessage $message, $dados) {

    echo ' [x] [ executing ] ', json_encode($dados), "\n";

    sleep(1);

//    $number = rand(0, 10) % 2 === 0;
//    if ($number) throw new \Exception("Error");

    $message->ack();

    echo ' [x] [ success ] ', json_encode($dados), "\n";
});

/**
 * Método que executa automaticamente caso aconteça uma exception não tratada
 * durante o processamento
 */
$worker->onError(function (\Exception $e, $dados) {
    echo ' [x] [   error   ] ', json_encode($dados), "\n";
});


$worker->consume("queue_teste");
