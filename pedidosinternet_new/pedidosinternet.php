<?php
/**
* 2007-2022 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2022 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use PedidosInternet\Dto\DtoCliente;
use PedidosInternet\Dto\DtoClienteShippingAddress;
use PedidosInternet\Controller\StateController;
use PedidosInternet\PedidosApi;
use PedidosInternet\Log;

class PedidosInternet extends Module
{
    
    public $tabs = [
        [
            'class_name' => 'pedidosinternet',
            'route_name' => 'pedidosInternet',
            'name' => 'Conector con Distrib', 
            'parent_class_name' => 'CONFIGURE',
            'visible' => true

        ],
    ];

    public function __construct()
    {
        $this->name = 'pedidosinternet';
        $this->tab = 'administration';
        $this->version = '2.0.0';
        $this->author = 'Hexer';
        $this->need_instance = 1;

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Conector con Distrib');
        $this->description = $this->l('Módulo para conectar PrestaShop con Distrib. Gestiona clientes, pedidos y productos.');

        $this->confirmUninstall = $this->l('');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);


    }

    public function install() {
        try {
            if (!parent::install()) {
                return false;
            }
    
            $hooks = [
                'header',
                'displayBackOfficeHeader',
                'actionAdminControllerSetMedia',
                'actionCustomerAccountAdd',
                'actionOrderStatusUpdate',
                'PaymentConfirmation',
                'displayCustomerAccount',
                'actionObjectCustomerAddAfter',
                'additionalCustomerFormFields',
                'actionCustomerAccountUpdate',
                'displayAdminOrderSide',
                'actionObjectAddressAddAfter',
                'actionObjectAddressUpdateAfter',
                'actionObjectAddressDeleteAfter'
            ];
    
            foreach ($hooks as $hook) {
                if (!$this->registerHook($hook)) {
                    Log::error("Error durante la instalación de hook: {$hook}");
                    return false;
                }
            }
    
            include(dirname(__FILE__).'/sql/install.php');
            return true;
    
        } catch (\Exception $e) {
            Log::error('Error crítico durante la instalación', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function uninstall()
    {
        Configuration::deleteByName('PEDIDOSINTERNET_LIVE_MODE');

        include(dirname(__FILE__).'/sql/uninstall.php');

        return parent::uninstall();
    }

    private function installTab()
    {
        $tabId = (int) Tab::getIdFromClassName('PedidosInternetController');
        if (!$tabId) {
            $tabId = null;
        }

        $tab = new Tab($tabId);
        $tab->active = 1;
        $tab->class_name = 'PedidosInternetController';
        $tab->route_name = 'pedidosInternet';
        $tab->name = 'Conector con Distrib';

        $tab->id_parent = (int) Tab::getIdFromClassName('CONFIGURE');
        $tab->module = $this->name;

        return $tab->save();
    }

    private function uninstallTab()
    {
        $tabId = (int) Tab::getIdFromClassName('PedidosInternetController');
        if (!$tabId) {
            return true;
        }

        $tab = new Tab($tabId);

        return $tab->delete();
    }

    /**
    * Hook que se ejecuta después de añadir una cuenta de cliente
    *
    * Este hook crea el cliente en el sistema ERP externo después de que un 
    * cliente se registra en la tienda. Registra la asociación entre el ID 
    * de cliente de Prestashop y el ID asignado en el sistema externo.
    *
    * @param array{newCustomer: Customer} $params Parámetros del hook con el nuevo cliente
    * @return bool True si el cliente se creó correctamente en el sistema externo, false en caso contrario
    */
    public function hookActionCustomerAccountAdd(array $params) {
        try {
            $customer = $params['newCustomer'];
            if (!($customer instanceof Customer)) {
                Log::error("Error: El parámetro no es una instancia de Customer | hookActionCustomerAccountAdd", [
                    'params' => $params
                ]);
                return false;
            }
    
            $dtoCliente = DtoCliente::fromPrestashopCustomer($customer);
            $pedidosApi = PedidosApi::create();
            
            if (!$pedidosApi) {
                Log::error("Error: El parámetro no es una instancia de Customer | hookActionCustomerAccountAdd", [
                    'pedidosApi' => $pedidosApi
                ]);
                return false;
            }
    
            $response_curl = $pedidosApi->createClient($dtoCliente);
    
            if (isset($response_curl['id']) && is_int($response_curl['id'])) {
                $dtoCliente->addId($customer->id, $response_curl['id']);
                Log::info("Cliente generado con id: " . $customer->id . " y api_id: " . $response_curl['id'], [
                    'customer_id' => $customer->id,
                    'api_id' => $response_curl['id']
                ]);
                return true;
            }
    
            Log::info("Cliente creado en el ERP | hookActionCustomerAccountAdd", [
                'customer_id' => $customer->id
            ]);
            return false;
    
        } catch (\Exception $e) {
            Log::error("Error: El parámetro no es una instancia de Customer | hookActionCustomerAccountAdd", [
                'params' => $params
            ]);
            return false;
        }
    }

    /**
     * Hook que se ejecuta cuando cambia el estado de un pedido
     *
     * Este hook detecta cuando un pedido cambia a los estados "Pago aceptado" o 
     * "Pago remoto aceptado", y entonces crea el pedido en el sistema externo de pedidos
     * y actualiza la dirección del cliente asociada al pedido.
     *
     * @param array{
     *  newOrderStatus: \OrderState,
     *  id_order: int
     * } $params Parámetros del hook con el nuevo estado y el id del pedido
     * @return void
     */
    public function hookActionOrderStatusUpdate(array $params)
    {
        $newStatus = $params['newOrderStatus'];

        $order = new Order((int) $params['id_order']);

        if ($params['newOrderStatus']->name === "Pago aceptado" || $params['newOrderStatus']->name === 'Pago remoto aceptado') {
            $orderNote = \PedidosInternet\Dto\DtoOrderNote::createFromPrestashopOrder($order);

            $pedidosApi = PedidosApi::create();
            
            try {
                $pedidosApi->createOrder($orderNote);

                $customer = new Customer((int)$order->id_customer);
                $pedidosApi->updateAddressOrderOnClient($order, $customer);

                Log::info("Pedido creado correctamente y Dirección de cliente actualizada | hookActionOrderStatusUpdate", [
                    'customer_id' => $customer->id,
                    'order_id' => $order->id,
                    'status' => $newStatus->name
                ]);

            } catch (\Exception $e) {
                Log::error("Error al crear orden o actualizar dirección | hookActionOrderStatusUpdate", [
                    'error' => $e->getMessage(),
                    'order_id' => $order->id,
                    'customer_id' => $order->id_customer
                ]);
            }
        }
    }

    /**
    * Hook que se ejecuta después de la confirmación de pago de un pedido
    *
    * Este hook crea el pedido en el sistema externo de pedidos y actualiza
    * la dirección del cliente asociada al pedido. Se ejecuta una vez que
    * el pago ha sido confirmado.
    *
    * @param array $params Parámetros del hook con el id_order confirmado
    * @return void
    */
    public function hookPaymentConfirmation(array $params)
    {
        try {
            $order_id = (int) $params['id_order'];
            $order = new Order($order_id);
    
            $orderNote = \PedidosInternet\Dto\DtoOrderNote::createFromPrestashopOrder($order);
            $pedidosApi = PedidosApi::create();
            
            $pedidosApi->createOrder($orderNote);
    
            $customer = new Customer((int)$order->id_customer);
            $pedidosApi->updateAddressOrderOnClient($order, $customer);

            Log::info("Pedido creado y Dirección de cliente actualizada | hookPaymentConfirmation", [
                'customer_id' => $customer->id,
                'order_id' => $order_id,
                'params' => $params
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error en confirmación de pago | hookPaymentConfirmation", [
                'error' => $e->getMessage(),
                'order_id' => $order_id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
        }
        
    }
             
    /**
    * Hook que se ejecuta después de añadir un nuevo cliente
    *
    * Este hook crea una dirección asociada al cliente recién registrado utilizando
    * los datos adicionales que se recopilaron durante el proceso de registro.
    * Además, guarda el campo empresa en el objeto cliente.
    * 
    * @param array $params Parámetros del hook con el objeto customer creado
    * @return void
    */
    public function hookActionObjectCustomerAddAfter(array $params)
    {

        $company = Tools::getValue('company');
        $customer = new Customer((int)$params['object']->id);
        $customer->company = $company;
        $customer->update();

        $address = new Address();
        $address->id_country = Tools::getValue('id_country');
        $address->id_state = (int)Tools::getValue('id_state');
        $address->id_customer = (int)$customer->id;
        $address->alias = Tools::getValue('alias');
        $address->company = $company;
        $address->lastname = $customer->lastname;
        $address->firstname = $customer->firstname;
        $address->address1 = Tools::getValue('adress1');
        $address->address2 = Tools::getValue('adress2');
        $address->postcode = Tools::getValue('postcode');
        $address->city = Tools::getValue('city');
        $address->phone = Tools::getValue('phone');
        $address->dni = Tools::getValue('dni');

        $address->add();

    }  

    /**
    * Hook para añadir campos adicionales al formulario de cliente
    *
    * Este hook agrega campos de dirección adicionales cuando el usuario está en la página
    * de registro/autenticación. Los campos incluyen información de dirección como alias,
    * empresa, NIF/DNI, teléfono, dirección, código postal, localidad, provincia y país.
    *
    * @param array $params Parámetros del hook
    * @return array|null Devuelve un array con los campos de formulario adicionales si
    *                    estamos en la página de autenticación, o null en caso contrario
    */
    public function hookAdditionalCustomerFormFields($params)
    {
        if($this->context->controller->php_self == 'authentication')
        {
            $langDefault = (int)\Configuration::get('PS_LANG_DEFAULT');
            $countries = Country::getCountries($langDefault, true, false, false);
            $states = State::getStates($langDefault, true);
    
            if (count($countries) > 0) {
                $countryField = (new FormField)
                    ->setName('id_country')
                    ->setType('countrySelect')
                    ->setLabel($this->l('País'))
                    ->setRequired(true);
                foreach ($countries as $country) {
                    $countryField->addAvailableValue(
                        $country['id_country'],
                        $country['country']
                    );
                }
            }

            if(count($states) > 0) {
                $stateField = (new FormField())
                    ->setName('id_state')
                    ->setType('select')
                    ->setLabel($this->l('Provincia'))
                    ->setRequired(true);
                foreach ($states as $state) {
                    $stateField->addAvailableValue(
                        $state['id_state'],
                        $state['name']
                    );
                }
            }
    
            return [
                (new FormField)
                ->setName('alias')
                ->setType('text')
                ->setRequired(true)
                ->setLabel($this->l('Alias dirección')),
                (new FormField)
                ->setName('company')
                ->setType('text')
                ->setLabel($this->l('Empresa')),
                (new FormField)
                ->setName('dni')
                ->setType('text')
                ->setRequired(true)
                ->setLabel($this->l('NIF / DNI')),
                (new FormField)
                ->setName('phone')
                ->setType('text')
                ->setRequired(true)
                ->setLabel($this->l('Teléfono')),
                (new FormField)
                ->setName('adress1')
                ->setType('text')
                ->setRequired(true)
                ->setLabel($this->l('Dirección')),
                (new FormField)
                ->setName('adress2')
                ->setType('text')
                ->setRequired(false)
                ->setLabel($this->l('Dirección Complementaria')),
                (new FormField)
                ->setName('postcode')
                ->setType('text')
                ->setRequired(true)
                ->setLabel($this->l('Código postal/Zip')),
                (new FormField)
                ->setName('city')
                ->setType('text')
                ->setRequired(false)
                ->setLabel($this->l('Localidad')),
                $stateField->getName() => $stateField,
                $countryField->getName() => $countryField,
            ];
        }
        
    }

    /**
     * Hook que se ejecuta después de que una cuenta de cliente sea actualizada
     *
     * Este hook actualiza la información del cliente en el sistema de pedidos externo
     * cuando los datos de una cuenta son modificados en Prestashop
     *
     * @param array $params Parámetros del hook con el objeto customer modificado
     * @return bool|void Retorna false en caso de error, o void si la operación es exitosa
     */
    public function hookActionCustomerAccountUpdate(array $params)
    {
        $customer = $params['customer'];

        if ($customer instanceof Customer) {
            $dtoCliente = DtoCliente::fromPrestashopCustomer($customer);
            $pedidosApi = PedidosApi::create();
            $updated = $pedidosApi->updateClient($dtoCliente);

            if(!$updated) {
                Log::error("No se ha actualizado correctamente el cliente | hookActionCustomerAccountUpdate", [
                    'customer_id' => $customer->id
                ]);
            }
            
        } else {
            Log::error("Error al actualizar el cliente. No hay datos de cliente | hookActionCustomerAccountUpdate", [
                'params' => $params
            ]);
            return false;
        }
    }

    /**
     * Hook que se ejecuta después de añadir una nueva dirección
     * Envía la nueva dirección al ERP para mantener los datos sincronizados
     * 
     * @param array $params Parámetros del hook, incluyendo el objeto dirección
     * @return bool Resultado de la operación
     */
    public function hookActionObjectAddressAddAfter(array $params)
    {
        try {
            // Verificar que tenemos un objeto dirección válido
            if (!isset($params['object']) || !($params['object'] instanceof Address)) {
                Log::warning("No se recibió un objeto dirección válido al crear dirección", [
                    'params' => array_keys($params)
                ]);
                return false;
            }
            
            $address = $params['object'];
            
            // Obtener el cliente asociado a la dirección
            $customer = new Customer((int)$address->id_customer);
            
            if (!Validate::isLoadedObject($customer)) {
                Log::error("No se encontró un cliente válido al crear dirección", [
                    'address_id' => $address->id,
                    'customer_id' => $address->id_customer
                ]);
                return false;
            }
            
            // Crear DTO con todas las direcciones del cliente, incluyendo la nueva
            $dtoCliente = DtoCliente::fromPrestashopCustomer($customer);
            
            // Inicializar API y enviar actualización
            $pedidosApi = PedidosApi::create();
            
            if (!$pedidosApi) {
                Log::error("No se pudo inicializar la API al crear dirección", [
                    'customer_id' => $customer->id,
                    'address_id' => $address->id
                ]);
                return false;
            }
            
            // El método fromPrestashopCustomer ya obtiene todas las direcciones, 
            // incluyendo la recién creada, así que solo actualizamos el cliente completo
            $updated = $pedidosApi->updateClient($dtoCliente);
            
            if (!$updated) {
                Log::error("No se ha sincronizado correctamente la nueva dirección con el ERP", [
                    'customer_id' => $customer->id,
                    'address_id' => $address->id
                ]);
                return false;
            }
            
            Log::info("Nueva dirección sincronizada correctamente con el ERP", [
                'customer_id' => $customer->id,
                'address_id' => $address->id
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error("Error al sincronizar nueva dirección con el ERP", [
                'address_id' => isset($address) ? $address->id : 'desconocido',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Hook que se ejecuta después de que una dirección sea actualizada
     * 
     * Este hook actualiza la información de dirección del cliente en el sistema de pedidos externo
     * cuando una dirección es modificada en Prestashop
     * 
     * @param array $params Parámetros del hook con el objeto address modificado
     * @return bool|void Retorna false en caso de error, o void si la operación es exitosa
     */
    public function hookActionObjectAddressUpdateAfter(array $params)
    {
        $address = $params['object'];

        if ($address instanceof Address) {
            $customer = new Customer((int)$address->id_customer);

            $dtoCliente = DtoCliente::fromPrestashopCustomer($customer);
            $pedidosApi = PedidosApi::create();
            $updated = $pedidosApi->updateAddress($dtoCliente, $address);

            if(!$updated) {
                Log::error("No se ha actualizado correctamente la dirección del cliente | hookActionObjectAddressUpdateAfter", [
                    'customer_id' => $customer->id,
                    'address_id' => $address->id
                ]);
            }
            
        } else {
            Log::error("Error al actualizar la dirección del cliente. No hay datos de dirección | hookActionObjectAddressUpdateAfter", [
                'params' => $params
            ]);
            return false;
        }
    }

    /**
     * Hook que se ejecuta después de eliminar una dirección
     * Sincroniza la eliminación con el ERP manteniendo los requisitos mínimos
     * 
     * @param array $params Parámetros del hook, incluyendo el objeto dirección eliminado
     * @return bool Resultado de la operación
     */
    public function hookActionObjectAddressDeleteAfter(array $params)
    {
        try {
            // Verificar que tenemos los datos necesarios
            if (!isset($params['object']) || !($params['object'] instanceof Address)) {
                Log::warning("No se recibió un objeto dirección válido al eliminar dirección", [
                    'params' => array_keys($params)
                ]);
                return false;
            }
            
            $address = $params['object'];
            $customerId = (int)$address->id_customer;
            
            // Obtener el cliente asociado a la dirección
            $customer = new Customer($customerId);
            
            if (!Validate::isLoadedObject($customer)) {
                Log::error("No se encontró un cliente válido al eliminar dirección", [
                    'address_id' => $address->id,
                    'customer_id' => $customerId
                ]);
                return false;
            }
            
            // Verificar cuántas direcciones le quedan al cliente en PrestaShop
            $remainingAddresses = $customer->getAddresses((int)Configuration::get('PS_LANG_DEFAULT'));
            
            // Inicializar API
            $pedidosApi = PedidosApi::create();
            
            if (!$pedidosApi) {
                Log::error("No se pudo inicializar la API al eliminar dirección", [
                    'customer_id' => $customerId,
                    'address_id' => $address->id
                ]);
                return false;
            }
            
            // Crear DTO con las direcciones restantes del cliente
            $dtoCliente = DtoCliente::fromPrestashopCustomer($customer);
            
            // Si el cliente no existe en el ERP, no hay nada que hacer
            if (empty($dtoCliente->id)) {
                Log::warning("Cliente no encontrado en el ERP al eliminar dirección", [
                    'customer_id' => $customerId,
                    'prestashop_addresses' => count($remainingAddresses)
                ]);
                return false;
            }
            
            // Actualizar cliente en el ERP con las direcciones restantes
            // El método fromPrestashopCustomer ya habrá mantenido al menos una dirección
            // para shippingAddresses y habrá asegurado que invoiceAddress sea válida
            $updated = $pedidosApi->updateClient($dtoCliente);
            
            if (!$updated) {
                Log::error("No se ha sincronizado correctamente la eliminación de dirección con el ERP", [
                    'customer_id' => $customerId,
                    'address_id' => $address->id,
                    'remaining_addresses' => count($remainingAddresses)
                ]);
                return false;
            }
            
            Log::info("Eliminación de dirección sincronizada correctamente con el ERP", [
                'customer_id' => $customerId,
                'address_id' => $address->id,
                'remaining_addresses' => count($remainingAddresses)
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error("Error al sincronizar eliminación de dirección con el ERP", [
                'address_id' => isset($address) ? $address->id : 'desconocido',
                'customer_id' => isset($customerId) ? $customerId : 'desconocido',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Ejecuta las tareas programadas de sincronización con el ERP
     * 
     * Esta función se encarga de ejecutar todas las tareas de sincronización
     * definidas en un array de funciones. Cada tarea se ejecuta de forma
     * independiente, y si una falla, se registra el error y se continúa
     * con la siguiente tarea en lugar de detener todo el proceso.
     * 
     * Las tareas que ejecuta son:
     * - Sincronización de clientes
     * - Sincronización de productos
     * - Sincronización de tarifas
     * - Sincronización de plantillas web
     * - Sincronización de valores de plantilla de productos
     * - Asignación de categorías con plantillas
     * - Sincronización de marcas
     * 
     * @return void No devuelve ningún valor
     */
    public function processCronTask() {

        try {
            $tasks = [
                'clients' => function() { StateController::checkSynchronizationClients(); },
                'products' => function() { StateController::checkSynchronizationProducts(); },
                'rates' => function() { StateController::checkSynchronizationRates(); },
                'web_templates' => function() { StateController::checkSynchronizationWebTemplates(); },
                'product_template_values' => function() { StateController::checkSynchronizationProductTemplateValues(); },
                'categories_templates' => function() { StateController::checkSynchronizationAsignCategoriesWithTemplates(); },
                'brands' => function() { StateController::checkSynchronizationBrands(); }
            ];
    
            foreach ($tasks as $taskName => $task) {
                try {
                    $task();
                } catch (\Exception $e) {
                    Log::error("Error en tareas programadas | processCronTask", [
                        'taskName' => $taskName,
                    ]);
                    // Continuar con la siguiente tarea
                }
            }
        } catch (\Exception $e) {
            Log::error("Error en tareas programadas (cron) | processCronTask", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }


}
