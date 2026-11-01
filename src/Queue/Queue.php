<?php

namespace WillRy\RabbitRun\Queue;


use Exception;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use WillRy\RabbitRun\Base;

class Queue extends Base
{
    /** @var string|null nome do consumer (consumer tag no RabbitMQ); vazio gera um nome aleatório */
    public $consumerName;
    
    /** @var string nome da fila */
    protected $queueName;
    
    /** @var string nome da exchange */
    protected $exchangeName;

    /** @var array argumentos usados na declaração da fila */
    protected $queueArguments = [];

    /** @var array filas já declaradas no canal atual */
    protected $declaredQueues = [];

    /** @var \PhpAmqpLib\Channel\AMQPChannel|null canal em que as filas foram declaradas */
    protected $declaredChannel;

    protected \Closure $onReceiveCallback;

    protected \Closure $onExecutingCallback;

    protected \Closure $onErrorCallback;

    protected \Closure $onCheckStatusCallback;

    /**
     * @param int $heartbeat intervalo em segundos (0 desativa); deve ser maior que a tarefa mais longa
     */
    public function __construct($host, $port, $user, #[\SensitiveParameter] $pass, $vhost, int $heartbeat = 60)
    {
        parent::__construct();

        $this->configRabbit($host, $port, $user, $pass, $vhost, $heartbeat);
    }

    /**
     * Publica mensagem
     *
     * @param array $job
     * @return array
     * @throws Exception
     */
    public function publish(
        string $queueName,
        array  $job
    ) {
        $this->createQueue($queueName);

        $payload = [
            "payload" => $job,
            'queue' => $this->queueName,
        ];

        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        $message = new AMQPMessage(
            $json,
            array('content_type' => 'application/json', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT)
        );

        $this->channel->basic_publish(
            $message,
            $this->exchangeName
        );

        if ($this->confirmSelect) {
            $this->channel->wait_for_pending_acks($this->confirmTimeout);
        }

        return $payload;
    }

    /**
     * Inicializa a fila e exchange, vinculano os 2
     * @param string $name
     * @return $this
     */
    public function createQueue(string $name)
    {

        $this->queueName = "{$name}";

        $this->exchangeName = "{$name}_exchange";

        // novo canal (ex: após reconexão) precisa declarar tudo de novo
        if ($this->declaredChannel !== $this->channel) {
            $this->declaredQueues = [];
            $this->declaredChannel = $this->channel;
        }

        if (isset($this->declaredQueues[$name])) {
            return $this;
        }

        $this->exchange($this->exchangeName);

        $this->queue($name, false, true, false, false, $this->queueArguments);

        $this->bind($name, $this->exchangeName);

        $this->declaredQueues[$name] = true;

        return $this;
    }

    /**
     * Configura o tipo da fila e o limite de entregas
     * Publisher e consumer devem usar a mesma configuração,
     * pois o RabbitMQ não permite redeclarar a fila com argumentos diferentes
     *
     * @param string $type classic|quorum
     * @param int|null $deliveryLimit (somente quorum) após N entregas a mensagem é descartada ou enviada à DLX
     * @param string|null $deadLetterExchange exchange (já existente) que recebe as mensagens descartadas
     * @return $this
     */
    public function configQueue(
        string  $type = 'quorum',
        ?int    $deliveryLimit = null,
        ?string $deadLetterExchange = null
    ) {
        if (!in_array($type, ['classic', 'quorum'], true)) {
            throw new \InvalidArgumentException("Tipo de fila inválido: {$type}");
        }

        if ($deliveryLimit !== null && $type !== 'quorum') {
            throw new \InvalidArgumentException("deliveryLimit só é suportado em filas quorum");
        }

        $this->queueArguments = ['x-queue-type' => $type];

        if ($deliveryLimit !== null) {
            $this->queueArguments['x-delivery-limit'] = $deliveryLimit;
        }

        if ($deadLetterExchange !== null) {
            $this->queueArguments['x-dead-letter-exchange'] = $deadLetterExchange;
        }

        // argumentos mudaram: as filas precisam ser declaradas novamente
        $this->declaredQueues = [];

        return $this;
    }

    /**
     * Loop de consumo de mensagem
     *
     * @param int $sleepSeconds intervalo de verificação do status enquanto o worker estiver pausado
     * @throws Exception
     */
    public function consume(
        string $queueName,
        int    $sleepSeconds = 3
    ) {
        $this->validateExecuteCallback();

        if (!function_exists('pcntl_sigprocmask')) {
            throw new \RuntimeException("A extensão pcntl é necessária para consumir filas");
        }

        self::$consumers++;

        try {
            $this->loopConnection(function () use ($sleepSeconds, $queueName) {

                $this->createQueue($queueName);

                $this->channel->basic_qos(null, 1, null);

                while (!self::$stopping) {
                    // Worker pausado: não consome, apenas verifica o status periodicamente
                    if (!$this->isRunning()) {
                        sleep($sleepSeconds);
                        pcntl_signal_dispatch();
                        continue;
                    }

                    $this->consumeUntilCanceled();
                }
            });
        } finally {
            self::$consumers--;
        }
    }

    /**
     * Registra o consumer e processa mensagens até ele ser cancelado
     * (ao pausar o worker via onCheckStatus)
     */
    protected function consumeUntilCanceled()
    {
        print_r("[WORKER RUNNING]" . PHP_EOL);

        $this->channel->basic_consume(
            $this->queueName,
            $this->consumerName ?: $this->randomConsumer(),
            false,
            false,
            false,
            false,
            function (AMQPMessage $message) {
                pcntl_sigprocmask(SIG_BLOCK, [SIGTERM, SIGINT]);

                try {
                    $incomeData = json_decode($message->getBody(), true);

                    // corpo inválido nunca será processado: descarta (ou envia para a DLX, se configurada)
                    if (!is_array($incomeData)) {
                        $reason = json_last_error() !== JSON_ERROR_NONE ? json_last_error_msg() : "body is not a JSON object";
                        print_r("[INVALID MESSAGE BODY] {$reason}" . PHP_EOL);
                        $message->nack();
                        return;
                    }

                    //se o status for negativo, não executa o consumo
                    if (!$this->executeStatusCallback($message)) {
                        return;
                    }

                    if (!$this->executeReceiveCallback($message, $incomeData)) {
                        return;
                    }

                    try {
                        $this->executeMessage($message, $incomeData);
                    } catch (\Throwable $e) {
                        try {
                            // registra a falha antes de devolver a mensagem, para outro worker não recebê-la antes disso
                            $this->executeErrorCallback($e, $incomeData, $message);
                        } finally {
                            $this->nackIfPending($message, true);
                        }
                        return;
                    }

                    // callback terminou sem responder a mensagem: confirma automaticamente
                    $this->ackIfPending($message);
                } finally {
                    // sempre libera os sinais, mesmo se algum callback lançar exceção
                    pcntl_sigprocmask(SIG_UNBLOCK, [SIGTERM, SIGINT]);
                }
            }
        );

        // Loop enquanto houver consumers registrados no canal
        while ($this->channel->is_consuming() && !self::$stopping) {
            try {
                // timeout curto para verificar o sinal de parada mesmo sem mensagens
                $this->channel->wait(null, false, 1);
            } catch (AMQPTimeoutException $e) {
                // nenhuma mensagem no período
            }

            // Despachar eventos de "finalizar" script
            pcntl_signal_dispatch();
        }
    }

    public function validateExecuteCallback()
    {
        if (!empty($this->onExecutingCallback)) {
            return true;
        }

        throw new Exception("Define a onExecuting callback");
    }

    public function executeStatusCallback(AMQPMessage $message)
    {
        if ($this->isRunning()) {
            return true;
        }

        print_r("[WORKER STOPPED]" . PHP_EOL);

        // cancela o consumer antes de devolver a mensagem, para o broker não entregar outra a este worker
        $message->getChannel()->basic_cancel($message->getConsumerTag());
        $message->nack(true);
        return false;
    }

    /**
     * Status do worker segundo o onCheckStatus (retorno null conta como ativo)
     */
    protected function isRunning(): bool
    {
        if (empty($this->onCheckStatusCallback)) {
            return true;
        }

        $checkStatusCallback = $this->onCheckStatusCallback;
        $statusBoolean = $checkStatusCallback();

        return $statusBoolean || !isset($statusBoolean);
    }

    public function executeReceiveCallback(AMQPMessage $message, $incomeData)
    {
        if (empty($this->onReceiveCallback)) {
            return true;
        }

        $receiveCallback = $this->onReceiveCallback;
        $statusBoolean = $receiveCallback($incomeData);
        if (!$statusBoolean && isset($statusBoolean)) {
            print_r("[TASK IGNORED BY ON RECEIVE RETURN]" . PHP_EOL);
            $message->nack();
            return false;
        }


        return true;
    }

    public function executeMessage(AMQPMessage $message, $incomeData)
    {
        $onExecutingCallback = $this->onExecutingCallback;
        $onExecutingCallback($message, $incomeData);
    }

    public function executeErrorCallback(\Throwable $e, $incomeData, ?AMQPMessage $message = null)
    {
        if (empty($this->onErrorCallback)) {
            return false;
        }

        $errorCallback = $this->onErrorCallback;
        $errorCallback($e, $incomeData, $message);
        return true;
    }

    /**
     * Faz ack apenas se o callback ainda não respondeu a mensagem
     */
    protected function ackIfPending(AMQPMessage $message)
    {
        try {
            $message->ack();
        } catch (\LogicException $e) {
            // mensagem já respondida (ack/nack) pelo callback
        }
    }

    /**
     * Faz nack apenas se o callback ainda não respondeu a mensagem
     */
    protected function nackIfPending(AMQPMessage $message, bool $requeue = false)
    {
        try {
            $message->nack($requeue);
        } catch (\LogicException $e) {
            // mensagem já respondida (ack/nack) pelo callback
        }
    }

    public function onCheckStatus(\Closure $callback)
    {
        $this->onCheckStatusCallback = $callback;
    }

    public function onReceive(\Closure $callback)
    {
        $this->onReceiveCallback = $callback;
    }

    public function onExecuting(\Closure $callback)
    {
        $this->onExecutingCallback = $callback;
    }

    public function onError(\Closure $callback)
    {
        $this->onErrorCallback = $callback;
    }
}
