<?php
declare(strict_types=1);

namespace PedidosInternet\Dto;

use PedidosInternet\Log;
use Product;
use Category;
use Feature;
use Db;
use Validate; 

/**
 * Clase para gestionar la relación entre categorías y características de productos
 * 
 * Proporciona funcionalidades para asignar categorías a productos
 * basándose en los valores de características
 */
class DtoCategoriesWithTemplates
{
    /**
     * Asigna categorías a productos basándose en valores de características
     * 
     * Busca coincidencias entre nombres de categorías y valores de características
     * y asigna las categorías correspondientes a los productos.
     *
     * @return array Estadísticas del proceso de asignación
     */
    public static function asignValues(): array
    {
        try {
            
            $startTime = microtime(true);
            $stats = [
                'products_processed' => 0,
                'categories_assigned' => 0,
                'already_assigned' => 0,
                'errors' => 0
            ];

            // Obtener todos los productos
            $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'product`';
            $allProducts = Db::getInstance()->executeS($sql);
            
            if (!is_array($allProducts)) {
                throw new \Exception("Error al obtener la lista de productos");
            }

            // Obtener todas las categorías
            $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'category`';
            $allCategories = Db::getInstance()->executeS($sql);
            
            if (!is_array($allCategories)) {
                throw new \Exception("Error al obtener la lista de categorías");
            }

            // Procesar cada producto
            foreach ($allProducts as $product) {
                try {
                    $stats['products_processed']++;
                    $productId = (int)$product['id_product'];
                    
                    // Obtener valores de características del producto
                    $sql = 'SELECT `id_feature_value` FROM `' . _DB_PREFIX_ . 'feature_product` WHERE `id_product` = ' . $productId;
                    $productFeatureValues = Db::getInstance()->executeS($sql);
                    
                    if (!is_array($productFeatureValues) || empty($productFeatureValues)) {
                        continue; // Producto sin características, pasar al siguiente
                    }
                    
                    foreach ($productFeatureValues as $feature) {
                        $featureValueId = (int)$feature['id_feature_value'];
                        
                        // Obtener el valor de la característica
                        $sql = 'SELECT `value` FROM `' . _DB_PREFIX_ . 'feature_value_lang` WHERE `id_feature_value` = ' . $featureValueId;
                        $featuresSelected = Db::getInstance()->executeS($sql);
                        
                        if (!is_array($featuresSelected) || empty($featuresSelected)) {
                            continue;
                        }
                        
                        // Buscar coincidencias con categorías
                        self::processFeatureValueCategories(
                            $productId, 
                            $featuresSelected, 
                            $allCategories,
                            $stats
                        );
                    }
                } catch (\Exception $e) {
                    $stats['errors']++;
                    Log::error("Error al procesar producto para asignacion de categorías", [
                        'product_id' => $productId ?? 'desconocido',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
            
            $executionTime = round(microtime(true) - $startTime, 2);
            
            $result = [
                'success' => true,
                'stats' => $stats,
                'execution_time' => $executionTime,
                'message' => "Asignacion de categorías completada. " . 
                             "Productos procesados: {$stats['products_processed']}, " .
                             "Categorías asignadas: {$stats['categories_assigned']}"
            ];
                        
            return $result;
        } catch (\Exception $e) {
            Log::error("Error general en asignacion de categorías", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => "Error al asignar categorías: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * Procesa y asigna categorías que coinciden con un valor de característica
     *
     * @param int $productId ID del producto a actualizar
     * @param array $featuresSelected Valores de característica seleccionados
     * @param array $allCategories Lista de todas las categorías
     * @param array &$stats Estadísticas para actualizar por referencia
     * @return void
     */
    private static function processFeatureValueCategories(
        int $productId, 
        array $featuresSelected, 
        array $allCategories,
        array &$stats
    ): void {
        foreach ($allCategories as $category) {
            $categoryId = (int)$category['id_category'];
            
            // Obtener el nombre de la categoría
            $sql = 'SELECT `id_category`, `name` FROM `' . _DB_PREFIX_ . 'category_lang` WHERE `id_category` = ' . $categoryId;
            $categoryInfo = Db::getInstance()->executeS($sql);
            
            if (!is_array($categoryInfo) || empty($categoryInfo)) {
                continue;
            }
            
            $categoryName = $categoryInfo[0]["name"] ?? '';
            
            if (empty($categoryName)) {
                continue;
            }
            
            // Verificar coincidencia con valores de característica
            foreach ($featuresSelected as $featureValue) {
                $featureValueText = $featureValue["value"] ?? '';
                
                if (empty($featureValueText)) {
                    continue;
                }
                
                // Comparar nombres (case insensitive)
                if (strtolower($categoryName) === strtolower($featureValueText)) {
                    
                    self::assignCategoryToProduct($productId, $categoryId, $stats);
                }
            }
        }
    }
    
    /**
     * Asigna una categoría a un producto si no está ya asignada
     *
     * @param int $productId ID del producto
     * @param int $categoryId ID de la categoría
     * @param array &$stats Estadísticas para actualizar por referencia
     * @return void
     */
    private static function assignCategoryToProduct(int $productId, int $categoryId, array &$stats): void
    {
        try {
            $productObj = new Product($productId);
            
            if (!$productObj->id) {
                Log::warning("Producto no válido al intentar asignar categoría", [
                    'product_id' => $productId,
                    'category_id' => $categoryId
                ]);
                return;
            }
            
            $currentCategories = $productObj->getCategories();
            
            if (in_array($categoryId, $currentCategories)) {
                $stats['already_assigned']++;

                return;
            }
            
            $productObj->addToCategories([$categoryId]);
            $stats['categories_assigned']++;
            
        } catch (\Exception $e) {
            $stats['errors']++;
            Log::error("Error al asignar categoría a producto", [
                'product_id' => $productId,
                'category_id' => $categoryId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
    * Asigna un producto a la categoría de ofertas flash
    * Si la categoría no existe, la crea automáticamente
    *
    * @param int $productId ID del producto a asignar
    * @return bool Resultado de la operación
    */
    public static function assignToFlashOffersCategory(int $productId): bool
    {
        try {
            // Buscar la categoría "Ofertas flash" por nombre
            $langDefault = (int)\Configuration::get('PS_LANG_DEFAULT');
            $flashOffersCategoryId = null;
            
            $sql = 'SELECT c.id_category FROM `' . _DB_PREFIX_ . 'category` c
                    INNER JOIN `' . _DB_PREFIX_ . 'category_lang` cl 
                    ON c.id_category = cl.id_category
                    WHERE cl.name = "Ofertas flash" 
                    AND cl.id_lang = ' . $langDefault;
            
            $result = Db::getInstance()->getValue($sql);
            
            if ($result) {
                // Categoría encontrada
                $flashOffersCategoryId = (int)$result;

            } else {
                // Crear nueva categoría
                Log::info("Categoría 'Ofertas flash' no encontrada, creando nueva categoría");
                
                $homeCategory = (int)\Configuration::get('PS_HOME_CATEGORY');
                $category = new Category();
                $category->name = [$langDefault => 'Ofertas flash'];
                $category->link_rewrite = [$langDefault => 'ofertas-flash'];
                $category->id_parent = $homeCategory;
                $category->active = 1;
                
                if (!$category->add()) {
                    Log::error("Error al crear categoría 'Ofertas flash'");
                    return false;
                }
                
                $flashOffersCategoryId = (int)$category->id;
                Log::info("Categoría 'Ofertas flash' creada exitosamente", [
                    'category_id' => $flashOffersCategoryId
                ]);
            }
            
            // Verificar que tenemos un ID válido
            if (!$flashOffersCategoryId) {
                Log::error("No se pudo obtener un ID válido para la categoría 'Ofertas flash'");
                return false;
            }
            
            // Proceder con la asignación del producto a la categoría
            
            $product = new Product($productId);
            
            if (!$product->id) {
                Log::warning("Producto no válido al intentar asignar a 'Ofertas flash'", [
                    'product_id' => $productId
                ]);
                return false;
            }
            
            $currentCategories = $product->getCategories();
            
            if (in_array($flashOffersCategoryId, $currentCategories)) {
                return true;
            }
            
            $result = $product->addToCategories([$flashOffersCategoryId]);
            
            if ($result) {
                Log::info("Producto asignado correctamente a 'Ofertas flash'", [
                    'product_id' => $productId,
                    'category_id' => $flashOffersCategoryId
                ]);
            } else {
                Log::warning("No se pudo asignar el producto a 'Ofertas flash'", [
                    'product_id' => $productId,
                    'category_id' => $flashOffersCategoryId
                ]);
            }
            
            return $result;
        } catch (\Exception $e) {
            Log::error("Error al asignar producto a 'Ofertas flash'", [
                'product_id' => $productId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}