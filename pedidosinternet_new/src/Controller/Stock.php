<?php
declare(strict_types=1);

namespace PedidosInternet\Controller;

use PedidosInternet\Log;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\JsonResponse;
use StockAvailable;
use Db;

/**
 * Controlador para gestionar operaciones relacionadas con el stock
 * 
 * Proporciona métodos para actualizar el stock de productos en PrestaShop,
 * tanto de forma individual como masiva.
 */
class Stock extends FrameworkBundleAdminController
{
    /**
     * Valor de stock alto utilizado para indicar disponibilidad ilimitada
     */
    private const HIGH_STOCK_VALUE = 99999;
    
    /**
     * Actualiza el stock de todos los productos a un valor alto
     * 
     * Este método establece un stock elevado para todos los productos,
     * lo que efectivamente los marca como "siempre disponibles".
     *
     * @return JsonResponse Respuesta con código 202 (Accepted)
     */
    public function updateAllProductsStock(): JsonResponse
    {
        try {
            
            $products = Db::getInstance()->executeS('SELECT id_product FROM '._DB_PREFIX_.'product');
            $count = 0;
            
            foreach ($products as $product) {
                StockAvailable::setQuantity((int)$product['id_product'], 0, self::HIGH_STOCK_VALUE);
                $count++;
            }
            
            return new JsonResponse([
                'success' => true,
                'message' => "Stock actualizado para $count productos"
            ], 202);
            
        } catch (\Exception $e) {
            Log::error("Error al actualizar stock masivo", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return new JsonResponse([
                'success' => false,
                'message' => "Error al actualizar stock masivo: " . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualiza el stock de un producto específico a un valor alto
     * 
     * Este método establece un stock elevado para un producto individual,
     * marcándolo como "siempre disponible".
     *
     * @param int $productId ID del producto a actualizar
     * @return JsonResponse Respuesta con código 202 (Accepted)
     */
    public function updateProductStock(int $productId): JsonResponse
    {
        try {
            if ($productId <= 0) {
                Log::warning("Intento de actualizar stock con ID de producto inválido", [
                    'product_id' => $productId
                ]);
                
                return new JsonResponse([
                    'success' => false,
                    'message' => "ID de producto inválido"
                ], 400);
            }
            
            StockAvailable::setQuantity($productId, 0, self::HIGH_STOCK_VALUE);
            
            return new JsonResponse([
                'success' => true,
                'message' => "Stock actualizado para producto ID: $productId"
            ], 202);
            
        } catch (\Exception $e) {
            Log::error("Error al actualizar stock de producto", [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            
            return new JsonResponse([
                'success' => false,
                'message' => "Error al actualizar stock: " . $e->getMessage()
            ], 500);
        }
    }
}