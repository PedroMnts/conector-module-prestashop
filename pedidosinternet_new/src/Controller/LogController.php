<?php
declare(strict_types=1);

namespace PedidosInternet\Controller;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\JsonResponse;

class LogController extends FrameworkBundleAdminController
{
    public function list(): JsonResponse
    {
        $sqlQuery = new \DbQuery();
        $sqlQuery
            ->from('pedidosinternet_logs')
            ->select('id,fecha_inicio,fecha_fin,direccion,url,contenido,respuesta')
            ->limit(20)
        ;
        return new JsonResponse(
            array_map(
                fn($row) => [
                    'id' => $row['id'],
                    'initial' => $row['fecha_inicio'],
                    'end' => $row['fecha_fin'],
                    'direction' => $row['direccion'],
                    'url' => $row['url'],
                    'content' => $row['contenido'],
                    'answer' => $row['respuesta']
                ],
                \Db::getInstance()->executeS($sqlQuery)));
    }
}
