<?php

namespace PedidosInternet;

class Log
{
    public const API = 'API';
    public const PRESTASHOP = 'PRESTASHOP';
    public const URL = '';


    public static $lastInsertedId = 0;

    /**
     * @throws \PrestaShopDatabaseException
     */
    public static function create(
        \DateTimeImmutable $initialTime,
        ?\DateTimeImmutable $endTime,
        string $direction,
        string $url,
        array $content = [],
        array $answer = []
    ): void {
        $db = \Db::getInstance();
        $inserted = $db->insert(
            "pedidosinternet_logs",
            [
                'fecha_inicio' => $initialTime->format("Y-m-d H:i:s"),
                'fecha_fin' => $endTime ? $endTime->format("Y-m-d H:i:s") : null,
                'direccion' => $direction,
                'url' => $url,
                'contenido' => json_encode($content),
                'respuesta' => json_encode($answer),
            ]
        );
        self::$lastInsertedId = ($inserted ? $db->getLink()->lastInsertId() : 0);
    }

    public static function update(
        int $id,
        \DateTimeImmutable $endTime,
        array $content
    ) {
        $db = \Db::getInstance();
        $db->update(
            "pedidosinternet_logs",
            [
                'fecha_fin' => $endTime->format("Y-m-d H:i:s"),
                'respuesta' => json_encode($content)
            ],
            "id = " . $id
        );
    }

    public static function delete() 
    {
        \Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'pedidosinternet_logs`');
        \Db::getInstance()->execute('TRUNCATE TABLE `' . _DB_PREFIX_ . 'pedidosinternet_logs`');
        \Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'pedidosinternet_logs` AUTO_INCREMENT = 1');
    }
}
