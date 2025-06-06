<?php
declare(strict_types=1);


namespace PedidosInternet;

use PrestaShopLogger;
use Configuration;
use Hook;
use ImageManager;
use ImageType;
use PedidosInternet\Dto\DtoAccessToken;
use PedidosInternet\Dto\DtoCliente;
use PedidosInternet\Dto\DtoOrderNote;
use PedidosInternet\Helpers\CurlHelper;
use PrestaShop\PrestaShop\Adapter\Entity\Image;
use PrestaShop\PrestaShop\Adapter\Entity\Shop;
use PrestaShop\PrestaShop\Adapter\Entity\Feature;
use PrestaShop\PrestaShop\Adapter\Entity\Language;
use Symfony\Component\HttpFoundation\JsonResponse;
use PedidosInternet\Dto\DtoWebProduct;
use PedidosInternet\Dto\DtoFeature;
use PedidosInternet\Dto\DtoFeatureValue;
use PedidosInternet\Dto\DtoCategoriesWithTemplates;
use PedidosInternet\Controller\Stock;

class PedidosApi
{
    private static ?PedidosApi $pedidosApi = null;
    private static DtoAccessToken $dtoAccessToken;

    // Constante para controlar el modo test
    private const TEST_MODE = false; // Cambiar a false para activar las llamadas reales

    // Método para verificar si estamos en modo test
    private static function isTestMode(): bool
    {
        return self::TEST_MODE;
    }

    // Constantes existentes
    public const API = 'API';
    public const PRESTASHOP = 'PRESTASHOP';
    public const URL = '';

    private const ENDPOINTS = [
        // Estas son las rutas correctas que debemos mantener y usar
        'CLIENTS' => '/PCCOM.Distrib.Clients.Dobuss/Clients',
        'PRODUCTS' => '/PCCOM.Distrib.Products.Dobuss/WebProducts', 
        'PRODUCT_IMAGE' => '/PCCOM.Distrib.Products.Dobuss/WebProductImage/',
        'FAMILIES' => '/PCCOM.Distrib.Products.Dobuss/WebFamilies',
        'RATES' => '/PCCOM.Distrib.Rates.Dobuss/RatePrices?rateID=17',
        'TAXONOMY' => '/PCCOM.Distrib.Taxonomies.Dobuss/Taxonomy',
        'TEMPLATE' => '/PCCOM.Distrib.Template.Dobuss/Template',
        'TEMPLATE_VALUES' => '/PCCOM.Distrib.Template.Dobuss/ProductTemplateValues',
        'ORDER_CHANGES' => '/PCCOM.Distrib.OrderNotes.Dobuss/OrderPickingState',
        'B2C_ORDERS' => '/PCCOM.Distrib.OrderNotes.Dobuss/WebB2COrderNotes'
    ];

    private string $baseUrl = "https://webapi.basterra.pedidosinternet.com:7443/";

    private string $connectUrl = '';
    private string $username = '';
    private string $pass = '';
    private string $clientId = '';
    private string $clientSecret = '';
    private string $scope = '';
    private string $resource = '';
    private string $url_append = '';

    private function __construct()
    {
        $sqlQuery = new \DbQuery();
        $sqlQuery->select('usuario_api, password_api, client_id, client_secret, scope, url, url_append');
        $sqlQuery->from(pSQL('pedidosinternet_configuracion'));
        $sqlQuery->limit(1);
        $row = \Db::getInstance()->executeS($sqlQuery);
        if (!empty($row)) {

            if (empty($row[0]['usuario_api']) || empty($row[0]['password_api'])) {
                Log::error("Configuración de API incompleta: falta usuario o contraseña");
            }

            $this->username = $row[0]['usuario_api'];
            $this->pass = $row[0]['password_api'];
            $this->baseUrl = $row[0]['url'];
            $this->clientId = $row[0]['client_id'];
            $this->clientSecret = $row[0]['client_secret'];
            $this->scope = $row[0]['scope'];
            $this->url_append = $row[0]['url_append'];
            $this->resource = $this->baseUrl . $this->url_append;

            $this->connectUrl = $this->baseUrl . "connect/token";
        } else {
            Log::error("No se encontró configuración para la API de Distrib");
        }
    }

    public static function create(): ?PedidosApi
    {
        if (is_null(self::$pedidosApi)) {
            self::$pedidosApi = new PedidosApi();
        }

        try {
            $row = \Db::getInstance()->executeS(
                'SELECT `access_token`, `refresh_token`,`expiracion_token` FROM `' . _DB_PREFIX_ . 'pedidosinternet_configuracion`  WHERE id = 1'
            );

            if (!empty($row)) {
                if (empty($row[0]['access_token'])) {
                    self::$pedidosApi->accessToken();
                } else {
                    $expirationToken = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row[0]['expiracion_token']);
                    self::$dtoAccessToken = DtoAccessToken::createFromDatabase($row[0]);
                    if ($expirationToken < (new \DateTimeImmutable())) {
                        self::$pedidosApi->refreshToken();
                    }
                }
                // El tiempo de vida del access token es aún válido
                return self::$pedidosApi;
            } else {
                return null;
            }
        } catch (\Exception $ex) {
            var_dump($ex);
            return null;
        }
    }

    /**
     * Construye una URL de API asegurando que no haya barras duplicadas
     */
    private function buildApiUrl(string $endpoint): string 
    {
        return rtrim($this->resource, '/') . '/' . ltrim($endpoint, '/');
    }

    /**
     * Crea un token de acceso mediante las credenciales configuradas
     *
     * @throws \Exception Si hay un error en la comunicación con la API
     */
    public function accessToken()
    {
        try {
            $parameters = [
                'grant_type' => 'password',
                'username' => $this->username,
                'password' => $this->pass,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => $this->scope,
                'resource' => $this->resource
            ];

            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL => $this->connectUrl,
                CURLOPT_POST => 1,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_POSTFIELDS => http_build_query($parameters),
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_RETURNTRANSFER => true
            ]);

            $server_output = curl_exec($ch);
            
            if ($server_output === false) {
                $errorDetails = [
                    'curl_error' => curl_error($ch),
                    'curl_errno' => curl_errno($ch),
                    'url' => $this->connectUrl
                ];
                curl_close($ch);
                Log::error("Error de cURL al obtener token de acceso", $errorDetails);
                throw new \Exception("Error en la conexión con el ERP: " . $errorDetails['curl_error']);
            }
            
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($http_code !== 200) {
                $errorDetails = [
                    'http_code' => $http_code,
                    'response' => $server_output
                ];
                curl_close($ch);
                Log::error("Error en respuesta HTTP al obtener token de acceso", $errorDetails);
                throw new \Exception("Error en la conexión con el ERP (HTTP $http_code)");
            }

            curl_close($ch);
            $this->saveServerAccessOrRefreshTokenOutput($server_output);
                        
        } catch (\Exception $e) {
            Log::error("Excepción al obtener token de acceso", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * @throws \Exception
     */
    public function refreshToken()
    {
        try {
            $parameters = [
                'grant_type' => 'refresh_token',
                'username' => $this->username,
                'password' => $this->pass,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => 'openid offline_access profile roles SSOScope',
                'refresh_token' => self::$dtoAccessToken->refreshToken,
                'resource' => $this->resource
            ];
    
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->connectUrl,
                CURLOPT_POST => 1,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_POSTFIELDS => http_build_query($parameters),
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_RETURNTRANSFER => true
            ]);
    
            $server_output = curl_exec($ch);
            
            if (empty($server_output) || (curl_errno($ch) > 0)) {
                Log::error("Error en refresh token, intentando nuevo acceso", [
                    'curl_error' => curl_error($ch)
                ]);
                curl_close($ch);
                $this->accessToken();
                return;
            }
    
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($http_code !== 200) {
                throw new \Exception("Error en la conexión con el ERP: HTTP $http_code");
            }
            
            curl_close($ch);
            $this->saveServerAccessOrRefreshTokenOutput($server_output);
                        
        } catch (\Exception $e) {
            Log::error("Error en refresh token", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     *
     * @param string $serverOutput
     * @return void
     */
    private function saveServerAccessOrRefreshTokenOutput(string $serverOutput)
    {
        self::$dtoAccessToken = DtoAccessToken::create($serverOutput);
        \Db::getInstance()->update('pedidosinternet_configuracion', [
            'access_token' => self::$dtoAccessToken->accessToken,
            'refresh_token' => self::$dtoAccessToken->refreshToken,
            'expiracion_token' => self::$dtoAccessToken->expirationTime->format('Y-m-d H:i:s'),
        ], 'id = ' . 1);
    }

    /**
     * Obtiene todas las taxonomías desde el API
     *
     * @return array Lista de taxonomías
     * @throws \Exception Si hay error en la comunicación con el API
     */
    public function getTaxonomies(): array
    {
        try {
            
            $url = $this->buildApiUrl(self::ENDPOINTS['TAXONOMY']);
            
            $ch = CurlHelper::getPetition(
                $url,
                self::$dtoAccessToken->accessToken
            );
            
            $response = CurlHelper::executeAndCheckCode($ch, 200);
            
            if (!is_array($response)) {
                throw new \Exception('La respuesta de taxonomías no es un array válido');
            }
            
            // Registrar información básica sobre las taxonomías recibidas
            $taxonomyIds = array_column($response, 'id');

            // Registrar información detallada sobre las taxonomías requeridas
            $requiredTaxonomies = array_filter($response, function($taxonomy) {
                return in_array($taxonomy['id'], [4, 15, 18]);
            });
            
            foreach ($requiredTaxonomies as $taxonomy) {
                $childTermsCount = isset($taxonomy['terms']) ? count($taxonomy['terms']) : 0;
                
            }
            
            return $response;
        } catch (\Exception $e) {
            Log::error("Error al obtener taxonomías", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Obtiene clientes filtrados por rol desde el ERP
     *
     * @param string $role El rol para filtrar (1=B2B, 2=B2C)
     * @return array Lista de objetos DtoCliente
     * @throws \Exception Si hay error en la comunicación con el API
     */
    public function getClientsByRole(string $role): array
    {
        try {
            
            $url = $this->buildApiUrl(self::ENDPOINTS['CLIENTS'] . "?idRole={$role}");
            $ch = CurlHelper::getPetition($url, self::$dtoAccessToken->accessToken);
            
            $clients = CurlHelper::executeAndCheckCode($ch, 200);
            
            if (!is_array($clients)) {
                Log::error("La respuesta de clientes no es un array válido", [
                    'response' => $clients,
                    'role' => $role
                ]);
                return [];
            }
            
            $clientsDTO = array_map(function ($row) {
                return DtoCliente::create($row);
            }, $clients);
            
            $clientsDTO = array_filter($clientsDTO, function($client) {
                return $client !== null;
            });
            
            return $clientsDTO;
        } catch (\Exception $e) {
            Log::error("Error al obtener clientes por rol", [
                'role' => $role,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Crea un cliente en el ERP
     * 
     * @param DtoCliente $client Datos del cliente a crear
     * @return array Respuesta de la API o array vacío en caso de error
     * @throws \Exception Si ocurre un error durante la creación
     */
    public function createClient(DtoCliente $client): array
    {
        try {
            Log::info("Iniciando creación de cliente", [
                'client' => $client,
            ]);
            
            // Si estamos en modo test, simular éxito y salir
            if (self::isTestMode()) {
                Log::info("MODO PRUEBA: Simulando creacion exitosa de cliente", [
                    'client_id' => $client->id,
                    'data' => $client->toApiArray(false)
                ]);
                return ['id' => 999, 'success' => true]; // Devolver un ID falso para modo prueba
            }

            $url = $this->buildApiUrl(self::ENDPOINTS['CLIENTS']);

            $ch = CurlHelper::postPetitionFormData(
                $url,
                self::$dtoAccessToken->accessToken,
                $client->toApiArray(),
                true
            );

            $client_created = CurlHelper::executeAndCheckCodeClient($ch, 201, true);

            Log::info("Cliente creado correctamente", [
                'client_id' => $client->id,
                'data' => $client->toApiArray(false),
                'response' => $client_created
            ]);

            return $client_created;
        } catch (\Exception $e) {
            Log::error("Error al crear cliente", [
                'error' => $e->getMessage(),
                'client_id' => $client->id,
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

	/**
	 * Actualiza un cliente existente en el ERP
	 *
	 * @param DtoCliente $client Datos del cliente a actualizar
	 * @return bool True si la actualización fue exitosa, false en caso contrario
	 */
	public function updateClient(DtoCliente $client): bool
	{
		try {
			if (empty($client->id)) {
				Log::warning("No se puede actualizar cliente: ID de API no disponible", [
					'customer_id' => $client->webId
				]);
				return false;
			}

			Log::info("Iniciando actualización de datos del cliente", [
				'client' => [
					'id' => $client->id,
					'webId' => $client->webId,
					'email' => $client->email
				]
			]);

			// Si estamos en modo test, simular éxito y salir
			if (self::isTestMode()) {
				Log::info("MODO PRUEBA: Simulando actualizacion de datos del cliente exitosa", [
					'client_id' => $client->id,
					'data' => $client->toApiArray(false)
				]);
				return true;
			}

			// CORRECCIÓN: Usar PUT con la URL correcta que incluye el ID del cliente
			$url = $this->buildApiUrl(self::ENDPOINTS['CLIENTS'] . "/{$client->id}");
			$requestData = $client->toApiArray(false);

			// Guardar ID del log para actualizar la respuesta después
			$logId = (int)Log::$lastInsertedId;

			// Ejecutar la petición PUT
			$ch = CurlHelper::putPetition(
				$url,
				self::$dtoAccessToken->accessToken,
				$requestData
			);

			$serverOutput = curl_exec($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$curlError = curl_error($ch);

			if (curl_errno($ch)) {
				curl_close($ch);

				// Actualizar el log con la respuesta
				if ($logId > 0) {
					Log::update($logId, [
						'http_code' => $httpCode,
						'curl_error' => $curlError,
						'response' => 'Error de conexión'
					]);
				}

				Log::error("Error de conexión al actualizar cliente", [
					'client_id' => $client->id,
					'url' => $url,
					'curl_error' => $curlError
				]);

				return false;
			}

			curl_close($ch);

			// Actualizar el log con la respuesta
			if ($logId > 0) {
				Log::update($logId, [
					'http_code' => $httpCode,
					'response' => $serverOutput
				]);
			}

			// Verificar si la respuesta es un HTML (posible error de autenticación)
			$isHtml = $this->isHtmlResponse($serverOutput);

			if ($httpCode < 200 || $httpCode >= 300 || $isHtml) {
				Log::error("Error al actualizar cliente", [
					'client_id' => $client->id,
					'http_code' => $httpCode,
					'is_html' => $isHtml,
					'response' => substr($serverOutput, 0, 500) . (strlen($serverOutput) > 500 ? '...' : '')
				]);

				// Si parece un error de autenticación, intentar renovar token y reintentar
				if ($this->isAuthenticationError(['http_code' => $httpCode, 'is_html' => $isHtml])) {
					Log::info("Posible error de autenticación, renovando token y reintentando", [
						'client_id' => $client->id,
						'http_code' => $httpCode,
						'error_type' => 'autenticación'
					]);

					// Forzar renovación del token
					$this->accessToken();

					// Segundo intento con token renovado
					return $this->retryUpdateClient($client, $url, $requestData);
				}

				return false;
			}

			Log::info("Cliente actualizado correctamente", [
				'client_id' => $client->id,
				'api_id' => $client->id,
				'http_code' => $httpCode
			]);

			return true;
		} catch (\Exception $e) {
			Log::error("Excepción al actualizar cliente", [
				'client_id' => $client->id,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			]);

			return false;
		}
	}
	
	/**
	 * Reintenta la actualización de un cliente después de renovar el token
	 * 
	 * @param DtoCliente $client Cliente a actualizar
	 * @param string $url URL de la API
	 * @param array $requestData Datos de la solicitud
	 * @return bool Resultado de la operación
	 */
	private function retryUpdateClient(DtoCliente $client, string $url, array $requestData): bool
	{
		try {
			$ch = CurlHelper::putPetition(
				$url,
				self::$dtoAccessToken->accessToken,
				$requestData
			);

			$serverOutput = curl_exec($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$curlError = curl_error($ch);

			if (curl_errno($ch)) {
				curl_close($ch);

				Log::error("Error de conexión en reintento de actualización de cliente", [
					'client_id' => $client->id,
					'curl_error' => $curlError
				]);

				return false;
			}

			curl_close($ch);

			if ($httpCode < 200 || $httpCode >= 300) {
				Log::error("Error en reintento de actualización de cliente", [
					'client_id' => $client->id,
					'http_code' => $httpCode,
					'response' => substr($serverOutput, 0, 500)
				]);

				return false;
			}

			Log::info("Cliente actualizado correctamente en reintento", [
				'client_id' => $client->id,
				'http_code' => $httpCode
			]);

			return true;
		} catch (\Exception $e) {
			Log::error("Excepción en reintento de actualización de cliente", [
				'client_id' => $client->id,
				'error' => $e->getMessage()
			]);

			return false;
		}
	}

	/**
	 * Verifica si la respuesta es HTML (posible página de login)
	 *
	 * @param string $response Respuesta de la API
	 * @return bool True si es HTML, false si no
	 */
	private function isHtmlResponse(string $response): bool
	{
		return strpos(trim($response), '<!DOCTYPE html>') === 0 || 
			   strpos(trim($response), '<html') === 0;
	}

	/**
	 * Determina si un error parece ser de autenticación
	 *
	 * @param array $result Resultado de la operación
	 * @return bool True si parece error de autenticación
	 */
	private function isAuthenticationError(array $result): bool
	{
		// Si es HTML, probablemente es la página de login
		if (isset($result['is_html']) && $result['is_html']) {
			return true;
		}

		// Si el código es 401 o 403, es un error de autenticación
		if (in_array($result['http_code'], [401, 403])) {
			return true;
		}

		// Si el código es 500 y la respuesta es HTML, podría ser un error de sesión expirada
		if ($result['http_code'] == 500 && $this->isHtmlResponse($result['response'])) {
			return true;
		}

		return false;
	}


	/**
	 * Actualiza la dirección de un cliente en el ERP
	 *
	 * @param DtoCliente $client Cliente al que pertenece la dirección
	 * @param \Address $address Dirección a actualizar
	 * @return bool True si la actualización fue exitosa, false en caso contrario
	 */
	public function updateAddress(DtoCliente $client, \Address $address): bool
	{
		try {
			if (empty($client->id)) {
				Log::warning("No se puede actualizar dirección: cliente sin ID en ERP", [
					'customer_id' => $client->webId,
					'address_id' => $address->id
				]);
				return false;
			}

			Log::info("Iniciando actualización de dirección de cliente", [
				'client_id' => $client->id,
				'address_id' => $address->id
			]);

			// Si estamos en modo test, simular éxito y salir
			if (self::isTestMode()) {
				Log::info("MODO PRUEBA: Simulando actualizacion de dirección exitosa", [
					'client_id' => $client->id,
					'address_id' => $address->id
				]);
				return true;
			}

			// CORRECCIÓN: Usar PUT con la URL correcta que incluye el ID del cliente
			$url = $this->buildApiUrl(self::ENDPOINTS['CLIENTS'] . "/{$client->id}");
			$requestData = $client->toApiArray(false, $address);

			// Guardar ID del log para actualizar la respuesta después
			$logId = (int)Log::$lastInsertedId;

			// Ejecutar la petición PUT
			$ch = CurlHelper::putPetition(
				$url,
				self::$dtoAccessToken->accessToken,
				$requestData
			);

			$serverOutput = curl_exec($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$curlError = curl_error($ch);

			if (curl_errno($ch)) {
				curl_close($ch);

				// Actualizar el log con la respuesta
				if ($logId > 0) {
					Log::update($logId, [
						'http_code' => $httpCode,
						'curl_error' => $curlError,
						'response' => 'Error de conexión'
					]);
				}

				Log::error("Error de conexión al actualizar dirección", [
					'client_id' => $client->id,
					'address_id' => $address->id,
					'url' => $url,
					'curl_error' => $curlError
				]);

				return false;
			}

			curl_close($ch);

			// Actualizar el log con la respuesta
			if ($logId > 0) {
				Log::update($logId, [
					'http_code' => $httpCode,
					'response' => $serverOutput
				]);
			}

			// Verificar si la respuesta es un HTML (posible error de autenticación)
			$isHtml = $this->isHtmlResponse($serverOutput);

			if ($httpCode < 200 || $httpCode >= 300 || $isHtml) {
				Log::error("Error al actualizar dirección", [
					'client_id' => $client->id,
					'address_id' => $address->id,
					'http_code' => $httpCode,
					'is_html' => $isHtml,
					'response' => substr($serverOutput, 0, 500) . (strlen($serverOutput) > 500 ? '...' : '')
				]);

				// Si parece un error de autenticación, intentar renovar token y reintentar
				if ($this->isAuthenticationError(['http_code' => $httpCode, 'is_html' => $isHtml])) {
					Log::info("Posible error de autenticación al actualizar dirección, renovando token y reintentando", [
						'client_id' => $client->id,
						'address_id' => $address->id,
						'http_code' => $httpCode,
						'error_type' => 'autenticación'
					]);

					// Forzar renovación del token
					$this->accessToken();

					// Segundo intento con token renovado
					return $this->retryUpdateAddress($client, $address, $url, $requestData);
				}

				return false;
			}

			Log::info("Dirección actualizada correctamente", [
				'client_id' => $client->id,
				'address_id' => $address->id,
				'http_code' => $httpCode
			]);

			return true;
		} catch (\Exception $e) {
			Log::error("Excepción al actualizar dirección", [
				'client_id' => $client->id ?? 'desconocido',
				'address_id' => $address->id ?? 'desconocido',
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			]);

			return false;
		}
	}

    /**
     * Obtiene un cliente por su ID desde el ERP
     *
     * @param string $id ID del cliente en el ERP
     * @return DtoCliente|null Cliente encontrado o null si hay error
     */
    public function getClientById(string $id): ?DtoCliente
    {
        try {
            
            $url = $this->buildApiUrl(self::ENDPOINTS['CLIENTS'] . "/{$id}");
            $ch = CurlHelper::getPetition(
                $url,
                self::$dtoAccessToken->accessToken
            );
            
            $response = CurlHelper::executeAndCheckCode($ch, 200, true);
            
            if (!$response) {
                Log::warning("Cliente no encontrado", [
                    'client_id' => $id
                ]);
                return null;
            }
            
            $client = DtoCliente::create($response);
            
            Log::info("Cliente obtenido correctamente", [
                'client_id' => $id
            ]);
            
            return $client;
        } catch (\Exception $e) {
            Log::error("Error al obtener cliente por ID", [
                'client_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return null;
        }
    }


    /**
     * Obtiene el listado de productos del API.
     *
     * @return array["errors" => array, "success" => array]
     * @throws \Exception
     */
    public function getWebProducts(): array
    {

        try {
            $url = $this->buildApiUrl(self::ENDPOINTS['PRODUCTS']);
            $imageUrl = $this->buildApiUrl(self::ENDPOINTS['PRODUCT_IMAGE']);
            
            $ch = CurlHelper::getPetition(
                $url,
                self::$dtoAccessToken->accessToken
            );

            $products = CurlHelper::executeAndCheckCode($ch, 200);
            $errors = [];
            $success = [];

            foreach ($products as $position => $product) {
                try {
                    $webProduct = DtoWebProduct::create($product);
                    if (is_null($webProduct)) {
                        continue;
                    }

                    $isNewProduct = false;
                    $existingProduct = DtoWebProduct::getProductByReference($webProduct->reference);
                    if (empty($existingProduct)) {
                        $isNewProduct = true;
                    }
                    
                    $created = $webProduct->createOrSaveProduct();
                    
                    if ($created === null) {
                        $errors[] = $webProduct;
                        Log::error("Error al crear/actualizar producto", [
                            'reference' => $webProduct->reference
                        ]);
                        continue;
                    }
    
                    // Solo procesamos imágenes para productos nuevos
                    if ($isNewProduct) {
                        $sql = 'SELECT `id_product` FROM ' . _DB_PREFIX_ . 'product WHERE `reference` = \'' . pSQL($created->reference) . '\'';
                        $product_id = \Db::getInstance()->getValue($sql);

                        if($product_id) {
                            Stock::updateProductStock((int)$product_id);

                            // Eliminar imágenes solo para productos nuevos
                            $created->deleteImages();
                            $position_image = 1;

                            if (count($webProduct->imagesInformation) > 0) {
                                foreach ($webProduct->imagesInformation as $image) {
                                    if($image['sendToWeb'] == true) {
                                        $chImage = CurlHelper::getPetition(
                                            $imageUrl . $image['id'],  
                                            self::$dtoAccessToken->accessToken
                                        );
                                        $imageResult = CurlHelper::executeAndCheckCode($chImage, 200);
                                        
                                        $this->addImageToProduct(intval($created->id), $imageResult, $position_image);
                                        $position_image++;
                                    }
                                }
                            }
                        }
                    } else {
                        // Para productos existentes, solo actualizamos el stock
                        $sql = 'SELECT `id_product` FROM ' . _DB_PREFIX_ . 'product WHERE `reference` = \'' . pSQL($created->reference) . '\'';
                        $product_id = \Db::getInstance()->getValue($sql);
                        
                        if($product_id) {
                            Stock::updateProductStock((int)$product_id);
                        }
                    }

                    $success[] = $webProduct;
    
                } catch (\Exception $e) {
                    Log::error("Error procesando producto", [
                        'position' => $position,
                        'reference' => $product['reference'] ?? 'desconocida',
                        'error' => $e->getMessage()
                    ]);
                    $errors[] = $webProduct ?? $product;
                }
            }
    
            return [
                "errors" => $errors,
                "success" => $success
            ];

        } catch (\Exception $e) {
            Log::error("Error obteniendo productos", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Obtiene y procesa las tarifas de productos desde el ERP
     *
     * @return array Información sobre las tarifas procesadas
     * @throws \Exception Si hay error en la comunicación con el API
     */

    public function getRates(): array
    {
        try {
            
            $url = $this->buildApiUrl(self::ENDPOINTS['RATES']);
            $ch = CurlHelper::getPetition($url, self::$dtoAccessToken->accessToken);
            
            $rates = CurlHelper::executeAndCheckCode($ch, 200);
            
            if (!is_array($rates)) {
                Log::error("La respuesta de tarifas no es un array válido", [
                    'response' => $rates
                ]);
                return ['error' => 'Formato de respuesta inválido'];
            }
            
            $processedCount = 0;
            $errors = [];
            
            foreach ($rates as $rate) {
                try {
                    DtoWebProduct::updatePrice(
                        $rate['reference'],
                        floatval($rate['ratePrice']),
                        boolval($rate['isPVP'])
                    );
                    
                    DtoWebProduct::asignProductToPriceCategory(
                        $rate['reference'], 
                        floatval($rate['ratePrice']), 
                        boolval($rate['isPVP'])
                    );
                    
                    $processedCount++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'reference' => $rate['reference'],
                        'error' => $e->getMessage()
                    ];
                    
                    Log::warning("Error al procesar tarifa", [
                        'reference' => $rate['reference'],
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            return [
                'total' => count($rates),
                'processed' => $processedCount,
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            Log::error("Error al obtener tarifas", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Obtiene las plantillas web desde el ERP
     *
     * @return array Información sobre las plantillas web
     */
    public function getWebTemplates(): array
    {
        try {
            
            $langDefault = (int)\Configuration::get('PS_LANG_DEFAULT');
            $shops = \Shop::getShops(true, null, true);
            $currentShopId = array_values($shops)[0];
            
            // Obtener plantillas
            $urlTemplate = $this->buildApiUrl(self::ENDPOINTS['TEMPLATE']);
            $chTemplate = CurlHelper::getPetition(
                $urlTemplate,
                self::$dtoAccessToken->accessToken
            );
            
            $webTemplates = CurlHelper::executeAndCheckCode($chTemplate, 200);
            
            if (!is_array($webTemplates)) {
                Log::error("Respuesta de plantillas web inválida", [
                    'response' => $webTemplates
                ]);
                return [];
            }
            
            // Obtener taxonomías
            $urlTaxonomies = $this->buildApiUrl(self::ENDPOINTS['TAXONOMY']);
            $chTaxonomies = CurlHelper::getPetition(
                $urlTaxonomies,
                self::$dtoAccessToken->accessToken
            );
            
            $taxonomies = CurlHelper::executeAndCheckCode($chTaxonomies, 200);
            
            if (!is_array($taxonomies)) {
                Log::error("Respuesta de taxonomías inválida", [
                    'response' => $taxonomies
                ]);
                return [];
            }
            
            $processedTemplates = 0;
            
            // Procesar plantillas
            foreach ($webTemplates as $template) {
                if ($template['id'] == 8) { // ID de la plantilla específica a procesar
                    foreach ($template['fieldsWithoutGroup'] as $featureValue) {
                        $conbinedValue = $featureValue['id'] . '-' . $featureValue['name'];
                        
                        try {
                            switch ($conbinedValue) {
                                case "57-Cuerpo (Ligero - Potente)":
                                case "58-Fruta (Frutal - Madura)":
                                case "59-Acidez (Fresco - Cálido)":
                                    DtoFeature::createFeature($langDefault, $featureValue);
                                    DtoFeatureValue::setNumbericFeatureValues($langDefault, $featureValue);
                                    break;
                                    
                                case "50-Nombre del vino":
                                case "54-Descripción":
                                case "70-MWP: Maridaje":
                                case "71-Oferta Flash":
                                    // Características que no necesitan procesamiento especial
                                    break;
                                    
                                default:        
                                    DtoFeature::createFeature($langDefault, $featureValue);
                                    DtoFeatureValue::createFeatureValue($langDefault, $featureValue, $taxonomies);
                                    break;
                            }
                            
                            $processedTemplates++;
                        } catch (\Exception $e) {
                            Log::warning("Error al procesar característica", [
                                'feature_id' => $featureValue['id'],
                                'feature_name' => $featureValue['name'],
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }
            }
            
            return [
                'total_templates' => count($webTemplates),
                'processed_features' => $processedTemplates
            ];
        } catch (\Exception $e) {
            Log::error("Error al obtener plantillas web", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [];
        }
    }

    /**
     * Obtiene y procesa los valores de plantilla de productos desde el ERP
     *
     * @return array Información sobre el procesamiento
     */
    public function getProductTemplateValues(): array
    {
        try {
            
            $totalProcessed = 0;
            $totalProducts = 0;
            $errors = [];
            $pageNum = 0;
            $hasMoreData = true;
            
            // Truncar tabla de características de producto solo antes de comenzar
            if ($pageNum == 0) {
                $sql = 'TRUNCATE TABLE `' . _DB_PREFIX_ . 'feature_product`';
                \Db::getInstance()->execute($sql);
                
            }
            
            // Obtener plantillas web para usar durante el procesamiento
            $url_template = $this->buildApiUrl(self::ENDPOINTS['TEMPLATE']);
            $ch_template = CurlHelper::getPetition(
                $url_template,
                self::$dtoAccessToken->accessToken
            );
            
            $webTemplates = CurlHelper::executeAndCheckCode($ch_template, 200);
            
            if (!is_array($webTemplates)) {
                Log::error("Respuesta de plantillas web inválida", [
                    'response' => $webTemplates
                ]);
                return ['error' => 'Formato de respuesta de plantillas inválido'];
            }
            
            // Procesar páginas de valores de plantilla
            while ($hasMoreData) {
                
                $url = $this->buildApiUrl(self::ENDPOINTS['TEMPLATE_VALUES']) . "?PageNum={$pageNum}";
                $ch = CurlHelper::getPetition(
                    $url,
                    self::$dtoAccessToken->accessToken
                );
                
                $productTemplateValues = CurlHelper::executeAndCheckCode($ch, 200);
                
                // Verificar si hay más datos para procesar
                if (empty($productTemplateValues)) {
                    $hasMoreData = false;
                    break;
                }
                
                $totalProducts += count($productTemplateValues);
                
                // Procesar productos en esta página
                foreach ($productTemplateValues as $product_reference => $templateValues) {
                    try {
                        $this->processProductTemplateValues(
                            strval($product_reference),
                            $templateValues,
                            $webTemplates
                        );
                        
                        $totalProcessed++;
                    } catch (\Exception $e) {
                        $errors[] = [
                            'reference' => $product_reference,
                            'error' => $e->getMessage()
                        ];
                        
                        Log::warning("Error al procesar valores de plantilla para producto", [
                            'reference' => $product_reference,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
                
                $pageNum++;
            }
            
            return [
                'total_products' => $totalProducts,
                'processed' => $totalProcessed,
                'errors' => count($errors),
                'pages' => $pageNum
            ];
        } catch (\Exception $e) {
            Log::error("Error al obtener valores de plantilla de productos", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Procesa los valores de plantilla para un producto específico
     *
     * @param string $product_reference Referencia del producto
     * @param array $templateValues Valores de plantilla
     * @param array $webTemplates Plantillas web
     * @return void
     */
    private function processProductTemplateValues(
        string $product_reference,
        array $templateValues,
        array $webTemplates
    ): void {
        try {
            // Verificar si el producto existe
            $sql = 'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'product WHERE `reference` = \'' . pSQL($product_reference) . '\'';
            $product_exist = \Db::getInstance()->getValue($sql);
            
            if (!$product_exist) {
                return;
            }
            
            // Obtener ID del producto
            $sql = 'SELECT `id_product` FROM ' . _DB_PREFIX_ . 'product WHERE `reference` = \'' . pSQL($product_reference) . '\'';
            $product_id = \Db::getInstance()->getValue($sql);
            
            if (!$product_id) {
                Log::warning("No se pudo obtener ID de producto", [
                    'reference' => $product_reference
                ]);
                return;
            }
            
            $isFlashOffer = false;
            
            foreach ($templateValues as $templateValue) {
                $id_feature = (int)$templateValue["fieldId"];
                $value = $templateValue["value"];
                
                // Detectar ofertas flash (ID 71)
                if ($id_feature == 71) {
                    $isFlashOffer = $this->processFlashOffer($id_feature, $value, (int)$product_id);
                } else {
                    // Procesar según tipo de característica
                    $this->processFeatureValue($id_feature, $value, (int)$product_id, $webTemplates);
                }
            }
            
        } catch (\Exception $e) {
            Log::error("Error al procesar valores de plantilla para producto", [
                'reference' => $product_reference,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Procesa la característica de oferta flash
     *
     * @param int $id_feature ID de la característica
     * @param string $value Valor
     * @param int $product_id ID del producto
     * @return bool True si se procesó como oferta flash
     */
    private function processFlashOffer(int $id_feature, string $value, int $product_id): bool
    {
        try {
            // Verificar si el valor indica una oferta flash
            $isFlashOffer = false;
            
            // Convertir a minúsculas y quitar espacios para comparación
            $normalizedValue = strtolower(trim($value));
            
            // Verificar si el valor es positivo (1, yes, true, si, etc.)
            if (in_array($normalizedValue, ['1', 'yes', 'true', 'si', 'sí', 'y', 's']) || 
                $normalizedValue === '1' || !empty($normalizedValue)) {
                $isFlashOffer = true;
            }
            
            if ($isFlashOffer) {

                // Intentar asignar a categoría de ofertas flash
                $result = DtoCategoriesWithTemplates::assignToFlashOffersCategory($product_id);
                
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error("Error al procesar oferta flash", [
                'product_id' => $product_id,
                'feature_id' => $id_feature,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Procesa un valor de característica para un producto
     *
     * @param int $id_feature ID de la característica
     * @param string $value Valor
     * @param int $product_id ID del producto
     * @param array $webTemplates Plantillas web
     * @return void
     */
    private function processFeatureValue(
        int $id_feature,
        string $value,
        int $product_id,
        array $webTemplates
    ): void {
        try {
            // Procesar nombre y descripción
            if ($id_feature == 50) {
                DtoWebProduct::updateProductName($product_id, $value);
                return;
            }
            
            if ($id_feature == 54) {
                DtoWebProduct::updateProductShortDescription($product_id, $value);
                return;
            }
            
            // Procesar características numéricas
            if ($id_feature == 57 || $id_feature == 58 || $id_feature == 59) {
                $this->assignNumericFeature($id_feature, $value, $product_id);
                return;
            }
            
            // Obtener ID de taxonomía si existe
            $taxonomyID = $this->getTaxonomyIdFromTemplates($id_feature, $webTemplates);
            
            if ($taxonomyID && $id_feature != 70) {
                $this->assignTaxonomyFeature($taxonomyID, $value, $id_feature, $product_id);
            }
        } catch (\Exception $e) {
            Log::error("Error al procesar valor de característica", [
                'product_id' => $product_id,
                'feature_id' => $id_feature,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Asigna una característica numérica a un producto
     *
     * @param int $id_feature ID de la característica
     * @param string $value Valor
     * @param int $product_id ID del producto
     * @return bool True si se asignó correctamente
     */
    private function assignNumericFeature(int $id_feature, string $value, int $product_id): bool
    {
        try {
            // Validar que el valor sea numérico
            if (!is_numeric($value)) {
                Log::warning("Valor no numérico para característica numérica", [
                    'product_id' => $product_id,
                    'feature_id' => $id_feature,
                    'value' => $value
                ]);
                return false;
            }
            
            // Asegurarse de que el valor esté en el rango 1-5
            $numericValue = (int)$value;
            if ($numericValue < 1 || $numericValue > 5) {
                Log::warning("Valor numérico fuera de rango (1-5)", [
                    'product_id' => $product_id,
                    'feature_id' => $id_feature,
                    'value' => $value,
                    'numeric_value' => $numericValue
                ]);
                $numericValue = max(1, min(5, $numericValue)); // Limitar al rango 1-5
            }
            
            $feature_value_id = (int)($id_feature . $numericValue);
            
            $data_feature_product = [
                "id_product" => $product_id,
                "id_feature" => $id_feature,
                "id_feature_value" => $feature_value_id,
            ];
            
            $conditionalSQL = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'feature_product` WHERE `id_product` = ' . 
                (int)$product_id . ' AND `id_feature` = ' . (int)$id_feature . 
                ' AND `id_feature_value` = ' . (int)$feature_value_id;
            
            if (!\Db::getInstance()->getValue($conditionalSQL)) {
                $result = \Db::getInstance()->insert('feature_product', $data_feature_product);
                
                if ($result) {
                    return true;
                } else {
                    Log::warning("Error al asignar característica numérica", [
                        'product_id' => $product_id,
                        'feature_id' => $id_feature,
                        'value' => $numericValue
                    ]);
                    return false;
                }
            } else {

                return true;
            }
        } catch (\Exception $e) {
            Log::error("Error al asignar característica numérica", [
                'product_id' => $product_id,
                'feature_id' => $id_feature,
                'value' => $value,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Asigna una característica de taxonomía a un producto
     *
     * @param int $taxonomyID ID de la taxonomía
     * @param string $value Valor (pueden ser múltiples separados por ;)
     * @param int $id_feature ID de la característica
     * @param int $product_id ID del producto
     * @return void
     */
    private function assignTaxonomyFeature(int $taxonomyID, string $value, int $id_feature, int $product_id): void
    {
        $values = explode(";", $value);
        
        foreach ($values as $val) {
            if (empty(trim($val))) {
                continue;
            }
            
            $feature_value_id = $taxonomyID . 0 . $val;
            
            $data_feature_product = [
                "id_product" => $product_id,
                "id_feature" => $id_feature,
                "id_feature_value" => $feature_value_id,
            ];
            
            $conditionalSQL = 'SELECT * FROM `' . _DB_PREFIX_ . 'feature_product` WHERE `id_product` = ' . 
                (int)$product_id . ' AND `id_feature` = ' . (int)$id_feature . 
                ' AND `id_feature_value` = ' . (int)$feature_value_id;
            
            if (!\Db::getInstance()->getValue($conditionalSQL)) {
                \Db::getInstance()->insert('feature_product', $data_feature_product);
                
            }
        }
    }

    /**
     * Obtiene el ID de taxonomía desde las plantillas web
     *
     * @param int $id_feature ID de la característica
     * @param array $webTemplates Plantillas web
     * @return int|null ID de la taxonomía o null si no se encuentra
     */
    private function getTaxonomyIdFromTemplates(int $id_feature, array $webTemplates): ?int
    {
        foreach ($webTemplates as $template) {
            if ($template['id'] == 8) {
                foreach ($template['fieldsWithoutGroup'] as $taxonomy) {
                    if ($taxonomy['id'] == $id_feature && isset($taxonomy['taxonomyId'])) {
                        return (int)$taxonomy['taxonomyId'];
                    }
                }
            }
        }
        
        return null;
    }

    /**
     * Asigna categorías con plantillas
     *
     * @return array Información sobre el proceso de asignación
     * @throws \Exception Si hay error en el proceso
     */
    public function asignCategoriesWithTemplates(): array
    {
        try {
            
            $startTime = new \DateTimeImmutable();
            
            // Aquí va la llamada a DtoCategoriesWithTemplates::asignValues() o similar
            DtoCategoriesWithTemplates::asignValues();
            
            $endTime = new \DateTimeImmutable();
            $duration = $endTime->getTimestamp() - $startTime->getTimestamp();
            
            return [
                'success' => true,
                'duration_seconds' => $duration
            ];
        } catch (\Exception $e) {
            Log::error("Error al asignar categorías con plantillas", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Añade una imagen a un producto
     *
     * @param int $productId ID del producto
     * @param array $image Datos de la imagen (con base64Content)
     * @param int $position Posición de la imagen
     * @return bool True si se añadió correctamente, false en caso contrario
     */
    private function addImageToProduct(int $productId, array $image, int $position): bool
    {
        try {
            
            // Crear archivo temporal para la imagen
            $tempfile = tempnam(_PS_TMP_IMG_DIR_, 'product_image_');
            
            if (!$tempfile) {
                Log::error("No se pudo crear archivo temporal para imagen", [
                    'product_id' => $productId
                ]);
                return false;
            }
            
            // Guardar contenido base64 en el archivo temporal
            if (!isset($image['base64Content']) || empty($image['base64Content'])) {
                Log::error("Contenido base64 de imagen vacío", [
                    'product_id' => $productId,
                    'image_id' => $image['id'] ?? 'unknown'
                ]);
                @unlink($tempfile);
                return false;
            }
            
            $imageContent = base64_decode($image['base64Content']);
            
            if (!$imageContent) {
                Log::error("No se pudo decodificar contenido base64 de imagen", [
                    'product_id' => $productId,
                    'image_id' => $image['id'] ?? 'unknown'
                ]);
                @unlink($tempfile);
                return false;
            }
            
            $handle = fopen($tempfile, 'w');
            
            if (!$handle) {
                Log::error("No se pudo abrir archivo temporal para escritura", [
                    'product_id' => $productId,
                    'tempfile' => $tempfile
                ]);
                @unlink($tempfile);
                return false;
            }
            
            fwrite($handle, $imageContent);
            fclose($handle);
            
            // Obtener tiendas activas
            $shops = \Shop::getShops(true, null, true);
            $currentShopId = array_values($shops)[0];
            
            // Crear objeto de imagen
            $dbImage = new \Image();
            $dbImage->id_product = $productId;
            $dbImage->position = $position;
            $dbImage->cover = ($position === 1);
            
            // Eliminar imagen existente en la misma posición
            $dbImage->delete();
            
            // Validar y añadir nueva imagen
            if (!$dbImage->validateFields(false, true) || !$dbImage->validateFieldsLang(false, true)) {
                Log::error("Validación de imagen fallida", [
                    'product_id' => $productId,
                    'position' => $position
                ]);
                @unlink($tempfile);
                return false;
            }
            
            if (!$dbImage->add()) {
                Log::error("No se pudo añadir imagen a la base de datos", [
                    'product_id' => $productId,
                    'position' => $position
                ]);
                @unlink($tempfile);
                return false;
            }
            
            // Asociar imagen a las tiendas
            $dbImage->associateTo($shops);
            
            // Obtener ruta para crear imagen
            $path = $dbImage->getPathForCreation();
            
            // Verificar límites de memoria
            if (!\ImageManager::checkImageMemoryLimit($tempfile)) {
                Log::error("Límite de memoria excedido para la imagen", [
                    'product_id' => $productId,
                    'image_size' => filesize($tempfile)
                ]);
                @unlink($tempfile);
                return false;
            }
            
            // Dimensiones para redimensionar imagen
            $tgt_width = $tgt_height = 0;
            $src_width = $src_height = 0;
            $error = 0;
            
            // Redimensionar imagen principal
            if (!\ImageManager::resize(
                $tempfile,
                $path . '.jpg',
                null,
                null,
                'jpg',
                false,
                $error,
                $tgt_width,
                $tgt_height,
                5,
                $src_width,
                $src_height
            )) {
                Log::error("Error al redimensionar imagen principal", [
                    'product_id' => $productId,
                    'error_code' => $error
                ]);
                @unlink($tempfile);
                return false;
            }
            
            // Información de rutas para imágenes redimensionadas
            $path_infos = [];
            $path_infos[] = [$tgt_width, $tgt_height, $path . '.jpg'];
            
            // Crear diferentes tamaños de la imagen
            $images_types = \ImageType::getImagesTypes('products', true);
            
            foreach ($images_types as $image_type) {
                try {
                    $tmpfile = $this->get_best_path((int)$image_type['width'], (int)$image_type['height'], $path_infos);
                    
                    if (\ImageManager::resize(
                        $tmpfile,
                        $path . '-' . stripslashes($image_type['name']) . '.jpg',
                        $image_type['width'],
                        $image_type['height'],
                        'jpg',
                        false,
                        $error,
                        $tgt_width,
                        $tgt_height,
                        5,
                        $src_width,
                        $src_height
                    )) {
                        // Añadir a lista de path_infos si la imagen no es mayor que la original
                        if ($tgt_width <= $src_width && $tgt_height <= $src_height) {
                            $path_infos[] = [
                                $tgt_width, 
                                $tgt_height, 
                                $path . '-' . stripslashes($image_type['name']) . '.jpg'
                            ];
                        }
                        
                        // Eliminar miniaturas temporales
                        if (is_file(_PS_TMP_IMG_DIR_ . 'product_mini_' . $productId . '.jpg')) {
                            unlink(_PS_TMP_IMG_DIR_ . 'product_mini_' . $productId . '.jpg');
                        }
                        
                        if (is_file(_PS_TMP_IMG_DIR_ . 'product_mini_' . $productId . '_' . $currentShopId . '.jpg')) {
                            unlink(_PS_TMP_IMG_DIR_ . 'product_mini_' . $productId . '_' . $currentShopId . '.jpg');
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning("Error al redimensionar imagen para tipo " . $image_type['name'], [
                        'product_id' => $productId,
                        'error' => $e->getMessage()
                    ]);
                    // Continuar con los siguientes tipos
                }
            }
            
            // Eliminar archivo temporal
            @unlink($tempfile);
            
            return true;
        } catch (\Exception $e) {
            Log::error("Excepción al añadir imagen a producto", [
                'product_id' => $productId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            if (isset($tempfile) && file_exists($tempfile)) {
                @unlink($tempfile);
            }
            
            return false;
        }
    }

    /**
     * Encuentra la mejor ruta de imagen para redimensionar
     * 
     * Busca la imagen más pequeña que sea mayor o igual a las dimensiones objetivo
     *
     * @param int $tgt_width Ancho objetivo
     * @param int $tgt_height Alto objetivo
     * @param array $path_infos Lista de rutas de imagen con sus dimensiones
     * @return string Ruta de la mejor imagen
     */
    private function get_best_path(int $tgt_width, int $tgt_height, array $path_infos): string
    {
        // Ordenar path_infos de mayor a menor (para encontrar la imagen más pequeña que cumpla)
        $path_infos = array_reverse($path_infos);
        $path = '';
        
        foreach ($path_infos as $path_info) {
            list($width, $height, $path) = $path_info;
            
            // Si la imagen es suficientemente grande, usarla
            if ($width >= $tgt_width && $height >= $tgt_height) {
                return $path;
            }
        }
        
        // Si no hay ninguna imagen suficientemente grande, usar la última
        return $path;
    }

    /**
     * Crea un pedido en el ERP
     *
     * @param DtoOrderNote $orderNote Datos del pedido
     * @return bool True si se creó correctamente, false en caso contrario
     */
    public function createOrder(DtoOrderNote $orderNote): bool
    {
        try {
            Log::info("Iniciando creación de pedido", [
                'order_id' => $orderNote->code,
                'customer_id' => $orderNote->customerId
            ]);
            
            // Si estamos en modo test, simular éxito y salir
            if (self::isTestMode()) {
                Log::info("MODO PRUEBA: Simulando creacion exitosa de pedido", [
                    'order_id' => $orderNote->code
                ]);
                return true;
            }

            $url = $this->buildApiUrl(self::ENDPOINTS['B2C_ORDERS']);
            
            $orderData = $orderNote->toApiArray(true);
            
            $ch = CurlHelper::postPetition(
                $url,
                self::$dtoAccessToken->accessToken,
                $orderData,
                false
            );
            
            $response = CurlHelper::executeAndCheckCode($ch, 201, true);
            
            if (!$response) {
                Log::error("Error al crear pedido: respuesta vacía", [
                    'order_id' => $orderNote->code
                ]);
                return false;
            }
            
            // Verificar si el pedido se creó con errores
            if (isset($response['state']) && $response['state'] == 2) {
                Log::warning("Pedido creada con errores", [
                    'order_id' => $orderNote->code,
                    'error_info' => $response['errorInfo'] ?? 'No disponible'
                ]);
                return false;
            }
            
            Log::info("Pedido creado correctamente", [
                'order_id' => $orderNote->code,
                'customer_id' => $orderNote->customerId,
                'response_code' => $response['code'] ?? 'N/A'
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error("Error al crear el pedido", [
                'order_id' => $orderNote->code,
                'customer_id' => $orderNote->customerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }

    /**
     * Asigna marcas a productos basándose en características
     *
     * @return array Información sobre el proceso de asignación
     * @throws \Exception Si hay error en el proceso
     */
    public function asignBrandsToProduct(): array
    {
        try {
        
            $startTime = new \DateTimeImmutable();
            $products = \Product::getProducts(\Context::getContext()->language->id, 0, 0, 'id_product', 'ASC');
            
            $processedCount = 0;
            $updatedCount = 0;
            $errors = [];

            foreach ($products as $product) {
                try {
                    $features = \Product::getFeaturesStatic($product['id_product']);
                    
                    foreach ($features as $feature) {
                        $featureId = $feature['id_feature'];
                        
                        if ($featureId == 51) { // Identificador de la característica de marca
                            
                            $featureValueId = $feature['id_feature_value'];
                            $featureValues = \FeatureValue::getFeatureValueLang($featureValueId, \Context::getContext()->language->id);
                            
                            foreach ($featureValues as $featureValue) {
                                $dtoProduct = new \Product($product['id_product']);
                                
                                // Resetear marca actual
                                $dtoProduct->id_manufacturer = 0;
                                $dtoProduct->update();
                                
                                // Asignar nueva marca
                                $manufacturerId = \Manufacturer::getIdByName($featureValue['value']);
                                if ($manufacturerId) {
                                    $dtoProduct->id_manufacturer = $manufacturerId;
                                    $dtoProduct->update();
                                    $updatedCount++;
                                    
                                }
                            }
                        }
                    }
                    
                    $processedCount++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'product_id' => $product['id_product'],
                        'error' => $e->getMessage()
                    ];
                    
                    Log::warning("Error al asignar marca a producto", [
                        'product_id' => $product['id_product'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $endTime = new \DateTimeImmutable();
            $duration = $endTime->getTimestamp() - $startTime->getTimestamp();
            
            Log::info("Marcas asignadas a productos correctamente", [
                'total_products' => count($products),
                'processed' => $processedCount,
                'updated' => $updatedCount,
                'errors' => count($errors),
                'duration_seconds' => $duration
            ]);
            
            return [
                'success' => true,
                'total_products' => count($products),
                'processed' => $processedCount,
                'updated' => $updatedCount,
                'errors' => count($errors),
                'duration_seconds' => $duration
            ];
            
        } catch (\Exception $e) {
            Log::error("Error al asignar marcas a productos", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }

    }

    /**
     * Actualiza la dirección de un cliente basada en los datos de un pedido
     *
     * @param \Order $order Pedido
     * @param \Customer $customer Cliente
     * @return bool True si se actualizó correctamente, false en caso contrario
     */
    public function updateAddressOrderOnClient(\Order $order, \Customer $customer): bool
    {
        try {
            Log::info("Actualizando dirección de cliente desde pedido", [
                'order_id' => $order->id,
                'customer_id' => $customer->id
            ]);
            
            $id_address_delivery = (int)$order->id_address_delivery;
            $id_address_invoice = (int)$order->id_address_invoice;
            
            if (!$id_address_delivery || !$id_address_invoice) {
                Log::warning("IDs de dirección inválidos en el pedido", [
                    'order_id' => $order->id,
                    'delivery_address_id' => $id_address_delivery,
                    'invoice_address_id' => $id_address_invoice
                ]);
                return false;
            }
            
            // Crear DTO de cliente con TODAS las direcciones
            // Le pasamos las direcciones del pedido actual, pero dentro del método se
            // recuperarán todas las demás direcciones del cliente
            $dtoCliente = DtoCliente::fromPrestashopCustomer(
                $customer, 
                $id_address_delivery, 
                $id_address_invoice
            );

            if (!$dtoCliente) {
                Log::error("No se pudo crear DTO de cliente", [
                    'customer_id' => $customer->id
                ]);
                return false;
            }
            
            // API ID del cliente en el ERP
            $api_id = $dtoCliente->id;
            
            if (empty($api_id)) {
                // Si el cliente no tiene API ID, primero crearlo
                Log::warning("Cliente sin API ID, intentando crear", [
                    'customer_id' => $customer->id
                ]);
                
                $createResult = $this->createClient($dtoCliente);
                
                if (!$createResult || !isset($createResult['id'])) {
                    Log::error("No se pudo crear cliente en ERP", [
                        'customer_id' => $customer->id
                    ]);
                    return false;
                }

                // Actualizar el API ID del cliente
                $dtoCliente->id = $createResult['id'];
                DtoCliente::addId((int)$customer->id, $createResult['id']);
                
                Log::info("Cliente creado en ERP", [
                    'customer_id' => $customer->id,
                    'api_id' => $createResult['id'],
                    'address_count' => count($dtoCliente->shippingAddresses)
                ]);
                
                return true;
            }
            
            // Actualizar cliente existente con todas sus direcciones
            $updated = $this->updateClient($dtoCliente);
            
            if (!$updated) {
                Log::warning("No se actualizó correctamente la dirección del cliente", [
                    'customer_id' => $customer->id,
                    'api_id' => $dtoCliente->id
                ]);
            } else {
                Log::info("Cliente actualizado correctamente con todas sus direcciones", [
                    'customer_id' => $customer->id,
                    'api_id' => $dtoCliente->id,
                    'address_count' => count($dtoCliente->shippingAddresses)
                ]);
            }
            
            return $updated;

        } catch (\Exception $e) {
            Log::error("Error al actualizar dirección de cliente desde pedido", [
                'order_id' => $order->id,
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }
    
}
