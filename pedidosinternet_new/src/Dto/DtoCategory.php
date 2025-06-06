<?php
declare(strict_types=1);

namespace PedidosInternet\Dto;

use PedidosInternet\Log;
use Category;
use Configuration;
use Db;
use \IntlChar;

/**
 * Clase para gestionar categorías (taxonomías) de PrestaShop
 * 
 * Proporciona métodos para crear, actualizar y gestionar categorías
 * en PrestaShop basándose en datos recibidos del ERP
 */
class DtoCategory
{
    
  /**
     * Crea una categoría estándar en PrestaShop
     *
     * @param int $langDefault ID del idioma por defecto
     * @param array $category Datos de la categoría
     * @param int $parent ID de la categoría padre
     * @return Category|null La categoría creada/actualizada o null si hay error
     */
    public static function createCategory(int $langDefault, array $category, int $parent): ?Category
    {
        try {
            $existingCategoryId = self::getIdByApiId($category['id']);
            
            if (!empty($existingCategoryId)) {
                return new Category($existingCategoryId);
            }
            
            $objects = Category::searchByName($langDefault, $category['description']);
            
            if (!empty($objects)) {
                self::addId($objects[0]["id_category"], $category['id']);
                return new Category($objects[0]["id_category"]);
            }
            
            $object = new Category();
            $object->name = [$langDefault => $category['description']];
            $object->id_parent = $parent;
            $object->description = [$langDefault => htmlspecialchars($category['description'])];
            
            $link_rewrite = self::generateLinkRewrite($category['description']);
            $object->link_rewrite = [$langDefault => $link_rewrite];
            
            if (!$object->add()) {
                Log::error("Error al crear categoría estándar", [
                    'category_id' => $category['id'],
                    'name' => $category['description']
                ]);
                return null;
            }
            
            self::addId($object->id, $category['id']);
            
            return $object;
        } catch (\Exception $e) {
            Log::error("Error al crear categoría estándar", [
                'category_data' => $category,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Crea o actualiza una categoría en PrestaShop basándose en datos de taxonomía
     *
     * @param int $langDefault ID del idioma por defecto
     * @param array $taxonomy Datos de la taxonomía desde el ERP
     * @param int $parent ID de la categoría padre
     * @param bool $isParent Indica si es una categoría padre
     * @return Category|null La categoría creada/actualizada o null si hay error
     */
    public static function createCategoryWithTaxonomy(int $langDefault, array $taxonomy, int $parent, bool $isParent = false): ?Category
    {
        try {
            // Determinar el ID único para esta taxonomía
            $uniqueApiId = isset($taxonomy['unique_id']) 
                ? $taxonomy['unique_id'] 
                : (string)$taxonomy['id'];
            
            // Comprobar si la categoría ya existe por su api_id único
            $existingCategoryId = self::getIdByApiId($uniqueApiId);
            
            if (!empty($existingCategoryId)) {
                
                $category = new Category($existingCategoryId);
                if (\Validate::isLoadedObject($category)) {
                    // Actualizar datos básicos si es necesario
                    $categoryName = self::cleanCategoryName($taxonomy['description'] ?? '');
                    
                    if ($category->name[$langDefault] !== $categoryName) {
                        $category->name[$langDefault] = $categoryName;
                        $category->update();
                        
                        Log::info("Categoría actualizada con nombre nuevo", [
                            'category_id' => $category->id,
                            'unique_id' => $uniqueApiId,
                            'new_name' => $categoryName
                        ]);
                    }
                    
                    return $category;
                }
            }
            
            // Limpiar el nombre de la categoría
            $categoryName = self::cleanCategoryName($taxonomy['description'] ?? '');
            
            if (empty($categoryName)) {
                Log::warning("Taxonomía sin nombre válido", [
                    'taxonomy_id' => $taxonomy['id'] ?? 'unknown',
                    'unique_id' => $uniqueApiId,
                    'description' => $taxonomy['description'] ?? 'sin descripción'
                ]);
                return null;
            }
            
            // Buscar categoría por nombre (solo para categorías padres)
            if ($isParent) {
                $existingCategories = Category::searchByName($langDefault, $categoryName, true);
                
                if (!empty($existingCategories)) {
                    $categoryId = $existingCategories[0]["id_category"];
                    
                    // Actualizar el api_id de la categoría existente
                    self::addId($categoryId, $uniqueApiId);
                    
                    Log::info("Actualizada categoría padre existente con nuevo api_id", [
                        'category_id' => $categoryId,
                        'unique_id' => $uniqueApiId,
                        'name' => $categoryName
                    ]);
                    
                    return new Category($categoryId);
                }
            }
            
            // Crear nueva categoría
            $category = new Category();
            $category->name = [$langDefault => $categoryName];
            $category->id_parent = $parent;
            $category->description = [$langDefault => htmlspecialchars($categoryName)];
            $category->active = 1;
            
            // Establecer posición basada en el orden de la taxonomía
            if (isset($taxonomy['order'])) {
                $category->position = (int)$taxonomy['order'];
            }
            
            // Generar URL amigable
            $link_rewrite = self::generateLinkRewrite($categoryName);
            $category->link_rewrite = [$langDefault => $link_rewrite];
            
            if (!$category->add()) {
                Log::error("Error al crear categoría", [
                    'taxonomy_id' => $taxonomy['id'] ?? 'unknown',
                    'unique_id' => $uniqueApiId,
                    'name' => $categoryName,
                    'parent_id' => $parent
                ]);
                return null;
            }
            
            // Asignar api_id a la nueva categoría
            self::addId($category->id, $uniqueApiId);
            
            return $category;
        } catch (\Exception $e) {
            Log::error("Error al crear/actualizar categoría", [
                'taxonomy_id' => $taxonomy['id'] ?? 'unknown',
                'unique_id' => $taxonomy['unique_id'] ?? $taxonomy['id'] ?? 'unknown',
                'name' => $taxonomy['description'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Limpia el nombre de la categoría eliminando prefijos específicos
     *
     * @param string $name Nombre original de la categoría
     * @return string Nombre limpio
     */
    private static function cleanCategoryName(string $name): string
    {
        // Eliminar prefijos específicos
        $prefixesToRemove = [
            "B2C- ", "B2C-", "B2C - "
        ];
        
        return str_replace($prefixesToRemove, "", $name);
    }

    /**
     * Genera una URL amigable para la categoría
     *
     * @param string $name Nombre de la categoría
     * @return string URL amigable
     */
    private static function generateLinkRewrite(string $name): string
    {
        try {
            // Eliminar acentos y convertir a minúsculas
            $normalized = self::removeAccents(mb_strtolower($name, 'UTF-8'));
            
            // Reemplazar caracteres no permitidos
            $cleaned = preg_replace([
                '/[^a-z0-9\-]/',  // Eliminar caracteres no alfanuméricos
                '/-+/',           // Reemplazar múltiples guiones
                '/^-+|-+$/'       // Eliminar guiones al inicio y final
            ], [
                '-',              // Reemplazar caracteres no permitidos con guión
                '-',              // Un solo guión para múltiples guiones
                ''                // Eliminar guiones al inicio y final
            ], $normalized);
            
            // Asegurar que no esté vacío
            $result = $cleaned ?: 'categoria';
            
            Log::debug("Generación de URL amigable", [
                'original' => $name,
                'normalized' => $result
            ]);
            
            return $result;
        } catch (\Exception $e) {
            Log::error("Error al generar link_rewrite", [
                'name' => $name,
                'error' => $e->getMessage()
            ]);
            
            // Fallback simple
            return 'categoria-' . substr(md5($name), 0, 5);
        }
    }

    /**
     * Obtiene el ID de categoría de PrestaShop a partir del ID de API
     *
     * @param string|int $apiId ID de la categoría en el API
     * @return int|null ID de la categoría en PrestaShop o null si no existe
     */
    public static function getIdByApiId($apiId): ?int
    {
        try {
            if (empty($apiId)) {
                return null;
            }
            
            // Convertir a string para la consulta SQL
            $apiIdString = is_int($apiId) ? (string)$apiId : $apiId;
            
            $result = Db::getInstance()->getValue(
                "SELECT id_category FROM " . _DB_PREFIX_ . 'category WHERE api_id="' . pSQL($apiIdString) . '"'
            );
            
            return $result ? (int)$result : null;
        } catch (\Exception $e) {
            Log::error("Error al buscar categoría por api_id", [
                'api_id' => $apiId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Asigna un ID de API a una categoría de PrestaShop
     *
     * @param string|int $categoryId ID de la categoría en PrestaShop
     * @param string $apiId ID de la categoría en el API
     * @return bool Éxito o fracaso
     */
    public static function addId($categoryId, string $apiId): bool
    {
        try {
            $result = Db::getInstance()->execute(
                "UPDATE " . _DB_PREFIX_ . 'category SET api_id="' . pSQL($apiId) . '" WHERE id_category=' . (int)$categoryId
            );
            
            if ($result) {
                Log::debug("API ID asignado a categoría", [
                    'category_id' => $categoryId,
                    'api_id' => $apiId
                ]);
            } else {
                Log::warning("No se pudo asignar API ID a categoría", [
                    'category_id' => $categoryId,
                    'api_id' => $apiId
                ]);
            }
            
            return $result;
        } catch (\Exception $e) {
            Log::error("Error al asignar API ID a categoría", [
                'category_id' => $categoryId,
                'api_id' => $apiId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }


    /**
     * Elimina acentos y caracteres especiales de una cadena
     * 
     * @param string $str Cadena de entrada
     * @return string Cadena sin acentos ni caracteres especiales
     */
    public static function removeAccents($str): string
    {
        $replacements = [
            ['Á', 'À', 'Â', 'Ä', 'á', 'à', 'ä', 'â', 'Ã', 'ã'],
            ['A', 'A', 'A', 'A', 'a', 'a', 'a', 'a', 'A', 'a'],
            ['É', 'È', 'Ê', 'Ë', 'é', 'è', 'ë', 'ê'],
            ['E', 'E', 'E', 'E', 'e', 'e', 'e', 'e'],
            ['Í', 'Ì', 'Ï', 'Î', 'í', 'ì', 'ï', 'î'],
            ['I', 'I', 'I', 'I', 'i', 'i', 'i', 'i'],
            ['Ó', 'Ò', 'Ö', 'Ô', 'ó', 'ò', 'ö', 'ô', 'Õ', 'õ'],
            ['O', 'O', 'O', 'O', 'o', 'o', 'o', 'o', 'O', 'o'],
            ['Ú', 'Ù', 'Û', 'Ü', 'ú', 'ù', 'ü', 'û'],
            ['U', 'U', 'U', 'U', 'u', 'u', 'u', 'u'],
            ['Ñ', 'ñ'],
            ['N', 'n'],
            ['Ç', 'ç'],
            ['C', 'c']
        ];
        
        for ($i = 0; $i < count($replacements); $i += 2) {
            $str = str_replace($replacements[$i], $replacements[$i+1], $str);
        }
        
        return $str;
    }
}
