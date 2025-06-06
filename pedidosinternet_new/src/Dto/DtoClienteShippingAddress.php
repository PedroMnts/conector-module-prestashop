<?php
declare(strict_types=1);

namespace PedidosInternet\Dto;

use PedidosInternet\Helpers\StringHelper;
use PedidosInternet\Log;

/**
 * DTO para representar direcciones de envío
 * 
 * Facilita la conversión entre el modelo de dirección de envío de PrestaShop
 * y el modelo de Distrib
 */
class DtoClienteShippingAddress
{
    public int $idCountry = 0;
    public int $idCommunity = 0;
    public int $idProvince = 0;
    public string $postalCode = '';
    public string $contactName = '';
    public string $countryName = '';
    public string $communityName = '';
    public string $provinceName = '';
    public string $townName = '';
    public string $address = '';
    public ?int $shippingAddressId = null;
    public string $phoneNumber1 = '';
    public string $phoneNumber2 = '';
    public string $shippingObservations = '';

    /**
     * Crea un objeto DtoClienteShippingAddress a partir de datos del API
     * 
     * @param array $shippingAddress Datos de la dirección desde el API
     * @return DtoClienteShippingAddress|null DTO creado o null si faltan datos obligatorios
     */
    public static function create(array $shippingAddress) : ?DtoClienteShippingAddress
    {
        try {
            // Validar que tiene los datos mínimos necesarios
            if (empty($shippingAddress['townName']) || empty($shippingAddress['address'])) {
                return null;
            }
            
            $toRet = new DtoClienteShippingAddress();

            $toRet->idCountry = isset($shippingAddress['idCountry']) ? (int)$shippingAddress['idCountry'] : 0;
            $toRet->idCommunity = isset($shippingAddress['idCommunity']) ? (int)$shippingAddress['idCommunity'] : 0;
            $toRet->idProvince = isset($shippingAddress['idProvince']) ? (int)$shippingAddress['idProvince'] : 0;
            $toRet->postalCode = $shippingAddress['postalCode'] ?? '';
            $toRet->contactName = $shippingAddress['contactName'] ?? '';
            $toRet->countryName = $shippingAddress['countryName'] ?? '';
            $toRet->communityName = $shippingAddress['communityName'] ?? '';
            $toRet->provinceName = $shippingAddress['provinceName'] ?? '';
            $toRet->townName = $shippingAddress['townName'];
            $toRet->address = $shippingAddress['address'];
            $toRet->shippingAddressId = isset($shippingAddress['shippingAddressId']) && $shippingAddress['shippingAddressId'] > 0 
                ? (int)$shippingAddress['shippingAddressId'] 
                : null;
            $toRet->phoneNumber1 = $shippingAddress['phoneNumber1'] ?? '';
            $toRet->phoneNumber2 = $shippingAddress['phoneNumber2'] ?? '';
            $toRet->shippingObservations = $shippingAddress['shippingObservations'] ?? '';

            return $toRet;
        } catch (\Exception $e) {
            Log::error('Error al crear DtoClienteShippingAddress', [
                'address_data' => $shippingAddress ?? [],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Convierte el DTO a un array para enviar al API
     * 
     * @return array Datos formateados para el API
     */
    public static function toApiArray(DtoClienteShippingAddress $shippingAddress): array
    {
        try {
            return [
                "idCountry" => $shippingAddress->idCountry,
                "idCommunity" => $shippingAddress->idCommunity,
                "idProvince" => $shippingAddress->idProvince,
                "postalCode" => $shippingAddress->postalCode,
                "contactName" => $shippingAddress->contactName,
                "countryName" => $shippingAddress->countryName,
                "communityName" => $shippingAddress->communityName,
                "provinceName" => $shippingAddress->provinceName,
                "townName" => $shippingAddress->townName,
                "address" => $shippingAddress->address,
                "shippingAddressId" => $shippingAddress->shippingAddressId ?? 0,
                "phoneNumber1" => $shippingAddress->phoneNumber1,
                "phoneNumber2" => $shippingAddress->phoneNumber2,
                "shippingObservations" => $shippingAddress->shippingObservations,
            ];
        } catch (\Exception $e) {
            Log::error('Error al convertir DtoClienteShippingAddress a array para API', [
                'error' => $e->getMessage()
            ]);
            
            // Devolver datos mínimos para evitar error completo
            return [
                "idCountry" => 66,
                "idProvince" => 0,
                "postalCode" => "",
                "address" => "No definida",
                "townName" => "No definida",
                "shippingAddressId" => 0
            ];
        }
    }

    /**
     * Crea un array de DTOs a partir de una dirección de PrestaShop
     * 
     * @param array $address Datos de la dirección de PrestaShop
     * @return array Array con un DtoClienteShippingAddress
     */
    public static function fromPrestashop(array $address) : array
    {
        try {
            // Validar entrada para evitar problemas
            if (empty($address) || !is_array($address)) {
                Log::warning("Datos de dirección vacíos o no válidos", [
                    'address' => $address
                ]);
                return [self::createDefaultAddress()];
            }
            
            $toRet = new DtoClienteShippingAddress();

            // Obtener nombre del estado/provincia si está disponible
            if (isset($address['id_state']) && $address['id_state'] > 0) {
                try {
                    $state_name = \State::getNameById($address['id_state']);
                    $toRet->provinceName = $state_name ?: ($address['state'] ?? '');
                } catch (\Exception $e) {
                    // Si falla la obtención del estado, usar el valor directo si existe
                    Log::warning("Error al obtener nombre de estado/provincia", [
                        'id_state' => $address['id_state'],
                        'error' => $e->getMessage()
                    ]);
                    $toRet->provinceName = $address['state'] ?? '';
                }
            } else {
                $toRet->provinceName = isset($address['state']) ? $address['state'] : '';
            }

            // Asignar valores con verificaciones
            $toRet->idCountry = isset($address['id_country']) ? (int)$address['id_country'] : 0;
            $toRet->idCommunity = 0; // No disponible en PrestaShop por defecto
            $toRet->idProvince = isset($address['id_state']) ? (int)$address['id_state'] : 0;
            $toRet->postalCode = $address['postcode'] ?? '';
            $toRet->contactName = ($address['firstname'] ?? '') . ' ' . ($address['lastname'] ?? '');
            $toRet->countryName = $address['country'] ?? '';
            $toRet->communityName = '';
            $toRet->townName = $address['city'] ?? '';
            
            // Combinar direcciones principal y secundaria con mejor manejo
            $addressLines = [];
            if (!empty($address['address1'])) $addressLines[] = trim($address['address1']);
            if (!empty($address['address2'])) $addressLines[] = trim($address['address2']);
            $toRet->address = !empty($addressLines) ? implode(' ', $addressLines) : 'No definida';
            
            // Asegurar que el ID es un entero
            $toRet->shippingAddressId = isset($address['id_address']) ? (int)$address['id_address'] : 0;
            
            // Gestionar campos de teléfono con validación
            $toRet->phoneNumber1 = !empty($address['phone']) ? $address['phone'] : '';
            $toRet->phoneNumber2 = !empty($address['phone_mobile']) ? $address['phone_mobile'] : '';
            $toRet->shippingObservations = '';

            return [$toRet];
        } catch (\Exception $e) {
            Log::error('Error al crear DtoClienteShippingAddress desde dirección PrestaShop', [
                'address_data' => $address ?? [],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Crear dirección mínima para evitar error completo
            return [self::createDefaultAddress()];
        }
    }

    /**
     * Crea una dirección por defecto con valores mínimos
     * 
     * @return DtoClienteShippingAddress
     */
    private static function createDefaultAddress(): DtoClienteShippingAddress
    {
        $defaultAddress = new DtoClienteShippingAddress();
        $defaultAddress->idCountry = 0;
        $defaultAddress->townName = 'No definida';
        $defaultAddress->address = 'No definida';
        $defaultAddress->postalCode = '';
        
        return $defaultAddress;
    }

    /**
     * Guarda esta dirección en un objeto Address de PrestaShop
     *
     * @param DtoCliente $dtoCliente Cliente propietario de la dirección
     * @param \Customer $customer Cliente de PrestaShop
     * @return int ID de la dirección creada/actualizada o -1 si hay error
     */
    public function toPrestashop(DtoCliente $dtoCliente, \Customer $customer): int
    {
        try {
            // Verificar si existe la dirección por su ID
            $address = new \Address($this->shippingAddressId ?: 0);
            
            // Configurar datos de la dirección
            $address->id_country = ($this->idCountry === 0 || $this->idCountry === 66) ? 6 : $this->idCountry;
            $address->address1 = (strlen($this->address) < 128) ? $this->address : substr($this->address, 0, 127);
            $address->postcode = $this->postalCode;
            $address->city = $this->townName ?? "No definida";
            $address->phone = is_numeric($dtoCliente->phone) ? $dtoCliente->phone : '';
            $address->dni = $dtoCliente->cif;
            $address->company = $dtoCliente->businessName;

            // Separar el nombre en nombre y apellido
            [$firstname, $lastname] = StringHelper::separateNames($this->contactName);
            if ($firstname === null || $lastname === null) {
                Log::warning('No se pudo separar el nombre de contacto', [
                    'contact_name' => $this->contactName,
                    'customer_id' => $customer->id
                ]);
                return -1;
            }
            
            $address->firstname = $firstname;
            $address->lastname = $lastname;
            $address->id_customer = (int)$customer->id;
            $address->id_state = $this->idProvince > 0 ? $this->idProvince : 0;
            $address->alias = 'Envío';

            // Guardar la dirección
            if (\Validate::isLoadedObject($address)) {
                $address->update();

            } else {
                $address->add();

            }
            
            return (int)$address->id;
        } catch (\PrestaShopException $e) {
            Log::error('Error al guardar direccion de envío en PrestaShop', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
                'address_data' => [
                    'postcode' => $this->postalCode,
                    'city' => $this->townName,
                    'country' => $this->idCountry
                ]
            ]);
            return -1;
        } catch (\Exception $e) {
            Log::error('Error general al guardar direccion de envío', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage()
            ]);
            return -1;
        }
    }

}