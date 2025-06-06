<?php
declare(strict_types=1);

namespace PedidosInternet\Controller;

use PedidosInternet\Log;
use PedidosInternet\PedidosApi;
use PedidosInternet\Dto\DtoCategory;
use PedidosInternet\PrestashopApi;
use Symfony\Component\HttpFoundation\JsonResponse;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;

/**
 * Controlador para la sincronización de datos con el ERP Distrib
 * 
 * Se encarga de gestionar todas las operaciones de sincronización
 * entre la tienda y el ERP, incluyendo clientes, categorías,
 * productos, tarifas, etc.
 */
class StateController extends FrameworkBundleAdminController
{
    // IDs de taxonomías que se sincronizan como categorías
    private const TAXONOMY_IDS_TO_SYNC = [4, 15, 18];

     /**
     * Inicializa el API asegurando que está disponible
     * 
     * @return PedidosApi|null La instancia del API o null si hay error
     */
    private static function initializeApi(): ?PedidosApi
    {
        try {

            $pedidosApi = PedidosApi::create();

            if (empty($pedidosApi)) {
                Log::error("No se ha podido crear la instancia de PedidosApi");
                return null;
            }

            return $pedidosApi;

        } catch (\Exception $e) {
            
            Log::error("Error al inicializar la API", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return null;
        }
    }

    /**
     * Genera una respuesta de error estándar
     * 
     * @param string $message Mensaje de error
     * @param int $statusCode Código HTTP de error
     * @return JsonResponse
     */
    private static function errorResponse(string $message, int $statusCode = 500): JsonResponse
    {
        return new JsonResponse(["error" => $message], $statusCode);
    }

    /**
     * Sincroniza los clientes con el ERP
     * 
     * @return JsonResponse
     */
    public function checkSynchronizationClients(): JsonResponse
    {

        $pedidosApi = self::initializeApi();

        if (!$pedidosApi) {
            return self::errorResponse("No se ha podido acceder al API");
        }

        try {
            $syncClientsRole2 = $pedidosApi->getClientsByRole("2");
            $errorsSaving = [];

            foreach ($syncClientsRole2 as $client) {

                try {

                    $idOrError = PrestashopApi::createOrUpdateClient($client);

                    if (is_string($idOrError)) {

                        $errorsSaving[] = [
                            'id' => $client->id,
                            'error' => $idOrError,
                            'email' => $client->email,
                            'name' => $client->tradeName,
                        ];

                    }

                } catch (\Exception $e) {

                    $errorsSaving[] = [
                        'id' => $client->id,
                        'error' => $e->getMessage(),
                        'email' => $client->email ?? 'unknown',
                        'name' => $client->tradeName ?? 'unknown',
                    ];

                    Log::error("Error al procesar cliente", [
                        'client_id' => $client->id,
                        'error' => $e->getMessage()
                    ]);

                }
            }

            return new JsonResponse([
                "status" => "success", 
                "errors" => $errorsSaving,
                "total_processed" => count($syncClientsRole2),
                "total_errors" => count($errorsSaving)
            ]);

        } catch (\Exception $e) {
            Log::error("Error en sincronizacion de clientes", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse("Error al sincronizar clientes: " . $e->getMessage());
        }
    }

    /**
     * Sincroniza las categorías con el ERP
     * 
     * @return JsonResponse
     */
    public function checkSynchronizationCategories(): JsonResponse
    {
        $startTime = new \DateTimeImmutable();
        
        $pedidosApi = $this->initializeApi();
        if (!$pedidosApi) {
            return $this->errorResponse("No se ha podido acceder al API");
        }

        try {
            // Obtener taxonomías del API
            $syncInfoTaxonomies = $pedidosApi->getTaxonomies();
            
            if (!is_array($syncInfoTaxonomies)) {
                Log::error("La respuesta de taxonomías no es un array válido", [
                    'response_type' => gettype($syncInfoTaxonomies)
                ]);
                return $this->errorResponse("La respuesta de taxonomías no es válida");
            }
            
            // Filtrar solo las taxonomías requeridas
            $requiredTaxonomies = array_filter($syncInfoTaxonomies, function($taxonomy) {
                return in_array((int)$taxonomy['id'], self::TAXONOMY_IDS_TO_SYNC);
            });
            
            if (empty($requiredTaxonomies)) {
                Log::warning("No se encontraron taxonomías con los IDs requeridos", [
                    'required_ids' => self::TAXONOMY_IDS_TO_SYNC,
                    'available_ids' => array_column($syncInfoTaxonomies, 'id')
                ]);
            }
            
            $langDefault = (int)\Configuration::get('PS_LANG_DEFAULT');
            $homeCategory = (int)\Configuration::get('PS_HOME_CATEGORY');
            
            // Estadísticas de procesamiento
            $stats = [
                'total_taxonomies' => count($requiredTaxonomies),
                'created_parent_categories' => 0,
                'created_child_categories' => 0,
                'updated_categories' => 0,
                'errors' => 0
            ];
            
            // Procesar taxonomías padres
            foreach ($requiredTaxonomies as $taxonomy) {
                try {
                    // Asegurarnos de que el ID sea entero
                    $taxonomyId = (int)$taxonomy['id'];
                    
                    // Crear o actualizar categoría padre
                    $parentCategory = DtoCategory::createCategoryWithTaxonomy(
                        $langDefault, 
                        $taxonomy,
                        $homeCategory,
                        true // Es categoría padre
                    );
                    
                    if (!$parentCategory) {
                        Log::warning("No se pudo crear/actualizar la categoría padre", [
                            'taxonomy_id' => $taxonomyId,
                            'description' => $taxonomy['description'] ?? 'sin descripción'
                        ]);
                        $stats['errors']++;
                        continue;
                    }
                    
                    $stats['created_parent_categories']++;
                    
                    // Procesar términos hijos si existen
                    if (!empty($taxonomy['terms'])) {
                        $processedChildrenCount = $this->processChildTaxonomies(
                            $langDefault,
                            $taxonomy['terms'],
                            (int)$parentCategory->id,  // Asegurarnos de que sea entero
                            $taxonomyId  // Ya lo convertimos a entero arriba
                        );
                        
                        $stats['created_child_categories'] += $processedChildrenCount['created'];
                        $stats['updated_categories'] += $processedChildrenCount['updated'];
                        $stats['errors'] += $processedChildrenCount['errors'];
                    }
                } catch (\Exception $e) {
                    Log::error("Error al procesar taxonomía padre", [
                        'taxonomy_id' => $taxonomy['id'],
                        'description' => $taxonomy['description'] ?? 'sin descripción',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $stats['errors']++;
                }
            }
            
            $endTime = new \DateTimeImmutable();
            $duration = $endTime->getTimestamp() - $startTime->getTimestamp();
            
            Log::info("Sincronizacion de categorias completada", [
                'duration_seconds' => $duration,
                'stats' => $stats
            ]);
            
            return new JsonResponse([
                "status" => "success",
                "message" => "Categorias sincronizadas correctamente",
                "stats" => $stats,
                "duration_seconds" => $duration
            ]);

        } catch (\Exception $e) {
            Log::error("Error en sincronizacion de categorias", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse("Error al sincronizar categorías: " . $e->getMessage());
        }
    }

    /**
     * Procesa las taxonomías hijas y crea/actualiza las subcategorías correspondientes
     *
     * @param int $langDefault ID del idioma por defecto
     * @param array $terms Términos hijos de la taxonomía
     * @param int $parentCategoryId ID de la categoría padre
     * @param int $parentTaxonomyId ID de la taxonomía padre
     * @return array Estadísticas del procesamiento
     */
    private function processChildTaxonomies(int $langDefault, array $terms, int $parentCategoryId, int $parentTaxonomyId): array
    {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'errors' => 0
        ];
        
        foreach ($terms as $term) {
            try {
                // Aseguramos que el ID del término sea un entero
                $termId = isset($term['id']) ? (int)$term['id'] : 0;
                
                // Crear un ID único para la categoría hija combinando IDs
                $uniqueApiId = $parentTaxonomyId . '-' . $termId;
                
                // Verificar si la categoría ya existe
                $existingCategoryId = DtoCategory::getIdByApiId($uniqueApiId);
                
                if ($existingCategoryId) {
                    // Actualizar categoría existente
                    $category = new \Category($existingCategoryId);
                    if (\Validate::isLoadedObject($category)) {
                        $category->name[$langDefault] = $term['description'] ?? '';
                        $category->update();
                        $stats['updated']++;
                    }
                } else {
                    // Crear nueva categoría hija
                    $termData = $term;
                    $termData['unique_id'] = $uniqueApiId; // Pasamos el ID único
                    
                    $childCategory = DtoCategory::createCategoryWithTaxonomy(
                        $langDefault,
                        $termData,
                        $parentCategoryId,
                        false // No es categoría padre
                    );
                    
                    if ($childCategory) {
                        $stats['created']++;
                        
                        // Procesar términos nietos si existen
                        if (!empty($term['childrenTerms'])) {
                            $childStats = $this->processChildTaxonomies(
                                $langDefault,
                                $term['childrenTerms'],
                                (int)$childCategory->id, // Asegurarnos de que sea entero
                                $parentTaxonomyId // Usamos el mismo ID padre para mantener consistencia
                            );
                            
                            $stats['created'] += $childStats['created'];
                            $stats['updated'] += $childStats['updated'];
                            $stats['errors'] += $childStats['errors'];
                        }
                    } else {
                        $stats['errors']++;
                        Log::warning("No se pudo crear la categoría hija", [
                            'term_id' => $termId,
                            'unique_id' => $uniqueApiId,
                            'description' => $term['description'] ?? 'sin descripción'
                        ]);
                    }
                }
            } catch (\Exception $e) {
                $stats['errors']++;
                Log::error("Error al procesar término hijo", [
                    'term_id' => $term['id'] ?? 'desconocido',
                    'parent_id' => $parentCategoryId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $stats;
    }

    /**
     * Sincroniza los productos con el ERP
     * 
     * @return JsonResponse
     */
    public function checkSynchronizationProducts(): JsonResponse
    {

        $initialTime = new \DateTimeImmutable();
        
        $pedidosApi = self::initializeApi();
        if (!$pedidosApi) {
            return self::errorResponse("No se ha podido acceder al API");
        }

        try {
            $result = $pedidosApi->getWebProducts();
            $endTime = new \DateTimeImmutable();
            
            // Registramos la información de la sincronización
            Log::info("Sincronizacion de productos completada", [
                'duration_seconds' => $endTime->getTimestamp() - $initialTime->getTimestamp(),
                'total_products' => $result['total'] ?? 0,
                'success_count' => count($result['success'] ?? []),
                'error_count' => count($result['errors'] ?? [])
            ]);
            
            if (!empty($result['errors'])) {
                Log::warning("Errores en sincronizacion de productos", [
                    'errors' => $result['errors']
                ]);
            }
            
            return new JsonResponse([
                "status" => "success",
                "errors" => $result['errors'] ?? [],
                "total_products" => $result['total'] ?? 0,
                "success_count" => count($result['success'] ?? []),
                "error_count" => count($result['errors'] ?? [])
            ]);
        } catch (\Exception $e) {
            Log::error("Error en sincronizacion de productos", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse("Error al sincronizar productos: " . $e->getMessage());
        }
    }

     /**
     * Sincroniza las tarifas con el ERP
     * 
     * @return JsonResponse
     */
    public function checkSynchronizationRates(): JsonResponse
    {
        
        $pedidosApi = $this->initializeApi();
        if (!$pedidosApi) {
            return $this->errorResponse("No se ha podido acceder al API");
        }

        try {
            $result = $pedidosApi->getRates();
            
            Log::info("Sincronizacion de tarifas completada", [
                'result' => $result
            ]);
            
            return new JsonResponse([
                "status" => "success",
                "message" => "Tarifas sincronizadas correctamente"
            ]);
        } catch (\Exception $e) {
            Log::error("Error en sincronizacion de tarifas", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse("Error al sincronizar tarifas: " . $e->getMessage());
        }
    }

    /**
     * Sincroniza las plantillas web con el ERP
     * 
     * @return JsonResponse
     */
    public function checkSynchronizationWebTemplates(): JsonResponse
    {
        
        $pedidosApi = $this->initializeApi();
        if (!$pedidosApi) {
            return $this->errorResponse("No se ha podido acceder al API");
        }

        try {
            $result = $pedidosApi->getWebTemplates();
            
            Log::info("Sincronizacion de plantillas web completada", [
                'result' => $result
            ]);
            
            return new JsonResponse([
                "status" => "success",
                "message" => "Plantillas web sincronizadas correctamente"
            ]);

        } catch (\Exception $e) {

            Log::error("Error en sincronizacion de plantillas web", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse("Error al sincronizar plantillas web: " . $e->getMessage());
        
        }
    }

    /**
     * Sincroniza los valores de plantilla de productos con el ERP
     * 
     * @return JsonResponse
     */
    public function checkSynchronizationProductTemplateValues(): JsonResponse
    {
        
        $pedidosApi = $this->initializeApi();
        if (!$pedidosApi) {
            return $this->errorResponse("No se ha podido acceder al API");
        }

        try {
            $result = $pedidosApi->getProductTemplateValues();
            
            Log::info("Sincronizacion de valores de plantilla de productos completada", [
                'result' => $result
            ]);
            
            return new JsonResponse([
                "status" => "success",
                "message" => "Valores de plantilla de productos sincronizados correctamente"
            ]);

        } catch (\Exception $e) {

            Log::error("Error en sincronizacion de valores de plantilla de productos", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse("Error al sincronizar valores de plantilla de productos: " . $e->getMessage());
        }
    }

    /**
     * Asigna categorías con plantillas
     * 
     * @return JsonResponse
     */
    public function checkSynchronizationAsignCategoriesWithTemplates(): JsonResponse
    {
        
        $initialTime = new \DateTimeImmutable();
        
        $pedidosApi = $this->initializeApi();
        if (!$pedidosApi) {
            return $this->errorResponse("No se ha podido acceder al API");
        }

        try {
            
            $result = $pedidosApi->asignCategoriesWithTemplates();
            $endTime = new \DateTimeImmutable();
            
            Log::info("Asignacion de categorías con plantillas completada", [
                'duration_seconds' => $endTime->getTimestamp() - $initialTime->getTimestamp()
            ]);
            
            return new JsonResponse([
                "status" => "success",
                "message" => "Categorias asignadas con plantillas correctamente",
                "duration_seconds" => $endTime->getTimestamp() - $initialTime->getTimestamp()
            ]);
        } catch (\Exception $e) {
            Log::error("Error en asignacion de categorías con plantillas", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse("Error al asignar categorías con plantillas: " . $e->getMessage());
        }
    }


    /**
     * Sincroniza las marcas con el ERP
     * 
     * @return JsonResponse
     */
    public function checkSynchronizationBrands(): JsonResponse
    {
        
        $pedidosApi = $this->initializeApi();
        if (!$pedidosApi) {
            return $this->errorResponse("No se ha podido acceder al API | checkSynchronizationBrands");
        }

        try {
            $result = $pedidosApi->asignBrandsToProduct();

            return new JsonResponse([
                "status" => "success",
                "message" => "Marcas sincronizadas correctamente"
            ]);
        } catch (\Exception $e) {
            Log::error("Error en sincronizacion de marcas", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse("Error al sincronizar marcas: " . $e->getMessage());
        }
    }

    /**
     * Elimina registros de log
     * 
     * @return JsonResponse
     */
    public function DeleteLog(): JsonResponse
    {
        try {
            Log::delete();
            return new JsonResponse([
                "status" => "success",
                "message" => "Logs eliminados correctamente"
            ]);
        } catch (\Exception $e) {
            Log::error("Error al eliminar logs", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse("Error al eliminar logs: " . $e->getMessage());
        }
    }

}
