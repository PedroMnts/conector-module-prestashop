<?php
declare(strict_types=1);

namespace PedidosInternet\Controller;


use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\JsonResponse;

class Stock extends FrameworkBundleAdminController
{

    /**
     *
     * @return JsonResponse
     */
    public function updateAllProductsStock(): JsonResponse
    {
        $highStock = 99999; 

        $products = \Db::getInstance()->executeS('SELECT id_product FROM '._DB_PREFIX_.'product');

        foreach ($products as $product) {
            \StockAvailable::setQuantity((int)$product['id_product'], 0, $highStock);
        }

        return new JsonResponse([], 202);
    }

    /**
     *
     * @return JsonResponse
     */
    static function updateProductStock($product): JsonResponse
    {
        $highStock = 99999; 

        \StockAvailable::setQuantity((int)$product, 0, $highStock);

        return new JsonResponse([], 202);
    }
}