<?php
declare(strict_types=1);

namespace PedidosInternet\Dto;

use PedidosInternet\Helpers\StringHelper;

class DtoClienteShippingAddress
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
    public string $phoneNumber1;
    public string $phoneNumber2;
    public string $shippingObservations;

    /**
     * @param array $shippingAdress
     * @return DtoClienteShippingAddress
     */
    public static function create(array $shippingAdress) : ?DtoClienteShippingAddress
    {
        if (empty($shippingAdress['townName'])) {
            return null;
        }
        $toRet = new DtoClienteShippingAddress();

        $toRet->idCountry = intval($shippingAdress['idCountry']);
        $toRet->idCommunity = intval($shippingAdress['idCommunity']);
        $toRet->idProvince = intval($shippingAdress['idProvince']);
        $toRet->postalCode = $shippingAdress['postalCode'];
        $toRet->contactName = $shippingAdress['contactName'] ?? "";
        $toRet->countryName = $shippingAdress['countryName'];
        $toRet->communityName = $shippingAdress['communityName'] ?? "";
        $toRet->provinceName = $shippingAdress['provinceName'] ?? "";
        $toRet->townName = $shippingAdress['townName'];
        $toRet->address = $shippingAdress['address'];
        $toRet->shippingAddressId =
            empty($shippingAdress['shippingAddressId']) ?
                null :
                $shippingAdress['shippingAddressId']
        ;
        $toRet->phoneNumber1 = $shippingAdress['phoneNumber1'];
        $toRet->phoneNumber2 = $shippingAdress['phoneNumber2'];
        $toRet->shippingObservations = $shippingAdress['shippingObservations'] ?? "";

        return $toRet;
    }

    /**
     * @param DtoClienteShippingAddress $shippingAddress
     * @return array
     */
    public static function toApiArray(DtoClienteShippingAddress $shippingAddress): array
    {

        return [
            "idCountry" => $shippingAddress->idCountry ?? 66,
            "idCommunity" => $shippingAddress->idCommunity ?? 0,
            "idProvince" => $shippingAddress->idProvince ?? 0,
            "postalCode" => $shippingAddress->postalCode ?? "",
            "contactName" => $shippingAddress->contactName ?? "",
            "countryName" => $shippingAddress->countryName ?? "",
            "communityName" => $shippingAddress->communityName ?? "",
            "provinceName" => $shippingAddress->provinceName ?? "",
            "townName" => $shippingAddress->townName ?? "",
            "address" => $shippingAddress->address ?? "",
            "shippingAddressId" => $shippingAddress->shippingAddressId ?? 0,
            "phoneNumber1" => $shippingAddress->phoneNumber1 ?? "",
            "phoneNumber2" => $shippingAddress->phoneNumber2 ?? "",
            "shippingObservations" => $shippingAddress->shippingObservations ?? "",
        ];
    }

    /**
     * @param \Customer $customer
     * @return array|DtoClienteShippingAddress
     */
    public static function fromPrestashop(array $address) : array
    {
        $toRet = new DtoClienteShippingAddress();

        $state_name = \State::getNamebyID($address['id_state']);
        if($state_name) {
            $toRet->provinceName = $state_name;
        } else {
            $toRet->provinceName = "";
        }

        $toRet->idCountry = intval($address['id_country']);
        $toRet->idCommunity = 0; // No sale
        $toRet->idProvince = intval($address['id_state']);
        $toRet->postalCode = $address['postcode'];
        $toRet->contactName = $address['firstname'] . ' ' . $address['lastname'];
        $toRet->countryName = $address['country'];
        $toRet->communityName = "";
        $toRet->townName = $address['city'];
        $toRet->address = $address['address1'] . ' ' . $address['address2'];
        $toRet->shippingAddressId = 0;
        $toRet->phoneNumber1 = $address['phone'];
        $toRet->phoneNumber2 = $address['phone_mobile'];
        $toRet->shippingObservations = "";

        return [$toRet];
    }

    /**
     * Guarda esta dirección en un objeto Address de Prestashop indicando como alias 'Envío'
     *
     * @param DtoCliente $dtoCliente
     * @param \Customer $customer
     * @return int Id de la dirección creada/actualizada
     */
    public function toPrestashop(DtoCliente $dtoCliente, \Customer $customer): int
    {
        $address = new \Address($this->shippingAddressId, 1);

        $address->id_country = (empty($this->idCountry) || ($this->idCountry === 66)) ? 6 : $this->idCountry;
        $address->address1 = (strlen($this->address) < 128) ? $this->address : substr($this->address, 0, 127);
        $address->postcode = $this->postalCode;
        $address->city = $this->townName;
        $address->phone = is_numeric($dtoCliente->phone) ? $dtoCliente->phone : '';
        $address->dni = $dtoCliente->cif;
        $address->company = $dtoCliente->businessName;

        [$firstname, $lastname] = StringHelper::separateNames($this->contactName);
        if ($firstname === null) {
            return -1;
        }
        $address->firstname = $firstname;
        $address->lastname = $lastname;
        $address->id_customer = (int) $customer->id;
        $address->id_state = 0;
        $address->alias = 'Envío';

        $address->save();
        return intval($address->id);
    }
}
