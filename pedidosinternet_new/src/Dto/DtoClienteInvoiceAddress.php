<?php
declare(strict_types=1);

namespace PedidosInternet\Dto;

use PedidosInternet\Helpers\StringHelper;
use PedidosInternet\Log;


/**
 * DTO para representar direcciones de facturación
 */
class DtoClienteInvoiceAddress
{
    public int $idCountry;
    public int $idCommunity;
    public int $idProvince;
    public string $postalCode;
    public string $contactName;
    public string $countryName;
    public string $communityName;
    public string $provinceName;
    public string $townName;
    public string $address;
    public ?int $shippingAddressId;
    public ?int $populationId;

    /**
     * Crea un objeto DtoClienteInvoiceAddress a partir de datos del API
     * 
     * @param array $invoiceAddress Datos de la dirección desde el API
     * @return DtoClienteInvoiceAddress
     */
    public static function create(array $invoiceAddress)
    {

        try {
            $toRet = new DtoClienteInvoiceAddress();

            $toRet->idCountry = isset($invoiceAddress['idCountry']) ? (int)$invoiceAddress['idCountry'] : 0;
            $toRet->idCommunity = isset($invoiceAddress['idCommunity']) ? (int)$invoiceAddress['idCommunity'] : 0;
            $toRet->idProvince = isset($invoiceAddress['idProvince']) ? (int)$invoiceAddress['idProvince'] : 0;
            $toRet->postalCode = $invoiceAddress['postalCode'] ?? '';
            $toRet->contactName = $invoiceAddress['contactName'] ?? '';
            $toRet->countryName = $invoiceAddress['countryName'] ?? '';
            $toRet->communityName = $invoiceAddress['communityName'] ?? '';
            $toRet->provinceName = $invoiceAddress['provinceName'] ?? '';
            $toRet->townName = $invoiceAddress['townName'] ?? '';
            $toRet->address = empty($invoiceAddress['address']) ? 'No definida' : $invoiceAddress['address'];
            $toRet->shippingAddressId = empty($invoiceAddress['shippingAddressId']) ? null : (int)$invoiceAddress['shippingAddressId'];
            $toRet->populationId = isset($invoiceAddress['populationId']) ? (int)$invoiceAddress['populationId'] : null;

            return $toRet;
        } catch (\Exception $e) {
            Log::error('Error al crear DtoClienteInvoiceAddress', [
                'address_data' => $invoiceAddress ?? [],
                'error' => $e->getMessage()
            ]);
            
            // Crear dirección mínima para evitar errores
            $toRet = new DtoClienteInvoiceAddress();
            $toRet->idCountry = 66;
            $toRet->postalCode = '00000';
            $toRet->address = 'No definida';
            
            return $toRet;
        }
    }

    /**
     * Convierte el DTO a un array para enviar al API
     * 
     * @return array Datos formateados para el API
     */
    public function toApiArray(): array
    {
        try {
            return [
                "idCountry" => $this->idCountry ?? 66,
                "idCommunity" => $this->idCommunity ?? 1,
                "idProvince" => $this->idProvince ?? 1,
                "postalCode" => $this->postalCode ?? "",
                "contactName" => $this->contactName ?? "",
                "countryName" => $this->countryName ?? "",
                "communityName" => $this->communityName ?? "",
                "provinceName" => $this->provinceName ?? "",
                "townName" => $this->townName ?? "",
                "address" => $this->address ?? "No definida",
                "shippingAddressId" => $this->shippingAddressId ?? 0,
                "populationId" => $this->populationId ?? 0
            ];
        } catch (\Exception $e) {
            Log::error('Error al convertir DtoClienteInvoiceAddress a array para API', [
                'error' => $e->getMessage()
            ]);
            
            // Devolver datos mínimos para evitar error completo
            return [
                "idCountry" => 66,
                "idProvince" => 1,
                "postalCode" => "",
                "address" => "No definida"
            ];
        }
    }

    /**
     * Crea un DtoClienteInvoiceAddress a partir de una dirección de PrestaShop
     * 
     * @param array $address Datos de la dirección de PrestaShop
     * @return DtoClienteInvoiceAddress
     */
    public static function fromPrestashop(array $address): DtoClienteInvoiceAddress
    {
        try {
            $toRet = new DtoClienteInvoiceAddress();

            // Obtener nombre de la provincia/estado si está disponible
            if (isset($address['id_state']) && $address['id_state'] > 0) {
                $state_name = \State::getNameById($address['id_state']);
                $toRet->provinceName = $state_name ?: ($address['state'] ?? '');
            } else {
                $toRet->provinceName = isset($address['state']) ? $address['state'] : '';
            }
            
            $toRet->idCountry = isset($address['id_country']) ? (int)$address['id_country'] : 0;
            $toRet->idCommunity = 0; // No disponible en PrestaShop por defecto
            $toRet->idProvince = isset($address['id_state']) ? (int)$address['id_state'] : 0;
            $toRet->postalCode = $address['postcode'] ?? '';
            $toRet->contactName = ($address['firstname'] ?? '') . ' ' . ($address['lastname'] ?? '');
            $toRet->countryName = $address['country'] ?? '';
            $toRet->communityName = '';
            $toRet->townName = $address['city'] ?? '';
            
            // Combinar direcciones principal y secundaria
            $addressLines = [];
            if (!empty($address['address1'])) $addressLines[] = $address['address1'];
            if (!empty($address['address2'])) $addressLines[] = $address['address2'];
            $toRet->address = !empty($addressLines) ? implode(' ', $addressLines) : 'No definida';
            
            $toRet->shippingAddressId = 0;
            
            return $toRet;
        } catch (\Exception $e) {
            Log::error('Error al crear DtoClienteInvoiceAddress desde dirección PrestaShop', [
                'address_data' => $address ?? [],
                'error' => $e->getMessage()
            ]);
            
            // Crear dirección mínima para evitar errores
            $toRet = new DtoClienteInvoiceAddress();
            $toRet->idCountry = 0;
            $toRet->postalCode = '';
            $toRet->address = 'No definida';
            
            return $toRet;
        }
    }

    /**
     * Guarda la dirección de facturación en PrestaShop
     * 
     * @param DtoCliente $dtoCliente Cliente propietario de la dirección
     * @param \Customer $customer Cliente de PrestaShop
     * @return int ID de la dirección creada/actualizada
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
            $address->city = $this->townName ?? "-";
            $address->phone = is_numeric($dtoCliente->phone) ? $dtoCliente->phone : '';
            $address->dni = $dtoCliente->cif;
            $address->company = $dtoCliente->businessName;

            // Separar el nombre en nombre y apellido
            [$firstname, $lastname] = StringHelper::separateNames($this->contactName);
            $address->firstname = $firstname;
            $address->lastname = $lastname;
            $address->id_customer = (int)$customer->id;
            $address->id_state = $this->idProvince > 0 ? $this->idProvince : 0;
            $address->alias = 'Facturación';

            // Guardar la dirección
            if (\Validate::isLoadedObject($address)) {
                $address->update();
            } else {
                $address->add();
            }
            
            return (int)$address->id;
        } catch (\Exception $e) {
            return 0;
        }
    }
}
