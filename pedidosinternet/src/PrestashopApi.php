<?php
declare(strict_types=1);

namespace PedidosInternet;

use PrestaShopLogger;
use PedidosInternet\Dto\DtoCliente;
use PedidosInternet\Dto\DtoClienteShippingAddress;
use PedidosInternet\Dto\DtoWebProduct;
use PedidosInternet\Helpers\StringHelper;
use PrestaShop\PrestaShop\Adapter\Entity\Customer;

class PrestashopApi
{
    /**
     * @param DtoCliente $cliente
     * @return int|string
     * @throws \PrestaShopException
     */
    public static function createOrUpdateClient(DtoCliente $cliente)
    {
        // Comprobar si existe el cliente
        $clientExist = DtoCliente::userById(intval($cliente->id));
                
        // Solo inserción de nuevos clientes
        if ($clientExist) {
            return;
        }
        
        $cliente->email = trim($cliente->email);
        if (mb_strpos($cliente->email, ';') !== false) {
            $emails = explode(";", $cliente->email);
            $email = $emails[0];
        } elseif (mb_strpos($cliente->email, ',') !== false) {
            $emails = explode(",", $cliente->email);
            $email = $emails[0];
        } elseif (mb_strpos($cliente->email, ' ') !== false) {
            $emails = explode(" ", $cliente->email);
            $email = $emails[0];
        } else {
            $email = $cliente->email;
        }

        if (empty($email)) {
            //return "Cliente sin email";
            $email = uniqid() . '@pedidosinternet.com';
        }

        $customer = new \Customer();
        $customer->email = $email;
        $customer->passwd = bin2hex(random_bytes(20));

        [$firstname, $lastname] = StringHelper::separateNames($cliente->tradeName);
        $customer->firstname = $firstname;
        $customer->lastname = $lastname;
        $customer->company = $cliente->businessName;

        try {
            $customer->save();

            DtoCliente::addId(intval($customer->id), intval($cliente->id));
            $customer_erp_id = DtoCliente::idByUser(intval($customer->id));
        } catch (\PrestaShopException $e) {
            return $e->getMessage();
        }

        foreach ($cliente->shippingAddresses as $index => $shippingAddress) {
            $addressId = $shippingAddress->toPrestashop($cliente, $customer);
            $cliente->shippingAddresses[$index]->shippingAddressId = $addressId;
        }

        $addressId = $cliente->invoiceAddress->toPrestashop($cliente, $customer);
        $cliente->invoiceAddress->shippingAddressId = $addressId;


        PrestaShopLogger::addLog("Cliente importado con éxito desde ERP: ERP ID: $customer_erp_id | Prestashop ID: $customer->id | Email: $customer->email | Nombre: $customer->firstname $customer->lastname", 1);

        return intval($customer->id);
    }
}
