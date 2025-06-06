<?php
declare(strict_types=1);

namespace PedidosInternet\Dto;

use PedidosInternet\Helpers\StringHelper;

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

    public static function create(array $invoiceAddress)
    {
        $toRet = new DtoClienteInvoiceAddress();

        $toRet->idCountry = $invoiceAddress['idCountry'];
        $toRet->idCommunity = $invoiceAddress['idCommunity'];
        $toRet->idProvince = $invoiceAddress['idProvince'];
        $toRet->postalCode = $invoiceAddress['postalCode'];
        $toRet->contactName = $invoiceAddress['contactName'] ?? "";
        $toRet->countryName = $invoiceAddress['countryName'];
        $toRet->communityName = $invoiceAddress['communityName'] ?? "";
        $toRet->provinceName = $invoiceAddress['provinceName'] ?? "";
        $toRet->townName = $invoiceAddress['townName'];
        $toRet->address = empty($invoiceAddress['address']) ? 'No definida' : $invoiceAddress['address'];
        $toRet->shippingAddressId =
            empty($invoiceAddress['shippingAddressId']) ?
                null :
                $invoiceAddress['shippingAddressId']
        ;

        return $toRet;
    }

    /**
     * @return array
     */
    public function toApiArray() : array
    {
        return [
            "idCountry" => $this->idCountry ?? 66,
            "idCommunity" => $this->idCommunity ?? 1,
            "idProvince" => $this->idProvince ?? 1,
            "postalCode" => $this->postalCode ?? "string",
            "contactName" => $this->contactName ?? "string",
            "countryName" => $this->countryName ?? "string",
            "communityName" => $this->communityName ?? "string",
            "provinceName" => $this->provinceName ?? "string",
            "townName" => $this->townName ?? "string",
            "address" => $this->address ?? "No definida",
            "shippingAddressId" => $this->shippingAddressId ?? 1,
        ];

    }

    public static function fromPrestashop(array $address): DtoClienteInvoiceAddress
    {
        $toRet = new DtoClienteInvoiceAddress();

        if (isset($address['id_state'])) {
            $state_name = \State::getNamebyID($address['id_state']);
            $toRet->provinceName = $state_name ? $state_name : $address['state'];
        } else {
            $toRet->provinceName = "";
        }
        $toRet->idCountry = intval($address['id_country']);
        $toRet->idCommunity = 0;
        $toRet->idProvince = intval($address['id_state']);
        $toRet->postalCode = $address['postcode'];
        $toRet->contactName = $address['firstname'] . ' ' . $address['lastname'];
        $toRet->countryName = $address['country'];
        $toRet->communityName = "";
        $toRet->townName = $address['city'];
        $toRet->address = $address['address1'] . ' ' . $address['address2'];
        $toRet->shippingAddressId = 0;

        return $toRet;
    }

    /**
     * Guarda esta dirección en un objeto Address de Prestashop indicando como alias 'Facturación'
     *
     * @param DtoCliente $dtoCliente
     * @param \Customer $customer
     * @return int ID de la dirección creada/actualizada
     * @throws \PrestaShopException
     */
    public function toPrestashop(DtoCliente $dtoCliente, \Customer $customer): int
    {
        $address = new \Address($this->shippingAddressId, 1);
        $address->id_country =
            (empty($this->idCountry) || ($this->idCountry === 66)) ?
                6 :
                $this->idCountry;
        $address->address1 = (strlen($this->address) < 128) ? $this->address : substr($this->address, 0, 127);
        $address->postcode = $this->postalCode;
        $address->city = $this->townName ?? "-";
        $address->phone = is_numeric($dtoCliente->phone) ? $dtoCliente->phone : '';
        $address->dni = $dtoCliente->cif;
        $address->company = $dtoCliente->businessName;

        [$firstname, $lastname] = StringHelper::separateNames($this->contactName);
        $address->firstname = $firstname;
        $address->lastname = $lastname;
        $address->id_customer = (int) $customer->id;
        $address->id_state = 0;
        $address->alias = 'Facturación';

        try {
            $address->save();
        }catch (\PrestaShopException $ex) {
            dump($address);
            dump($ex);
            die();
        }
        return intval($address->id);
    }
}
