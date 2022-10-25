<?php

namespace WillRy\RabbitRun\Traits;

use WillRy\RabbitRun\Connections\ConnectPDO;

trait Helpers
{
    public function randomConsumer($len = 30): string
    {
        $pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        return substr(str_shuffle(str_repeat($pool, (int)ceil($len / strlen($pool)))), 0, $len);
    }
}
