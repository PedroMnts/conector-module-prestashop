<?php
declare(strict_types=1);

namespace PedidosInternet\Dto;

use PrestaShop\PrestaShop\Adapter\Entity\Customer;

class DtoCliente
{
    public string $cif;
    public string $email;
    public ?string $phone;
    public bool $confirmSaleNotes;
    public bool $IgnoreProductDivision;
    public string $registrationDate;  // is datetime
    public int $rateId;
    /** @var array<DtoClienteShippingAddress> $shippingAddresses */
    public array $shippingAddresses = [];

    public DtoClienteInvoiceAddress $invoiceAddress;
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

    public static function create(array $client) : ?DtoCliente
    {
        // El primer registro del API viene nulo
        if (is_null($client['cif'])) {
            return null;
        }   
        
        $toRet = new DtoCliente();
        $toRet->cif = $client['cif'];
        $toRet->email = $client['email'];
        $toRet->phone = $client['phone'];
        $toRet->confirmSaleNotes = $client['confirmSaleNotes'] ?? false;
        $toRet->IgnoreProductDivision = $client['IgnoreProductDivision'] ?? false;
        $toRet->registrationDate = $client['registrationDate'] ?? null;
        $toRet->rateId = $client['rateId'];

        foreach ($client['shippingAddresses'] as $value) {
            $toRet->shippingAddresses[] = DtoClienteShippingAddress::create($value);
        }
        $toRet->shippingAddresses = array_values(array_filter(
            $toRet->shippingAddresses,
            fn (?DtoClienteShippingAddress $shippingAddress) =>  !is_null($shippingAddress)
        ));

        $toRet->invoiceAddress = DtoClienteInvoiceAddress::create($client['invoiceAddress']);

        $toRet->roles[0] = $client['roles'][0] ?? 1;

        //$toRet->groupId = $client['groupId'] ?? 0; Este parámetro se ha comentado ya que da error si se pasa a 0 y no es muy relevante que se establezca un valor desde la web

        //$toRet->isARemovedClient = $client['isARemovedClient'] ?? false;
        $toRet->isARemovedClient = $client['isARemovedClient'];
        $toRet->id = $client['id']; //ID del ERP
        $toRet->tradeName = $client['tradeName'];
        $toRet->businessName = $client['businessName'];
        //$toRet->webId = intval($client['webId']) === 0 ? $client['id'] : $client['webId']; //ID del Prestashop: si es estrictamente a 0, establece la ID del ERP. Sino, deja el valor de webId como viene desde el ERP.
        $toRet->webId = $client['webId'];
        $toRet->clientTypeId = $client['clientTypeId'];
        $toRet->lastUpdateDate = $client['lastUpdateDate'];
        $toRet->salesmanId = $client['salesmanId'];
        $toRet->salesmanName = $client['salesmanName'];
        $toRet->salesmanSurname = $client['salesmanSurname'];
        $toRet->isDisabledForBuying = $client['isDisabledForBuying'];
         
        return $toRet;
    }

    public function toApiArray(bool $encodeAsJson = true, \Address $address = null): array
    {

        if(!is_null($address)) {
            $address = DtoClienteShippingAddress::fromPrestashop(get_object_vars($address));
        } else {
            $address = $this->shippingAddresses;
        }

        $business = ($this->businessName !== null && !empty($this->businessName)) ? $this->businessName : $this->tradeName;

        return [
            "CIF" => $this->cif,
            "Email" => $this->email,
            "Phone" => $this->phone,
            "ConfirmSaleNotes" => $this->confirmSaleNotes,
            "IgnoreProductDivision" => $this->IgnoreProductDivision,
            "registrationDate" => $this->registrationDate,
            "rateId" => $this->rateId,
            "ShippingAddresses" => array_map(
                fn($address) => DtoClienteShippingAddress::toApiArray($address),
                $address
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
    }

    /**
     * @param \Customer $customer
     * @return DtoCliente
     */
    public static function fromPrestashopCustomer(\Customer $customer, $id_address_delivery = NULL, $id_address_invoice = NULL): DtoCliente
    {
        $toRet = new DtoCliente();
        $random_cif = uniqid();

        if($id_address_delivery == NULL && $id_address_invoice != NULL ) {
            $invoiceAddresses = $customer->getSimpleAddress($id_address_invoice);
            $shippingAddresses = $invoiceAddresses;
        }
        elseif($id_address_delivery != NULL && $id_address_invoice == NULL) {
            $shippingAddresses = $customer->getSimpleAddress($id_address_delivery);
            $invoiceAddresses = $shippingAddresses;
        }
        elseif($id_address_invoice != NULL && $id_address_invoice != NULL) {
            $shippingAddresses = $customer->getSimpleAddress($id_address_delivery);
            $invoiceAddresses = $customer->getSimpleAddress($id_address_invoice);
        } else {

            $addresses = $customer->getAddresses((int) \Configuration::get('PS_LANG_DEFAULT')); 

            if (isset($addresses[0])) {
                $shippingAddresses = $customer->getSimpleAddress($addresses[0]['id_address']);
                $invoiceAddresses = $shippingAddresses; // O puedes asignar otra dirección si lo deseas
            } else {
                $shippingAddresses = null;
                $invoiceAddresses = null;
            }
        }
        
        if (!empty($shippingAddresses)) {
            if (isset($shippingAddresses[0]) && is_array($shippingAddresses[0])) {
                $shippingAddress = $shippingAddresses[0];
            } else {
                $shippingAddress = $shippingAddresses;
            }
            
            if (isset($shippingAddress['dni']) && isset($shippingAddress['phone'])) {
                $cif = $shippingAddress['dni'];
                $phone = $shippingAddress['phone'];
                $toRet->shippingAddresses = DtoClienteShippingAddress::fromPrestashop($shippingAddress);
            } 
        }
        if (!empty($invoiceAddresses)) {
            if (isset($invoiceAddresses[0]) && is_array($invoiceAddresses[0])) {
                $invoiceAddress = $invoiceAddresses[0];
            } else {
                $invoiceAddress = $invoiceAddresses;
            }
            
            $toRet->invoiceAddress = DtoClienteInvoiceAddress::fromPrestashop($invoiceAddress);
        }
        
        if($toRet::idByUser(intval($customer->id))) {
            $api_id = intval($toRet::idByUser(intval($customer->id)));
        } else {
            $api_id = 0;
        }

        $completeName = $customer->firstname . " " . $customer->lastname;
        $customerCompany = $customer->company;

        $toRet->cif = $cif ?? $random_cif; // Si no tiene CIF establecemos el una ID aleatoria
        $toRet->email = $customer->email;
        $toRet->phone = $phone ?? "0"; 
        $toRet->confirmSaleNotes = false;
        $toRet->IgnoreProductDivision = false;
        $toRet->registrationDate = date("Y-m-d\TH:i:s.000\Z", strtotime($customer->date_add));
        $toRet->rateId = 1;
        $toRet->roles[0] = !empty($toRet->businessName) ? 1 : 2; // Si el campo businessName está vacío, se establece en 1, sino en 2.
        $toRet->groupId = 0;
        $toRet->id = $api_id;
        $toRet->isARemovedClient = false;
        $toRet->tradeName = $completeName;
        $toRet->businessName = $customerCompany ?? $completeName;
        $toRet->webId = intval($customer->id);
        $toRet->lastUpdateDate = date("Y-m-d\TH:i:s.000\Z", strtotime($customer->date_upd));
        
        return $toRet;
    }

    /**
     * @param int $apiId
     * @return false|int
     */
    public static function userById(int $apiId)
    {
        return \Db::getInstance()->getValue("SELECT id_customer FROM " . pSQL(_DB_PREFIX_) . 'customer WHERE api_id=' . $apiId);
    }

    public static function idByUser(int $customerId)
    {
        return \Db::getInstance()->getValue("SELECT api_id FROM " . pSQL(_DB_PREFIX_) . 'customer WHERE id_customer=' . $customerId);
    }

    public static function addId(int $customerId, int $apiId)
    {
        \Db::getInstance()->execute("UPDATE " . pSQL(_DB_PREFIX_) . 'customer SET api_id=' . $apiId . ' WHERE id_customer=' . $customerId);
    }

    public static function remove_accents($str){
    
        $to_replace = array(
            array(
                array('Á', 'À', 'Â', 'Ä', 'á', 'à', 'ä', 'â'),
                array('A', 'A', 'A', 'A', 'a', 'a', 'a', 'a')
            ),
            array(
                array('É', 'È', 'Ê', 'Ë', 'é', 'è', 'ë', 'ê'),
                array('E', 'E', 'E', 'E', 'e', 'e', 'e', 'e')
            ),
            array(
                array('Í', 'Ì', 'Ï', 'Î', 'í', 'ì', 'ï', 'î'),
                array('I', 'I', 'I', 'I', 'i', 'i', 'i', 'i'),
            ),
            array(
                array('Ó', 'Ò', 'Ö', 'Ô', 'ó', 'ò', 'ö', 'ô'),
                array('O', 'O', 'O', 'O', 'o', 'o', 'o', 'o'),
            ),
            array(
                array('Ú', 'Ù', 'Û', 'Ü', 'ú', 'ù', 'ü', 'û'),
                array('U', 'U', 'U', 'U', 'u', 'u', 'u', 'u'),
            ),
        );
        
        foreach($to_replace as $from_to){
            $str = str_replace($from_to[0], $from_to[1], $str);
        }
        
        return $str;
    
    }
}
