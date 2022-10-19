<?php

use PhpAmqpLib\Message\AMQPMessage;

class Process implements \WillRy\RabbitRun\PubSub\WorkerInterface
{

    public function handle(\WillRy\RabbitRun\PubSub\Task $data)
    {
        $body = $data->getData();

        /**
         * Fazer o processamento que for necessário
         */

        //simulando um erro qualquer para exemplo
        $fakeException = rand() % 2 === 0;
        if ($fakeException) throw new \Exception("=== Erro ===");

        echo "Processado".PHP_EOL;


        /** Marca o item como erro */
        //$data->nackError();

        /** Marca o item como cancelado */
        //$data->nackCancel();
    }


    public function error(array $databaseData, Exception $error = null)
    {

    }
}
