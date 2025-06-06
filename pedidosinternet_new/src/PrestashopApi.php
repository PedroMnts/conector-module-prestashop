<?php
declare(strict_types=1);

namespace PedidosInternet;

use PedidosInternet\Dto\DtoCliente;
use PedidosInternet\Log;
use Customer;
use Context;
use Validate;
use Configuration;
use Db;

/**
 * Clase para interactuar con la API de PrestaShop
 *
 * Proporciona métodos para crear y actualizar entidades en PrestaShop
 * basadas en datos recibidos del ERP
 */
class PrestashopApi
{
    /**
     * Crea o actualiza un cliente en PrestaShop
     *
     * @param DtoCliente $client Datos del cliente desde el ERP
     * @return int|string ID del cliente en PrestaShop si se creó/actualizó correctamente, o mensaje de error
     */
    public static function createOrUpdateClient(DtoCliente $client)
    {
        try {
            // Validar datos esenciales
            if (empty($client->email) || !\Validate::isEmail($client->email)) {
                Log::warning("Email de cliente inválido", [
                    'client_id' => $client->id,
                    'email' => $client->email
                ]);
                return "Email inválido";
            }

            // Buscar si el cliente ya existe
            $prestashopId = DtoCliente::userById($client->id);
            
            if ($prestashopId) {
                // Actualizar cliente existente
                return self::updateExistingClient((int)$prestashopId, $client);
            } else {
                // Crear nuevo cliente
                return self::createNewClient($client);
            }
        } catch (\Exception $e) {
            Log::error("Error al crear/actualizar cliente", [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return "Error: " . $e->getMessage();
        }
    }
    
    /**
     * Actualiza un cliente existente en PrestaShop
     *
     * @param int $prestashopId ID del cliente en PrestaShop
     * @param DtoCliente $client Datos del cliente desde el ERP
     * @return int|string ID del cliente en PrestaShop si se actualizó correctamente, o mensaje de error
     */
    private static function updateExistingClient(int $prestashopId, DtoCliente $client)
    {
        try {
            $customer = new Customer($prestashopId);
            
            if (!\Validate::isLoadedObject($customer)) {
                Log::warning("Cliente no encontrado en PrestaShop", [
                    'prestashop_id' => $prestashopId,
                    'client_id' => $client->id
                ]);
                return "Cliente no encontrado";
            }
            
            // Actualizar datos del cliente
            $customer->email = $client->email;
            $customer->firstname = self::getFirstName($client->tradeName);
            $customer->lastname = self::getLastName($client->tradeName);
            $customer->company = $client->businessName;
            
            // Datos adicionales que podrían necesitar actualizarse
            $customer->passwd = !empty($customer->passwd) ? $customer->passwd : self::generateRandomPassword();
            
            // Guardar cliente
            if (!$customer->update()) {

                return "Error al actualizar cliente";
            }
            
            // Actualizar direcciones si es necesario
            self::updateClientAddresses($customer, $client);

            
            return $prestashopId;
        } catch (\Exception $e) {
            Log::error("Error al actualizar cliente existente", [
                'prestashop_id' => $prestashopId,
                'client_id' => $client->id,
                'error' => $e->getMessage()
            ]);
            return "Error al actualizar: " . $e->getMessage();
        }
    }
    
    /**
     * Crea un nuevo cliente en PrestaShop
     *
     * @param DtoCliente $client Datos del cliente desde el ERP
     * @return int|string ID del cliente en PrestaShop si se creó correctamente, o mensaje de error
     */
    private static function createNewClient(DtoCliente $client)
    {
        try {
            // Verificar si el email ya está en uso
            $existingClientId = Customer::customerExists($client->email, true, false);
            if ($existingClientId) {
                Log::warning("Email ya registrado en otro cliente", [
                    'email' => $client->email,
                    'existing_id' => $existingClientId,
                    'client_id' => $client->id
                ]);
                
                // Asociar el cliente existente con el ID del ERP
                DtoCliente::addId($existingClientId, $client->id);
                
                return $existingClientId;
            }
            
            // Crear nuevo cliente
            $customer = new Customer();
            $customer->email = $client->email;
            $customer->firstname = self::getFirstName($client->tradeName);
            $customer->lastname = self::getLastName($client->tradeName);
            $customer->company = $client->businessName;
            
            // Datos adicionales necesarios
            $customer->passwd = self::generateRandomPassword();
            $customer->active = 1;
            $customer->id_shop = Context::getContext()->shop->id;
            $customer->id_shop_group = Context::getContext()->shop->id_shop_group;
            $customer->id_default_group = (int)Configuration::get('PS_CUSTOMER_GROUP');
            $customer->id_lang = (int)Configuration::get('PS_LANG_DEFAULT');
            $customer->date_add = date('Y-m-d H:i:s');
            $customer->date_upd = date('Y-m-d H:i:s');
            
            // Guardar cliente
            if (!$customer->add()) {
                Log::error("Error al crear cliente en PrestaShop", [
                    'email' => $client->email,
                    'client_id' => $client->id
                ]);
                return "Error al crear cliente";
            }
            
            // Asociar cliente con ID del ERP
            DtoCliente::addId((int)$customer->id, $client->id);
            
            // Crear direcciones para el cliente
            self::createClientAddresses($customer, $client);
            
            return (int)$customer->id;
        } catch (\Exception $e) {
            Log::error("Error al crear nuevo cliente", [
                'client_id' => $client->id,
                'error' => $e->getMessage()
            ]);
            return "Error al crear: " . $e->getMessage();
        }
    }
    
    /**
     * Actualiza las direcciones de un cliente
     *
     * @param Customer $customer Cliente de PrestaShop
     * @param DtoCliente $client Datos del cliente desde el ERP
     * @return void
     */
    private static function updateClientAddresses(Customer $customer, DtoCliente $client): void
    {
        try {
            // Implementación de actualización de direcciones
            // Esto dependerá de la estructura específica de tus datos
            
            // Actualizar dirección de facturación
            if (isset($client->invoiceAddress) && !empty($client->invoiceAddress->address)) {
                // Lógica para actualizar dirección de facturación
                $client->invoiceAddress->toPrestashop($client, $customer);
                
            }
            
            // Actualizar direcciones de envío
            if (!empty($client->shippingAddresses)) {
                foreach ($client->shippingAddresses as $shippingAddress) {
                    // Lógica para actualizar direcciones de envío
                    $shippingAddress->toPrestashop($client, $customer);
                }
            }
        } catch (\Exception $e) {
            Log::warning("Error al actualizar direcciones", [
                'customer_id' => $customer->id,
                'client_id' => $client->id,
                'error' => $e->getMessage()
            ]);
            // No lanzamos la excepción para que no interrumpa el flujo principal
        }
    }
    
    /**
     * Crea las direcciones para un cliente nuevo
     *
     * @param Customer $customer Cliente de PrestaShop
     * @param DtoCliente $client Datos del cliente desde el ERP
     * @return void
     */
    private static function createClientAddresses(Customer $customer, DtoCliente $client): void
    {
        try {
            // Crear dirección de facturación
            if (isset($client->invoiceAddress) && !empty($client->invoiceAddress->address)) {
                // Lógica para crear dirección de facturación
                $client->invoiceAddress->toPrestashop($client, $customer);
                
            }
            
            // Crear direcciones de envío
            if (!empty($client->shippingAddresses)) {
                foreach ($client->shippingAddresses as $shippingAddress) {
                    // Lógica para crear direcciones de envío
                    $shippingAddress->toPrestashop($client, $customer);
                    
                }
            }
        } catch (\Exception $e) {
            Log::warning("Error al crear direcciones", [
                'customer_id' => $customer->id,
                'client_id' => $client->id,
                'error' => $e->getMessage()
            ]);
            // No lanzamos la excepción para que no interrumpa el flujo principal
        }
    }
    
    /**
     * Genera una contraseña aleatoria para nuevos clientes
     *
     * @return string Contraseña generada
     */
    private static function generateRandomPassword(): string
    {
        return bin2hex(random_bytes(8));
    }
    
    /**
     * Extrae el nombre del campo tradeName
     *
     * @param string $tradeName Nombre comercial
     * @return string Nombre extraído
     */
    private static function getFirstName(string $tradeName): string
    {
        $parts = explode(' ', trim($tradeName), 2);
        return !empty($parts[0]) ? $parts[0] : 'Cliente';
    }
    
    /**
     * Extrae el apellido del campo tradeName
     *
     * @param string $tradeName Nombre comercial
     * @return string Apellido extraído
     */
    private static function getLastName(string $tradeName): string
    {
        $parts = explode(' ', trim($tradeName), 2);
        return !empty($parts[1]) ? $parts[1] : 'ERP';
    }
}