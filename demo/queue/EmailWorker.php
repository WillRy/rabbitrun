<?php

use PhpAmqpLib\Message\AMQPMessage;

class EmailWorker implements \WillRy\RabbitRun\Queue\Interfaces\WorkerInterface
{

    public function handle(\WillRy\RabbitRun\Queue\Task $data)
    {
        $body = $data->getData();
        $database = $data->getDatabaseData();

        var_dump($data->getPayload());

        /**
         * Fazer o processamento que for necessário
         */

        //simulando um erro qualquer para exemplo
//        $fakeException = rand() % 2 === 0;
//        if ($fakeException) throw new \Exception("=== Erro ===");

        /** Marca o item como sucesso */
        $data->ack();


        /** Marca o item como erro */
        //$data->nackError();

        /** Marca o item como cancelado */
        //$data->nackCancel();
    }


    public function error(array $databaseData, Exception $error = null)
    {

    }
}
