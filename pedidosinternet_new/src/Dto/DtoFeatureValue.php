<?php
declare(strict_types=1);

namespace PedidosInternet\Dto;

use PedidosInternet\Log;
use Db;

/**
 * DTO para gestionar valores de características de productos
 * 
 * Facilita la creación y gestión de valores de características en PrestaShop
 * basadas en datos recibidos del ERP Distrib
 */
class DtoFeatureValue
{
    /**
     * Crea valores de características basados en taxonomías
     *
     * @param int $langDefault ID del idioma por defecto
     * @param array $featureData Datos de la característica
     * @param array $taxonomies Taxonomías disponibles desde el ERP
     * @return bool Éxito de la operación
     */
    public static function createFeatureValue(int $langDefault, array $featureData, array $taxonomies): bool
    {
        if (empty($featureData['id'])) {
            Log::warning("ID de característica no especificado", [
                'feature_data' => $featureData
            ]);
            return false;
        }

        try {
            // Si no es una característica basada en taxonomía, no continuar
            if (empty($featureData["taxonomyId"]) || $featureData["taxonomyId"] <= 0) {

                return true; // No es un error, simplemente no aplica
            }
            
            // Buscar la taxonomía correspondiente
            $foundTaxonomy = null;
            foreach ($taxonomies as $taxonomy) {
                if ($taxonomy["id"] == $featureData["taxonomyId"]) {
                    $foundTaxonomy = $taxonomy;
                    break;
                }
            }
            
            if (!$foundTaxonomy) {
                Log::warning("Taxonomía no encontrada para característica", [
                    'feature_id' => $featureData["id"],
                    'taxonomy_id' => $featureData["taxonomyId"]
                ]);
                return false;
            }

            // Crear valor para la taxonomía principal
            self::addFeatureValueToDatabase($langDefault, $featureData, $foundTaxonomy);
            
            // Procesar términos de la taxonomía (si existen)
            if (!empty($foundTaxonomy["terms"])) {
                self::processTerms($langDefault, $featureData, $foundTaxonomy, $foundTaxonomy["terms"]);
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error("Error al crear valores de característica", [
                'feature_data' => $featureData,
                'feature_id' => $featureData["id"] ?? 'unknown',
                'taxonomy_id' => $featureData["taxonomyId"] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Procesa los términos de una taxonomía de forma recursiva
     * 
     * @param int $langDefault ID del idioma por defecto
     * @param array $featureData Datos de la característica
     * @param array $taxonomy Datos de la taxonomía
     * @param array $terms Términos a procesar
     * @return void
     */
    private static function processTerms(int $langDefault, array $featureData, array $taxonomy, array $terms): void
    {
        foreach ($terms as $term) {
            self::addFeatureValueToDatabase($langDefault, $featureData, $taxonomy, $term);
            
            if (!empty($term["childrenTerms"])) {
                foreach ($term['childrenTerms'] as $childTerm) {
                    self::addFeatureValueToDatabase($langDefault, $featureData, $taxonomy, $term, $childTerm);
                    
                    if (!empty($childTerm["childrenTerms"])) {
                        self::processTerms($langDefault, $featureData, $taxonomy, $childTerm["childrenTerms"]);
                    }
                }
            }
        }
    }

    /**
     * Añade un valor de característica a la base de datos
     *
     * @param int $langDefault ID del idioma por defecto
     * @param array $featureData Datos de la característica
     * @param array $taxonomy Datos de la taxonomía
     * @param array|null $term Término (opcional)
     * @param array|null $childTerm Término hijo (opcional)
     * @return bool Éxito de la operación
     */
    public static function addFeatureValueToDatabase(
        int $langDefault, 
        array $featureData, 
        array $taxonomy, 
        ?array $term = null, 
        ?array $childTerm = null
    ): bool {
        try {
            // Determinar ID y valor basado en los parámetros
            if ($childTerm) {
                $idFeatureValue = (int)($taxonomy["id"] . '0' . $childTerm["id"]);
                $value = $childTerm["description"];
                $logContext = 'ChildTerm';
            } elseif ($term) {
                $idFeatureValue = (int)($taxonomy["id"] . '0' . $term["id"]);
                $value = $term["description"];
                $logContext = 'Term';
            } else {
                $idFeatureValue = (int)$taxonomy["id"];
                $value = $taxonomy["description"];
                $logContext = 'Taxonomy';
            }
            
            // Verificar si ya existe
            $existingValue = self::featureValueExists($idFeatureValue, $featureData["id"]);
            
            if ($existingValue) {

                return true;
            }
            
            // Insertar valor de característica
            $result = self::insertFeatureValue($idFeatureValue, $featureData["id"]);
            
            if (!$result) {
                Log::error("Error al insertar valor de característica", [
                    'context' => $logContext,
                    'feature_id' => $featureData["id"],
                    'value_id' => $idFeatureValue
                ]);
                return false;
            }
            
            // Insertar traducción del valor
            $result = self::insertFeatureValueLang($idFeatureValue, $langDefault, $value);
            
            if (!$result) {
                Log::error("Error al insertar traducción de valor de característica", [
                    'context' => $logContext,
                    'feature_id' => $featureData["id"],
                    'value_id' => $idFeatureValue,
                    'value' => $value
                ]);
                return false;
            }
            
            Log::debug("Valor de característica creado correctamente", [
                'context' => $logContext,
                'feature_id' => $featureData["id"],
                'value_id' => $idFeatureValue,
                'value' => $value
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error("Error al añadir valor de característica", [
                'feature_id' => $featureData["id"] ?? 'unknown',
                'taxonomy_id' => $taxonomy["id"] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Crea valores numéricos para una característica (del 1 al 5)
     *
     * @param int $langDefault ID del idioma por defecto
     * @param array $featureData Datos de la característica
     * @return bool Éxito de la operación
     */
    public static function setNumbericFeatureValues(int $langDefault, array $featureData): bool
    {
        if (empty($featureData["id"])) {
            Log::warning("ID de característica no especificado para valores numéricos", [
                'feature_data' => $featureData
            ]);
            return false;
        }

        try {
            $success = true;
            
            for ($i = 1; $i <= 5; $i++) {
                $idFeatureValue = (int)($featureData["id"] . $i);
                
                // Verificar si ya existe
                $existingValue = self::featureValueExists($idFeatureValue, $featureData["id"]);
                
                if ($existingValue) {
                    continue;
                }
                
                // Insertar valor numérico
                $result = self::insertFeatureValue($idFeatureValue, $featureData["id"]);
                
                if (!$result) {
                    $success = false;
                    Log::error("Error al insertar valor numérico", [
                        'feature_id' => $featureData["id"],
                        'value_id' => $idFeatureValue,
                        'value' => $i
                    ]);
                    continue;
                }
                
                // Insertar traducción del valor numérico
                $result = self::insertFeatureValueLang($idFeatureValue, $langDefault, $i);
                
                if (!$result) {
                    $success = false;
                    Log::error("Error al insertar traducción de valor numérico", [
                        'feature_id' => $featureData["id"],
                        'value_id' => $idFeatureValue,
                        'value' => $i
                    ]);
                    continue;
                }
                
            }
            
            return $success;
        } catch (\Exception $e) {
            Log::error("Error al crear valores numéricos", [
                'feature_id' => $featureData["id"] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Verifica si un valor de característica existe
     *
     * @param int $idFeatureValue ID del valor
     * @param int $idFeature ID de la característica
     * @return bool true si existe, false si no
     */
    private static function featureValueExists(int $idFeatureValue, int $idFeature): bool
    {
        try {
            $result = Db::getInstance()->getValue(
                'SELECT `id_feature` FROM ' . _DB_PREFIX_ . 'feature_value
                WHERE `id_feature_value` = ' . (int)$idFeatureValue . ' 
                AND `id_feature` = ' . (int)$idFeature . ' 
                AND `custom` = 0'
            );
            
            return !empty($result);
        } catch (\Exception $e) {
            Log::error("Error al verificar existencia de valor de característica", [
                'feature_id' => $idFeature,
                'value_id' => $idFeatureValue,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Inserta un valor de característica en la base de datos
     *
     * @param int $idFeatureValue ID del valor
     * @param int $idFeature ID de la característica
     * @param int $custom Indicador si es personalizado (0=no, 1=sí)
     * @return bool Éxito de la operación
     */
    private static function insertFeatureValue(int $idFeatureValue, int $idFeature, int $custom = 0): bool
    {
        try {
            // Comprobar si ya existe
            if (self::featureValueExists($idFeatureValue, $idFeature)) {
                return true;
            }
            
            // Preparar datos para inserción
            $data = [
                "id_feature_value" => $idFeatureValue,
                "id_feature" => $idFeature,
                "custom" => $custom,
            ];
            
            return Db::getInstance()->insert('feature_value', $data);
        } catch (\Exception $e) {
            Log::error("Error al insertar valor de característica", [
                'feature_id' => $idFeature,
                'value_id' => $idFeatureValue,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Inserta la traducción de un valor de característica
     *
     * @param int $idFeatureValue ID del valor
     * @param int $langId ID del idioma
     * @param mixed $value Valor (texto o número)
     * @return bool Éxito de la operación
     */
    private static function insertFeatureValueLang(int $idFeatureValue, int $langId, $value): bool
    {
        try {
            // Comprobar si ya existe
            $existingValue = Db::getInstance()->getValue(
                'SELECT `id_feature_value` FROM ' . _DB_PREFIX_ . 'feature_value_lang
                WHERE `id_feature_value` = ' . (int)$idFeatureValue . ' 
                AND `id_lang` = ' . (int)$langId
            );
            
            if ($existingValue) {
                return true;
            }
            
            // Preparar datos para inserción
            $data = [
                "id_feature_value" => $idFeatureValue,
                "id_lang" => $langId,
                "value" => $value,
            ];
            
            return Db::getInstance()->insert('feature_value_lang', $data);
        } catch (\Exception $e) {
            Log::error("Error al insertar traducción de valor de característica", [
                'value_id' => $idFeatureValue,
                'lang_id' => $langId,
                'value' => $value,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}