<?php
declare(strict_types=1);

namespace PedidosInternet\Dto;

use PedidosInternet\Log;
use PrestaShop\PrestaShop\Adapter\Entity\Db;
use PrestaShop\PrestaShop\Adapter\Entity\Product;
use PrestaShop\PrestaShop\Adapter\Entity\Category;
// Cambiamos la importación para usar la clase completa
// La clase Transliterator está en el namespace global

/**
 * DTO para representar productos
 * 
 * Facilita la conversión entre el modelo de producto de PrestaShop
 * y el modelo de Distrib
 */
class DtoWebProduct
{
    public string $reference = '';
    public string $description = '';
    public float $unitsPerBox = 0.0;
    public float $kilosPerUnity = 0.0;
    public float $subUnits = 0.0;
    public bool $isNovelty = false;
    public array $formats = [];
    public string $shippingFormat = '';
    public string $webFamilyId = '';
    public ?string $webSubFamilyId = null;
    public ?string $webSubSubFamilyId = null;
    public int $taxTypeId = 0;
    public float $taxPercentage = 0.0;
    public int $groupId = 0;
    public int $subGroupId = 0;
    public int $providerId = 0;
    public int $sectionId = 0;
    public int $brandId = 0;
    public array $imagesInformation = [];
    public string $iva = '';
    public int $quantity = 0;
    public float $price = 0.0;

    /**
     * Genera una URL amigable a partir de un texto
     * 
     * @param string $text El texto a convertir
     * @return string La URL amigable
     */
    private static function generateSafeUrl(string $text): string
    {
        try {
			
            // Eliminar caracteres especiales y convertir a minúsculas
			$cleanedText = trim(preg_replace('/[*\'"()[\]{}]/', '', $text));

			// Si está disponible la extensión intl, usarla para mejor manejo de caracteres internacionales
			if (extension_loaded('intl') && class_exists('\Transliterator')) {
				$transliterator = \Transliterator::createFromRules(
					':: Any-Latin; :: Latin-ASCII; :: NFD; :: [:Nonspacing Mark:] Remove; :: Lower(); :: NFC;',
					\Transliterator::FORWARD
				);

				$normalized = $transliterator->transliterate($cleanedText);
			} else {
				// Alternativa si intl no está disponible
				$normalized = self::fallbackUrlGenerator($cleanedText);
			}

			// Aplicar limpieza estricta para garantizar URL válida
			$url = preg_replace('/[^a-z0-9\-]/', '-', strtolower($normalized));
			$url = preg_replace('/-+/', '-', $url); // Reemplazar múltiples guiones por uno solo
			$url = trim($url, '-'); // Eliminar guiones al inicio y final

			// Verificar que la URL no esté vacía
			if (empty($url)) {
				// Crear una URL basada en la referencia del producto como último recurso
				return 'producto-' . substr(md5($text), 0, 8);
			}

			return $url;
			
        } catch (\Exception $e) {
            // En caso de error, generar una URL segura basada en hash
			$safeUrl = 'producto-' . substr(md5($text), 0, 8);

			// Registrar el error pero no interrumpir el proceso
			Log::warning("Error al generar URL amigable, usando alternativa", [
				'original_text' => $text,
				'generated_url' => $safeUrl,
				'error' => $e->getMessage()
			]);

			return $safeUrl;
        }
    }
    
    /**
	 * Método alternativo para generar URLs amigables
	 *
	 * @param string $text El texto a convertir
	 * @return string La URL amigable
	 */
    private static function fallbackUrlGenerator(string $text): string
	{
		// Eliminar acentos y caracteres especiales
		$text = htmlentities($text, ENT_QUOTES, 'UTF-8');
		$text = preg_replace('~&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml);~i', '$1', $text);
		$text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

		// Reemplazar espacios y caracteres no deseados con guiones
		$text = preg_replace('/[^a-zA-Z0-9]+/', '-', $text);
		$text = strtolower(trim($text, '-'));

		// Asegurar que solo haya caracteres permitidos
		$text = preg_replace('/[^a-z0-9-]/', '', $text);

		// Verificar que no esté vacío
		return !empty($text) ? $text : 'producto';
	}

    /**
     * Crea un objeto DtoWebProduct a partir de datos del API
     * 
     * @param array $webProduct Datos del producto desde el API
     * @return DtoWebProduct|null El objeto creado o null si no cumple requisitos mínimos
     */
    public static function create(array $webProduct): ?DtoWebProduct
    {
        try {
            // Validaciones básicas
            if (empty($webProduct['reference'])) {
                Log::warning("Producto sin referencia", [
                    'description' => $webProduct['description'] ?? 'sin descripción'
                ]);
                return null;
            }

            $toRet = new DtoWebProduct();
            $toRet->reference = $webProduct['reference'];
            $toRet->description = $webProduct['description'] ?? '';
            $toRet->unitsPerBox = (float)($webProduct['unitsPerBox'] ?? 0.0);
            $toRet->kilosPerUnity = (float)($webProduct['kilosPerUnity'] ?? 0.0);
            $toRet->subUnits = (float)($webProduct['subUnits'] ?? 0.0);
            $toRet->isNovelty = $webProduct['isNovelty'] ?? false;

            // Determinar familia del producto
            if (!empty($webProduct['webSubSubFamilyId'])) {
                $toRet->webFamilyId = $webProduct['webSubSubFamilyId'];
                $toRet->webSubSubFamilyId = $webProduct['webSubSubFamilyId'];
                $toRet->webSubFamilyId = $webProduct['webSubFamilyId'] ?? null;
            } elseif (!empty($webProduct['webSubFamilyId'])) {
                $toRet->webFamilyId = $webProduct['webSubFamilyId'];
                $toRet->webSubFamilyId = $webProduct['webSubFamilyId'];
            } else {
                $toRet->webFamilyId = $webProduct['webFamilyId'] ?? '';
            }

            // Verificar que el producto tenga familia asignada
            if (empty($toRet->webFamilyId)) {
                Log::warning("Producto sin familia asignada", [
                    'reference' => $toRet->reference,
                    'description' => $toRet->description
                ]);
                return null;
            }

            $toRet->taxTypeId = (int)($webProduct['taxTypeId'] ?? 0);
            $toRet->taxPercentage = (float)($webProduct['taxPercentage'] ?? 0.0);
            $toRet->groupId = (int)($webProduct['groupId'] ?? 0);
            $toRet->subGroupId = (int)($webProduct['subGroupId'] ?? 0);
            $toRet->providerId = (int)($webProduct['providerId'] ?? 0);
            $toRet->sectionId = (int)($webProduct['sectionId'] ?? 0);
            $toRet->brandId = (int)($webProduct['brandId'] ?? 0);
            $toRet->iva = $webProduct['iva'] ?? '';

            // Procesar imágenes
            $toRet->imagesInformation = [];
            if (!empty($webProduct['imagesInformation']) && is_array($webProduct['imagesInformation'])) {
                foreach ($webProduct['imagesInformation'] as $imageInformation) {
                    if (!isset($imageInformation['id']) || !isset($imageInformation['sendToWeb'])) {
                        continue;
                    }
                    
                    $imageData = [
                        'id' => (int)$imageInformation['id'],
                        'sendToWeb' => (bool)$imageInformation['sendToWeb']
                    ];
                    $toRet->imagesInformation[] = $imageData;
                }
            }

            // Procesar formatos
            $toRet->formats = [];
            if (!empty($webProduct['formats']) && is_array($webProduct['formats'])) {
                foreach ($webProduct['formats'] as $format) {
                    if (!isset($format['formatId'])) {
                        continue;
                    }
                    
                    $toRet->formats[$format['formatId']] = [
                        'description' => $format['description'] ?? '',
                        'quantity' => (float)($format['quantityMultiplier'] ?? 1.0),
                        'kilos' => (float)($format['kilosMultiplier'] ?? 0.0),
                        'default' => (bool)($format['isDefaultFormatForProduct'] ?? false),
                    ];
                }
            }

            return $toRet;
        } catch (\Exception $e) {
            Log::error("Error al crear producto", [
                'reference' => $webProduct['reference'] ?? 'desconocida',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Convierte el DTO a un array para enviar al API
     * 
     * @param bool $encodeAsJson Si la respuesta debe ser JSON o array
     * @return array Datos formateados para el API
     */
    public function toApiArray(bool $encodeAsJson = true): array
    {
        try {
            $result = [
                "reference" => $this->reference,
                "description" => $this->description,
                "unitsPerBox" => $this->unitsPerBox,
                "kilosPerUnity" => $this->kilosPerUnity,
                "subUnits" => $this->subUnits,
                "isNovelty" => $this->isNovelty,
                "formats" => $encodeAsJson ? json_encode($this->formats) : $this->formats,
                "shippingFormat" => $this->shippingFormat,
                "webFamilyId" => $this->webFamilyId,
                "webSubFamilyId" => $this->webSubFamilyId,
                "webSubSubFamilyId" => $this->webSubSubFamilyId,
                "taxTypeId" => $this->taxTypeId,
                "taxPercentage" => $this->taxPercentage,
                "groupId" => $this->groupId,
                "providerId" => $this->providerId,
                "brandId" => $this->brandId,
                "imagesInformation" => $encodeAsJson ? json_encode($this->imagesInformation) : $this->imagesInformation,
                "iva" => $this->iva
            ];
            
            return $result;
        } catch (\Exception $e) {
            Log::error("Error al convertir producto a array para API", [
                'reference' => $this->reference,
                'error' => $e->getMessage()
            ]);
            
            // Datos mínimos en caso de error
            return [
                "reference" => $this->reference,
                "description" => $this->description,
                "unitsPerBox" => 0.0,
                "kilosPerUnity" => 0.0,
                "webFamilyId" => $this->webFamilyId
            ];
        }
    }

    /**
     * Crea o actualiza un producto en PrestaShop
     * 
     * @return Product|null El producto creado/actualizado o null si hay error
     */
    public function createOrSaveProduct(): ?Product
    {
        try {
            $langDefault = (int)\Configuration::get('PS_LANG_DEFAULT');
            $productId = 0;
            $addProduct = false;

            // Buscar si el producto ya existe
            $productFounded = self::getProductByReference($this->reference);

            if (empty($productFounded)) {
                // Crear nuevo producto
                $product = new Product(null, false, $langDefault);
                $addProduct = true;
                $product->reference = $this->reference;
                $product->name = [$langDefault => htmlspecialchars($this->description)];
                
            } else {
                // Actualizar producto existente
                $product = new Product($productFounded[0]['id_product'], false, $langDefault);
        
            }

            // Obtener categoría por ID de API
            $categoryId = DtoCategory::getIdByApiId($this->webFamilyId);
            
            // Asignar categoría por defecto o la encontrada
            $product->id_category_default = $categoryId ?: 6; // Categoría 6 como fallback
            
            // Configurar propiedades del producto
            $product->redirect_type = '301';
            
            if (empty($product->price)) {
                $product->price = 0;
            }
            
            $product->minimal_quantity = 1;
            $product->show_price = 1;
            $product->on_sale = 0;
            $product->online_only = 0;
            $product->meta_description = '';
            $product->id_tax_rules_group = 1;
            $product->weight = $this->kilosPerUnity;

            // Si es un producto nuevo, generar URL amigable
            if ($addProduct) {
                $url = self::generateSafeUrl($this->description);
                $product->link_rewrite = [$langDefault => $url];
                
                // Normalizar el nombre del producto
                $cleanName = self::cleanProductName($this->description);
                if ($cleanName !== $this->description) {
                    $product->name[$langDefault] = $cleanName;
                }
            }
            
            // Guardar producto
            if ($addProduct) {
                $saved = $product->add();
            } else {
                $saved = $product->save();
            }

            if (!$saved) {
                Log::error("Error al guardar producto", [
                    'reference' => $this->reference,
                    'description' => $this->description
                ]);
                return null;
            }

            // Asignar categorías al producto
            $categories = [
                new Category((int)($categoryId ?: 6)) // Categoría por defecto si no se encuentra
            ];

            // Añadir subcategorías si existen
            $subCategoryId = DtoCategory::getIdByApiId($this->webSubFamilyId ?? '');
            if (!empty($subCategoryId)) {
                $categories[] = new Category((int)$subCategoryId);
            }

            $subSubCategoryId = DtoCategory::getIdByApiId($this->webSubSubFamilyId ?? '');
            if (!empty($subSubCategoryId)) {
                $categories[] = new Category((int)$subSubCategoryId);
            }
            
            // Convertir array de objetos en array de IDs
            $categoryIds = array_map(function($category) {
                return $category->id;
            }, $categories);
            
            // Asignar categorías
            $product->addToCategories($categoryIds);

            return $product;
        } catch (\Exception $e) {
            Log::error("Error al crear/actualizar producto", [
                'reference' => $this->reference,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
    
    /**
     * Limpia el nombre del producto de caracteres no deseados
     * 
     * @param string $name Nombre original
     * @return string Nombre limpio
     */
    private static function cleanProductName(string $name): string
    {
        $cleanName = $name;
        
        // Eliminar caracteres no deseados
        $cleanName = str_replace(
            [",", "/", "+", "(", ")", "´", "'", "_", "&amp", ";"],
            ["", "", "", "", "", "", "", "", "&", ""],
            $cleanName
        );
        
        // Convertir a formato título (primera letra de cada palabra en mayúscula)
        $cleanName = ucwords(strtolower($cleanName), " ");
        
        return $cleanName;
    }

    /**
     * Actualiza el precio de un producto
     * 
     * @param string $reference Referencia del producto
     * @param float $price Precio del producto
     * @param bool $isPvp Si el precio incluye IVA
     * @return bool Resultado de la operación
     */
    public static function updatePrice(string $reference, float $price, bool $isPvp): bool
    {
        try {
            // Si el precio incluye IVA, calcularlo sin IVA para almacenarlo
            if ($isPvp) {
                $price /= 1.21; // Factor de IVA por defecto
            }
            
            // Actualizar precio en tabla product
            $result1 = Db::getInstance()->execute(
                'UPDATE `'. _DB_PREFIX_ . "product` 
                SET `price` = " . (float)$price . ",
                `id_tax_rules_group` = 1 
                WHERE `reference` = '" . pSQL($reference) . "'"
            );
            
            // Actualizar precio en tabla product_shop
            $result2 = Db::getInstance()->execute(
                "UPDATE " . _DB_PREFIX_ . "product_shop ps
                LEFT JOIN " . _DB_PREFIX_ . "product p 
                ON ps.id_product = p.id_product
                SET ps.price = " . (float)$price . "
                WHERE p.reference = '" . pSQL($reference) . "'"
            );
            
            $success = ($result1 && $result2);
            
            if (!$success) {
                Log::warning("No se pudo actualizar el precio del producto", [
                    'reference' => $reference,
                    'price' => $price,
                    'result_product' => $result1,
                    'result_shop' => $result2
                ]);
            }
            
            return $success;

        } catch (\Exception $e) {
            Log::error("Error al actualizar precio de producto", [
                'reference' => $reference,
                'price' => $price,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Asigna un producto a una categoría de precio
     * 
     * @param string $reference Referencia del producto
     * @param float $price Precio del producto
     * @param bool $isPvp Si el precio incluye IVA
     * @return bool Resultado de la operación
     */
    public static function asignProductToPriceCategory(string $reference, float $price, bool $isPvp): bool
    {
        try {
            // Calcular precio con IVA si no lo incluye
            $priceWithTax = $isPvp ? $price : ($price * 1.21);
            
            // Buscar producto por referencia
            $product = self::getProductByReference($reference);
            
            if (empty($product)) {
                return false;
            }
            
            $productObj = new Product($product[0]['id_product']);
            
            if (!$productObj->id) {
                Log::warning("Producto inválido al intentar asignar a categoría de precio", [
                    'reference' => $reference,
                    'product_id' => $product[0]['id_product']
                ]);
                return false;
            }
            
            // Limpiar categorías de precio anteriores
            self::cleanPriceCategories($productObj);
            
            // Asignar a categoría de precio según rango
            $categoryAssigned = true;
            if ($priceWithTax > 0 && $priceWithTax < 10) {
                $productObj->addToCategories([145, 146, 147, 148]);
            } elseif ($priceWithTax > 0 && $priceWithTax < 15) {
                $productObj->addToCategories([146, 147, 148]);
            } elseif ($priceWithTax > 0 && $priceWithTax < 20) {
                $productObj->addToCategories([147, 148]);
            } elseif ($priceWithTax > 0 && $priceWithTax < 30) {
                $productObj->addToCategories([148]);
            } elseif ($priceWithTax >= 30 && $priceWithTax <= 50) {
                $productObj->addToCategories([149]);
            } else {
                $productObj->addToCategories([150]);
            }
            
            return $categoryAssigned;
        } catch (\Exception $e) {
            Log::error("Error al asignar producto a categoría de precio", [
                'reference' => $reference,
                'price' => $price,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Elimina las categorías de precio de un producto
     * 
     * @param Product $product Producto a limpiar
     * @return bool Resultado de la operación
     */
    public static function cleanPriceCategories(Product $product): bool
    {
        try {
            $priceCategoryIds = [145, 146, 147, 148, 149, 150];
            $categories = $product->getCategories();
            
            $success = true;
            foreach ($categories as $categoryId) {
                if (in_array($categoryId, $priceCategoryIds)) {
                    $success = $success && $product->deleteCategory($categoryId);
                }
            }
            
            return $success;
        } catch (\Exception $e) {
            Log::error("Error al limpiar categorías de precio", [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Busca un producto por su referencia
     * 
     * @param string $productReference Referencia del producto
     * @return array|false Datos del producto o false si no existe
     */
    public static function getProductByReference(string $productReference)
    {
        try {
            $productReferenceConverted = pSQL($productReference);
            $sql = 'SELECT * FROM `'. _DB_PREFIX_ . "product` WHERE `reference` = '{$productReferenceConverted}'";
            $result = Db::getInstance()->ExecuteS($sql);
            
            return $result;
        } catch (\Exception $e) {
            Log::error("Error al buscar producto por referencia", [
                'reference' => $productReference,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Actualiza el nombre de un producto
     * 
     * @param int $product_id ID del producto
     * @param string $name Nuevo nombre
     * @return bool Resultado de la operación
     */
    public static function updateProductName(int $product_id, string $name): bool
    {
        try {
            $langDefault = (int)\Configuration::get('PS_LANG_DEFAULT');
            $product = new Product($product_id, false, $langDefault);
            
            if (!\Validate::isLoadedObject($product)) {
                Log::warning("Producto no encontrado al actualizar nombre", [
                    'product_id' => $product_id
                ]);
                return false;
            }
            
            $product->name = [$langDefault => $name];
            $result = (bool)$product->save();
            
            if (!$result) {
                Log::warning("No se pudo actualizar el nombre del producto", [
                    'product_id' => $product_id,
                    'new_name' => $name
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error("Error al actualizar nombre de producto", [
                'product_id' => $product_id,
                'new_name' => $name,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Actualiza la descripción corta de un producto
     * 
     * @param int $product_id ID del producto
     * @param string $shortDescription Nueva descripción corta
     * @return bool Resultado de la operación
     */
    public static function updateProductShortDescription(int $product_id, string $shortDescription): bool
    {
        try {
            $langDefault = (int)\Configuration::get('PS_LANG_DEFAULT');
            $product = new Product($product_id, false, $langDefault);
            
            if (!\Validate::isLoadedObject($product)) {
                Log::warning("Producto no encontrado al actualizar descripción corta", [
                    'product_id' => $product_id
                ]);
                return false;
            }
            
            $product->description_short = [$langDefault => $shortDescription];
            $result = (bool)$product->save();
            
            if (!$result) {
                Log::warning("No se pudo actualizar la descripción corta del producto", [
                    'product_id' => $product_id
                ]);
            }
            
            return $result;
        } catch (\Exception $e) {
            Log::error("Error al actualizar descripción corta de producto", [
                'product_id' => $product_id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}