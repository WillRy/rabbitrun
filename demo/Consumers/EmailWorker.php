<?php

use PhpAmqpLib\Message\AMQPMessage;

class EmailWorker implements \WillRy\RabbitRun\WorkerInterface
{

    public function handle(\WillRy\RabbitRun\Task $data)
    {
        $body = $data->getData();
        $database = $data->getDatabaseData();

        var_dump($body, $database);

        $fakeException = rand() % 2 === 0;
//        $fakeException = true;
        if ($fakeException) throw new \Exception("=== Erro ===");


//            print("Sucesso:{$body->id_email}".PHP_EOL);

        $data->ack();
    }


    public function error(array $data, Exception $error = null)
    {
        print_r("=== Erro ===" . PHP_EOL);
    }
}
