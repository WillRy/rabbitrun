<?php

namespace WillRy\RabbitRun\Traits;

use WillRy\RabbitRun\Connections\ConnectPDO;

trait Helpers
{
    public function randomTag($len = 30): string
    {
        do {
            $pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $string = substr(str_shuffle(str_repeat($pool, (int)ceil($len / strlen($pool)))), 0, $len);

            $stmt = ConnectPDO::getInstance()->prepare("SELECT * FROM jobs WHERE tag = ?");
            $stmt->bindValue(1, $string);
            $stmt->execute();

        } while ($stmt->rowCount() > 0);

        return $string;
    }

    public function randomConsumer($len = 30): string
    {
        $pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        return substr(str_shuffle(str_repeat($pool, (int)ceil($len / strlen($pool)))), 0, $len);
    }
}
