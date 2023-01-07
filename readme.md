# RabbitRun - Mecanismo de fila

Mecanismo de fila utilizando a combinação do RabbitMQ e MySQL

- O RabbitMQ para entrega e distribuição da tarefa entre os workers
- O MySQL para conter detalhes sobre quais tarefas foram executadas e também quais devem ser ignoradas na fila

Combinando o MySQL junto ao RabbitMQ, é possível pesquisar e excluir/ignorar itens da fila de forma simples, o que não é
possível somente com o RabbitMQ, devido as limitações dele em procurar itens dentro da fila

**Workers**: É possível ter vários workers consumindo a fila ao mesmo tempo, pois o rabbit mq trata a distribuição de
tarefa entre eles.

## Requisitos

- MySQL
- PHP >= 7.3 com PDO
- RabbitMQ >= 3.8 (Filas do tipo Quorum são necessárias)

## Demonstração

**3k de mensagens**

![Painel administrativo](./midia/queue-01.png)

**Consumo de hardware baixo com 3k de mensagens e 4 workers**

![Uso de hardware](./midia/queue-02.png)

## Criar tabela de background jobs

Ao usar o PDO como driver de espelhamento de fila, deve ser criado as tabelas.

Caso use o Driver do MongoDB, não será necessário criar as collections, pois elas são
criadas automaticamente

```sql
drop table if exists jobs;

create table jobs
(
    id              bigint auto_increment primary key,
    queue           varchar(255) not NULL COMMENT 'queue name',
    payload         text         not NULL COMMENT 'json content, filter with JSON_EXTRACT',
    retries         int          not null default 0,
    max_retries     int          not null default 10,
    requeue_error   boolean               default true,
    last_error      text COMMENT 'last error description',
    status_desc     text COMMENT 'cancel/other status reason',
    id_owner        bigint COMMENT 'ID to determine the owner (user) of the item in the queue',
    id_object       bigint COMMENT 'ID to determine which object the queue item came from (ex: user, product and etc)r',
    auto_delete_end boolean               default false,
    status          enum('waiting','processing','canceled','error','success') default 'waiting',
    start_at        datetime,
    end_at          datetime,
    INDEX           idx_id_object (id_object),
    INDEX           idx_queue (queue),
    INDEX           idx_id_owner (id_owner)
);

drop table if exists rabbit_monitor;

create table rabbit_monitor
(
    id         bigint primary key auto_increment,
    name       varchar(255) not null,
    status     int          not null default 0,
    jobID      varchar(255) null,
    groupName  varchar(255) not null,
    modifiedAt datetime null
);

```

## Como publicar itens na fila

```php
<?php

require_once __DIR__ . '/../../vendor/autoload.php';


$worker = (new \WillRy\RabbitRun\Queue\Queue())
    ->configRabbit(
        "rabbitmq", //rabbitmq host
        "5672", //rabbitmq port
        "admin", //rabbitmq user
        "admin", //rabbitmq password
        "/" //rabbitmq vhost
    );


for ($i = 0; $i <= 3; $i++) {

    $payload = [
        "id" => $i,
        "id_email" => $i,
        "conteudo" => "blablabla"
    ];

    $worker
        ->createQueue("queue_teste")
        ->publish($payload);
}

```

## Como consumir itens da fila?

- Criar a classe de worker responsavel pelo processament **(Implementar a interface WorkerInterface)**

```php
<?php

use PhpAmqpLib\Message\AMQPMessage;

require_once __DIR__ . '/../../vendor/autoload.php';

$worker = (new \WillRy\RabbitRun\Queue\Queue())
    ->configRabbit(
        "rabbitmq",
        "5672",
        "admin",
        "admin",
        "/"
    );


/**
 * Executa quando o worker pega uma tarefa
 *
 * Retorna verdadeiro para o worker executar
 * Retorna false para o worker ficar devolvendo os itens para a fila
 *
 * Utilidade: Dizer se o worker está ativo, com base em algum registro de banco de dados, monitor de serviços
 * e etc
 */
$worker->onCheckStatus(function () {

});

/**
 * Executa ao pegar um item na fila
 * Se retornar false, o item é descartado
 *
 * Se não retornar nada ou verdadeiro, o item é processado no método onExecuting
 */
$worker->onReceive(function ($dados) {
    $id = $dados['payload']['id'];
    var_dump(['recebidos' => $id]);
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
    $id = $dados['payload']['id'];

    var_dump(['processando' => $id]);

    $number = rand(0, 10) % 2 === 0;
    if ($number) throw new \Exception("Error");

    $message->ack();
});

/**
 * Método que executa automaticamente caso aconteça uma exception não tratada
 * durante o processamento
 */
$worker->onError(function (\Exception $e, $dados) {
    $id = $dados['payload']['id'];
    var_dump(["erro" => $id]);
});


$worker
    ->createQueue("queue_teste")
    ->consume();

```

## Tratamento de erros e sucesso

Dentro da classe de worker, deverá ser implementada a interface **WorkerInterface**, que torna obrigatório 2 metodos:

- **handle:** Processa a tarefa
- **error:** Callback executado quando ocorre um erro

Todos **as exceptions lançadas no método handle**, serão interceptadas automaticamente para que o item seja marcado
como **erro** e seja **recolocado na fila** se necessário

## OBRIGATÓRIO

- Sempre execute um: **nack**, **nackCancel** ou **nackError** para que a tarefa tenha um tratamento e não fique
  infinito na fila.

- nack: marca como sucesso
- nackCancel: marca a tarefa como cancelada
- nackError: marca a tarefa como erro, tratando automaticamente o requeue

## Demonstração

Dentro desse repositório tem a pasta **demo**, nela tem 3 exemplos:

- queue: consumer e publisher simples
- queue_db: consumer e publisher que mantém espelho/monitoramento da fila no banco de dados
- pubsub: consumer e publisher no modelo pubsub

- **publisher.php**: Arquivo que publica itens na fila
- **consumer.php**: Arquivo que consome itens na fila, podendo ter várias instâncias
