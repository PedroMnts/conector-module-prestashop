<?php
declare(strict_types=1);

namespace PedidosInternet\Dto;

use PedidosInternet\Log;
use Feature;

/**
 * DTO para gestionar características de productos
 * 
 * Facilita la creación y gestión de características (features) en PrestaShop
 * basadas en datos recibidos del ERP Distrib
 */
class DtoFeature
{
    /**
     * Crea una nueva característica en PrestaShop
     *
     * @param int $langDefault ID del idioma por defecto
     * @param array $featureData Datos de la característica desde el ERP
     * @return int|null ID de la característica creada o null si hay error
     */
    public static function createFeature(int $langDefault, array $featureData): ?int
    {
        if (empty($featureData['id']) || empty($featureData['name'])) {
            Log::warning("Datos de característica incompletos", [
                'feature_data' => $featureData
            ]);
            return null;
        }

        try {
            // Comprobar si la característica ya existe
            $existingFeature = Feature::getFeature($langDefault, $featureData['id']);
            
            if ($existingFeature) {

                return (int)$existingFeature['id_feature'];
            }
            
            Log::info("Creando nueva característica", [
                'feature_id' => $featureData['id'],
                'name' => $featureData['name']
            ]);
            
            // Almacenar el ID y el nombre originales
            $featureId = $featureData['id'];
            $featureName = $featureData['name'];
            
            // Datos para la tabla feature
            $featureInsertData = [
                "id_feature" => $featureId,
                "position" => Feature::getHigherPosition() + 1,
            ];
                
            // Insertar en tabla feature
            $result = \Db::getInstance()->insert('feature', $featureInsertData);
            
            if (!$result) {
                Log::error("Error al insertar característica", [
                    'feature_data' => $featureInsertData
                ]);
                return null;
            }
            
            // Asociar con tienda
            $shopId = (int)\Context::getContext()->shop->id;
            $featureShopData = [
                "id_feature" => $featureId,
                "id_shop" => $shopId,
            ];
            
            $result = \Db::getInstance()->insert('feature_shop', $featureShopData);
            
            if (!$result) {
                Log::warning("Error al asociar característica con tienda", [
                    'feature_id' => $featureId,
                    'shop_id' => $shopId
                ]);
                // No retornamos null aquí para permitir continuar con el proceso
            }
            
            // Añadir traducción
            $featureLangData = [
                "id_feature" => $featureId,
                "id_lang" => $langDefault,
                "name" => $featureName,
            ];
            
            $result = \Db::getInstance()->insert('feature_lang', $featureLangData);
                
            if (!$result) {
                Log::error("Error al insertar traducción de característica", [
                    'feature_lang_data' => $featureLangData,
                    'feature_id' => $featureId,
                    'lang_id' => $langDefault
                ]);
                return null;
            }
            
            return (int)$featureId;
            
        } catch (\Exception $e) {
            Log::error("Error al crear característica", [
                'feature_data' => $featureData,
                'feature_id' => $featureData['id'] ?? 'unknown',
                'name' => $featureData['name'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

}