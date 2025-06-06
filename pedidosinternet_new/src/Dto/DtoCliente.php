<?php
declare(strict_types=1);

namespace PedidosInternet\Dto;

use PedidosInternet\Log;
use Customer;

/**
 * DTO para representar clientes
 * 
 * Facilita la conversión entre el modelo de cliente de PrestaShop y el modelo de Distrib
 */
class DtoCliente
{
    public string $cif;
    public string $email;
    public ?string $phone;
    public bool $confirmSaleNotes;
    public bool $ignoreProductDivision;
    public string $registrationDate;
    public int $rateId;
    /** @var DtoClienteShippingAddress[] */
    public array $shippingAddresses = [];
    public DtoClienteInvoiceAddress $invoiceAddress;
    /** @var int[] */
    public array $roles = [];
    public int $groupId;
    public bool $isARemovedClient;
    public int $id;
    public string $tradeName;
    public string $businessName;
    public int $webId;
    public int $clientTypeId;
    public string $lastUpdateDate;
    public int $salesmanId;
    public ?string $salesmanName;
    public ?string $salesmanSurname;
    public bool $isDisabledForBuying;

    public function __construct()
    {
        $this->invoiceAddress = new DtoClienteInvoiceAddress();
    }

    /**
     * Crea un objeto DtoCliente a partir de datos del API
     * 
     * @param array $client Datos del cliente desde el API
     * @return DtoCliente|null Cliente creado o null si los datos no son válidos
     */
    public static function create(array $client) : ?DtoCliente
    {
        try {
            // El primer registro del API puede venir nulo
            if (empty($client['cif'])) {
                Log::warning('Cliente sin CIF recibido del API', ['client_data' => $client]);
                return null;
            }   
            
            $toRet = new DtoCliente();
            $toRet->cif = $client['cif'];
            $toRet->email = $client['email'];
            $toRet->phone = $client['phone'];
            $toRet->confirmSaleNotes = $client['confirmSaleNotes'] ?? false;
            $toRet->ignoreProductDivision = $client['ignoreProductDivision'] ?? false;
            $toRet->registrationDate = $client['registrationDate'] ?? date('Y-m-d\TH:i:s.000\Z');
            $toRet->rateId = isset($client['rateId']) ? (int)$client['rateId'] : 1;

            // Procesar direcciones de envío
            if (isset($client['shippingAddresses']) && is_array($client['shippingAddresses'])) {
                foreach ($client['shippingAddresses'] as $address) {
                    $shippingAddress = DtoClienteShippingAddress::create($address);
                    if ($shippingAddress) {
                        $toRet->shippingAddresses[] = $shippingAddress;
                    }
                }
            }

            // Procesar dirección de facturación
            if (isset($client['invoiceAddress'])) {
                $toRet->invoiceAddress = DtoClienteInvoiceAddress::create($client['invoiceAddress']);
            }

            // Asignar rol por defecto si no viene
            $toRet->roles = isset($client['roles']) && is_array($client['roles']) ? $client['roles'] : [1];

            $toRet->isARemovedClient = $client['isARemovedClient'] ?? false;
            $toRet->id = isset($client['id']) ? (int)$client['id'] : 0;
            $toRet->tradeName = $client['tradeName'] ?? '';
            $toRet->businessName = $client['businessName'] ?? '';
            $toRet->webId = isset($client['webId']) ? (int)$client['webId'] : 0;
            $toRet->clientTypeId = isset($client['clientTypeId']) ? (int)$client['clientTypeId'] : 0;
            $toRet->lastUpdateDate = $client['lastUpdateDate'] ?? date('Y-m-d\TH:i:s.000\Z');
            $toRet->salesmanId = isset($client['salesmanId']) ? (int)$client['salesmanId'] : 0;
            $toRet->salesmanName = $client['salesmanName'] ?? null;
            $toRet->salesmanSurname = $client['salesmanSurname'] ?? null;
            $toRet->isDisabledForBuying = $client['isDisabledForBuying'] ?? false;
                
            return $toRet;
        } catch (\Exception $e) {
            Log::error('Error al crear DtoCliente', [
                'client_data' => $client ?? [],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Convierte el DTO a un array para enviar al API
     * 
     * @param bool $encodeAsJson Si ciertos campos deben codificarse como JSON
     * @param \Address|null $address Dirección específica a incluir
     * @return array Datos formateados para el API
     */
    public function toApiArray(bool $encodeAsJson = true, \Address $address = null): array
    {
        try {
            // Determinar qué direcciones utilizar
            $addresses = $address ? [DtoClienteShippingAddress::fromPrestashop(get_object_vars($address))[0]] : $this->shippingAddresses;
            
            // Usar businessName si está disponible, de lo contrario tradeName
            $business = !empty($this->businessName) ? $this->businessName : $this->tradeName;

            return [
                "CIF" => $this->cif,
                "Email" => $this->email,
                "Phone" => $this->phone,
                "ConfirmSaleNotes" => $this->confirmSaleNotes,
                "IgnoreProductDivision" => $this->ignoreProductDivision,
                "registrationDate" => $this->registrationDate,
                "rateId" => $this->rateId,
                "ShippingAddresses" => array_map(
                    fn($address) => DtoClienteShippingAddress::toApiArray($address),
                    $addresses
                ),
                "InvoiceAddress" => $encodeAsJson ?
                    json_encode($this->invoiceAddress->toApiArray()) :
                    $this->invoiceAddress->toApiArray(),
                "Roles" => $encodeAsJson ?
                    json_encode($this->roles) :
                    $this->roles,
                "IsARemovedClient" => $this->isARemovedClient,
                "Id" => $this->id,
                "TradeName" => $this->tradeName,
                "BusinessName" => $business,
                "WebId" => $this->webId,
                "LastUpdateDate" => $this->lastUpdateDate,
            ];
        } catch (\Exception $e) {
            Log::error('Error al convertir DtoCliente a array para API', [
                'client_id' => $this->id,
                'webId' => $this->webId,
                'error' => $e->getMessage()
            ]);
            
            // Retornar datos mínimos para evitar error completo
            return [
                "CIF" => $this->cif ?? 'ERROR',
                "Email" => $this->email ?? 'error@example.com',
                "Id" => $this->id ?? 0
            ];
        }
    }


    /**
     * Crea un DtoCliente a partir de un cliente de PrestaShop
     * 
     * @param Customer $customer Cliente de PrestaShop
     * @param int|null $id_address_delivery ID de la dirección de entrega
     * @param int|null $id_address_invoice ID de la dirección de facturación
     * @return DtoCliente
     */
    public static function fromPrestashopCustomer(Customer $customer, $id_address_delivery = null, $id_address_invoice = null): DtoCliente
    {
        try {
            $toRet = new DtoCliente();
            $random_cif = uniqid();

            // Obtener todas las direcciones asociadas al cliente
            $allAddresses = $customer->getAddresses((int) \Configuration::get('PS_LANG_DEFAULT'));

            // Si el cliente no tiene direcciones, crear una dirección mínima por defecto
            if (empty($allAddresses)) {
                Log::warning("Cliente sin direcciones al crear DtoCliente", [
                    'customer_id' => $customer->id
                ]);
                
                // Crear dirección de envío mínima
                $defaultShippingAddress = new DtoClienteShippingAddress();
                $defaultShippingAddress->idCountry = 66; // País por defecto
                $defaultShippingAddress->address = 'Dirección por defecto';
                $defaultShippingAddress->townName = 'Ciudad por defecto';
                $defaultShippingAddress->postalCode = '00000';
                $toRet->shippingAddresses[] = $defaultShippingAddress;
                
                // Crear dirección de facturación mínima
                $toRet->invoiceAddress = new DtoClienteInvoiceAddress();
                $toRet->invoiceAddress->idCountry = 66;
                $toRet->invoiceAddress->address = 'Dirección por defecto';
                $toRet->invoiceAddress->postalCode = '00000';
                
                // Extraer información básica
                $cif = $random_cif;
                $phone = "0";

                // Completar datos del cliente
                $completeName = $customer->firstname . " " . $customer->lastname;
                $customerCompany = $customer->company;

                $toRet->cif = $cif;
                $toRet->email = $customer->email;
                $toRet->phone = $phone;
                $toRet->confirmSaleNotes = false;
                $toRet->ignoreProductDivision = false;
                $toRet->registrationDate = date("Y-m-d\TH:i:s.000\Z", strtotime($customer->date_add));
                $toRet->rateId = 1;
                $toRet->roles[0] = !empty($customerCompany) ? 1 : 2; // B2B si tiene empresa, B2C si no
                $toRet->groupId = 0;

                // Obtener ID de API si existe
                $api_id = 0;
                $prestashopId = (int)$customer->id;
                $storedApiId = self::idByUser($prestashopId);
                if ($storedApiId) {
                    $api_id = (int)$storedApiId;
                }

                $toRet->id = $api_id;
                $toRet->isARemovedClient = false;
                $toRet->tradeName = $completeName;
                $toRet->businessName = $customerCompany ?? $completeName;
                $toRet->webId = $prestashopId;
                $toRet->lastUpdateDate = date("Y-m-d\TH:i:s.000\Z", strtotime($customer->date_upd));
                
                return $toRet;
            }

            // Información de facturación
            $invoiceAddress = null;
            if ($id_address_invoice !== null) {
                $invoiceAddress = $customer->getSimpleAddress($id_address_invoice);
            } elseif (!empty($allAddresses)) {
                // Si no se especifica, usar la primera
                $invoiceAddress = $customer->getSimpleAddress($allAddresses[0]['id_address']);
            }
            
            // Procesar todas las direcciones de envío
            $toRet->shippingAddresses = [];
            
            // Si hay una dirección de envío específica para el pedido actual, ponerla primero
            if ($id_address_delivery !== null) {
                $primaryShippingAddress = $customer->getSimpleAddress($id_address_delivery);
                $shippingAddresses = DtoClienteShippingAddress::fromPrestashop($primaryShippingAddress);
                if (!empty($shippingAddresses)) {
                    $toRet->shippingAddresses = $shippingAddresses;
                }
            }
            
            // Añadir el resto de direcciones del cliente que no sean la dirección de envío actual
            foreach ($allAddresses as $address) {
                // Evitar duplicar la dirección principal de envío si ya la hemos añadido
                if ($id_address_delivery !== null && $address['id_address'] == $id_address_delivery) {
                    continue;
                }
                
                $addressData = $customer->getSimpleAddress($address['id_address']);
                $additionalAddresses = DtoClienteShippingAddress::fromPrestashop($addressData);
                
                if (!empty($additionalAddresses)) {
                    foreach ($additionalAddresses as $addr) {
                        $toRet->shippingAddresses[] = $addr;
                    }
                }
            }
            
            // Asegurar que tenemos al menos una dirección de envío
            if (empty($toRet->shippingAddresses) && !empty($allAddresses)) {
                // Usar la primera dirección disponible
                $firstAddress = $customer->getSimpleAddress($allAddresses[0]['id_address']);
                $shippingAddresses = DtoClienteShippingAddress::fromPrestashop($firstAddress);
                if (!empty($shippingAddresses)) {
                    $toRet->shippingAddresses = $shippingAddresses;
                }
            }
            
            // Extraer información de facturación
            $cif = $random_cif;
            $phone = "0";
            
            if ($invoiceAddress) {
                if (isset($invoiceAddress['dni']) && isset($invoiceAddress['phone'])) {
                    $cif = $invoiceAddress['dni'];
                    $phone = $invoiceAddress['phone'];
                }
                $toRet->invoiceAddress = DtoClienteInvoiceAddress::fromPrestashop($invoiceAddress);
            } else if (!empty($allAddresses)) {
                // Asegurar que siempre haya una dirección de facturación
                $firstAddress = $customer->getSimpleAddress($allAddresses[0]['id_address']);
                $toRet->invoiceAddress = DtoClienteInvoiceAddress::fromPrestashop($firstAddress);
            }
            
            // Obtener ID de API si existe
            $api_id = 0;
            $prestashopId = (int)$customer->id;
            $storedApiId = self::idByUser($prestashopId);
            if ($storedApiId) {
                $api_id = (int)$storedApiId;
            }

            // Completar datos del cliente
            $completeName = $customer->firstname . " " . $customer->lastname;
            $customerCompany = $customer->company;

            $toRet->cif = $cif;
            $toRet->email = $customer->email;
            $toRet->phone = $phone;
            $toRet->confirmSaleNotes = false;
            $toRet->ignoreProductDivision = false;
            $toRet->registrationDate = date("Y-m-d\TH:i:s.000\Z", strtotime($customer->date_add));
            $toRet->rateId = 1;
            $toRet->roles[0] = !empty($customerCompany) ? 1 : 2; // B2B si tiene empresa, B2C si no
            $toRet->groupId = 0;
            $toRet->id = $api_id;
            $toRet->isARemovedClient = false;
            $toRet->tradeName = $completeName;
            $toRet->businessName = $customerCompany ?? $completeName;
            $toRet->webId = $prestashopId;
            $toRet->lastUpdateDate = date("Y-m-d\TH:i:s.000\Z", strtotime($customer->date_upd));
            
            Log::info('DtoCliente creado desde cliente PrestaShop', [
                'prestashop_id' => $prestashopId,
                'api_id' => $api_id,
                'email' => $customer->email,
                'address_count' => count($toRet->shippingAddresses)
            ]);
            
            return $toRet;
        } catch (\Exception $e) {
            Log::error('Error al crear DtoCliente desde cliente PrestaShop', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Crear un cliente con datos mínimos para evitar errores
            $toRet = new DtoCliente();
            $toRet->cif = uniqid();
            $toRet->email = $customer->email ?? 'error@example.com';
            $toRet->webId = (int)$customer->id;
            $toRet->tradeName = $customer->firstname . ' ' . $customer->lastname;
            $toRet->businessName = $customer->company ?? $toRet->tradeName;
            
            return $toRet;
        }
    }

    /**
     * Obtiene el ID de cliente de PrestaShop a partir del ID de API
     * 
     * @param int $apiId ID del cliente en el API
     * @return int|false ID de cliente en PrestaShop o false si no existe
     */
    public static function userById(int $apiId)
    {
        try {
            return \Db::getInstance()->getValue(
                "SELECT id_customer FROM " . _DB_PREFIX_ . "customer WHERE api_id = " . (int)$apiId
            );
        } catch (\Exception $e) {
            Log::error('Error al buscar cliente por API ID', [
                'api_id' => $apiId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtiene el ID de API a partir del ID de cliente de PrestaShop
     * 
     * @param int $customerId ID del cliente en PrestaShop
     * @return int|false ID del cliente en el API o false si no existe
     */
    public static function idByUser(int $customerId)
    {
        try {
            return \Db::getInstance()->getValue(
                "SELECT api_id FROM " . _DB_PREFIX_ . "customer WHERE id_customer = " . (int)$customerId
            );
        } catch (\Exception $e) {
            Log::error('Error al buscar API ID por ID de cliente', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Asocia un ID de API a un cliente de PrestaShop
     * 
     * @param int $customerId ID del cliente en PrestaShop
     * @param int $apiId ID del cliente en el API
     * @return bool Resultado de la operación
     */
    public static function addId(int $customerId, int $apiId): bool
    {
        try {
            $result = \Db::getInstance()->execute(
                "UPDATE " . _DB_PREFIX_ . "customer SET api_id = " . (int)$apiId . " WHERE id_customer = " . (int)$customerId
            );
            
            if (!$result) {
                Log::warning('No se pudo asignar API ID a cliente', [
                    'customer_id' => $customerId,
                    'api_id' => $apiId
                ]);
            }
            
            return $result;
        } catch (\Exception $e) {
            Log::error('Error al asignar API ID a cliente', [
                'customer_id' => $customerId,
                'api_id' => $apiId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

}
