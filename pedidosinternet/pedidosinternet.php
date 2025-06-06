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

class PedidosInternet extends Module
{
    
    protected $configForm = false;

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
        $this->version = '1.0.3';
        $this->author = 'Dobuss';
        $this->need_instance = 1;

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Conector con Distrib');
        $this->description = $this->l('Interacción con ERP');

        $this->confirmUninstall = $this->l('');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);


    }

    public function install()
    {
        Configuration::updateValue('PEDIDOSINTERNET_LIVE_MODE', false);

        /* Generate a random token
        $token = md5(uniqid(rand(), true));
        Configuration::updateValue('PEDIDOSINTERNET_CRON_TOKEN', $token);*/

        include(dirname(__FILE__).'/sql/install.php');

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('displayBackOfficeHeader') &&
            $this->registerHook('actionAdminControllerSetMedia') &&
            $this->registerHook('actionCustomerAccountAdd') &&
            $this->registerHook('actionOrderStatusUpdate') &&
            $this->registerHook('PaymentConfirmation') &&
            $this->registerHook('displayCustomerAccount') &&
            $this->registerHook('actionObjectCustomerAddAfter') &&
            $this->registerHook('additionalCustomerFormFields') &&
            $this->registerHook('actionCustomerAccountUpdate') &&
            $this->registerHook('displayAdminOrderSide') &&
            $this->registerHook('actionObjectAddressUpdateAfter')
        ;
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

    public function searchProducts($search)
    {
        $search = str_replace(' ', '%', $search);
        return Db::getInstance()->executeS('SELECT id_product, `name`
            FROM '._DB_PREFIX_.'product_lang
            WHERE id_lang = '.(int)$this->context->language->id.'
            AND `name` LIKE "%'.$search.'%"');
    }

    /**
     * @param array{newCustomer: Customer} $params
     * @return void
     */
    public function hookActionCustomerAccountAdd(array $params){

        $customer = $params['newCustomer'];

        if ($customer instanceof Customer) {
            $dtoCliente = DtoCliente::fromPrestashopCustomer($customer);

            $pedidosApi = PedidosApi::create();
            $response_curl = $pedidosApi->createClient($dtoCliente);

            if(isset($response_curl['id']) && is_int($response_curl['id'])) {
                $dtoCliente->addId($customer->id, $response_curl['id']);
                PrestaShopLogger::addLog("Cliente generado con id: " . $customer->id . " y api_id: " . $response_curl['id'], 1);
            }

            PrestaShopLogger::addLog("Cliente creado en el ERP", 1);
            
        } else {
            return false;
        }

    }

    /**
     * @param array{
     *  newOrderStatus: \OrderState,
     *  id_order: int
     * } $params
     * @return void
     */
    public function hookActionOrderStatusUpdate(array $params)
    {
        $newStatus = $params['newOrderStatus'];

        $order = new Order((int) $params['id_order']);

        if ($params['newOrderStatus']->name === "Pago aceptado" || $params['newOrderStatus']->name === 'Pago remoto aceptado') {
            $orderNote = \PedidosInternet\Dto\DtoOrderNote::createFromPrestashopOrder($order);

            $pedidosApi = PedidosApi::create();
            
            $pedidosApi->createOrder($orderNote);

            $customer = new Customer((int)$order->id_customer);
            $pedidosApi->updateAddressOrderOnClient($order, $customer);
        }
    }

    public function hookPaymentConfirmation(array $params)
    {
        $order_id = (int) $params['id_order'];
        $order = new Order($order_id);

        PrestaShopLogger::addLog($params, 1);

        $orderNote = \PedidosInternet\Dto\DtoOrderNote::createFromPrestashopOrder($order);

        $pedidosApi = PedidosApi::create();
        
        $pedidosApi->createOrder($orderNote);

        $customer = new Customer((int)$order->id_customer);
        $pedidosApi->updateAddressOrderOnClient($order, $customer);
        
    }
                    
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

        $address->phone =  Tools::getValue('phone');


        $address->dni = Tools::getValue('dni');

        $address->add();

   }  

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

    public function hookActionCustomerAccountUpdate(array $params)
    {
        $customer = $params['customer'];

        if ($customer instanceof Customer) {
            $dtoCliente = DtoCliente::fromPrestashopCustomer($customer);
            $pedidosApi = PedidosApi::create();
            $updated = $pedidosApi->updateClient($dtoCliente);

            if($updated) {
                PrestaShopLogger::addLog("Cliente actualizado con id: " . $customer->id . " y api_id: " . $dtoCliente->id, 1);
            } else {
                PrestaShopLogger::addLog("No se ha actualizado correctamente el cliente: " . $customer->id, 1);
            }
            
        } else {
            PrestaShopLogger::addLog("Error al actualizar el cliente. No hay datos de cliente", 1);
            return false;
        }
    }

    public function hookActionObjectAddressUpdateAfter(array $params)
    {
        $address = $params['object'];

        if ($address instanceof Address) {
            $customer = new Customer((int)$address->id_customer);

            $dtoCliente = DtoCliente::fromPrestashopCustomer($customer);
            $pedidosApi = PedidosApi::create();
            $updated = $pedidosApi->updateAddress($dtoCliente, $address);

            if($updated) {
                PrestaShopLogger::addLog("Dirección actualizada del cliente con id: " . $customer->id . " y api_id: " . $dtoCliente->id, 1);
            } else {
                PrestaShopLogger::addLog("No se ha actualizado correctamente la dirección del cliente: " . $customer->id, 1);
            }
            
        } else {
            PrestaShopLogger::addLog("Error al actualizar la dirección del cliente. No hay datos de dirección del cliente", 1);
            return false;
        }
    }

    public function hookDisplayCustomerAccount(array $params)
    {
        $pedidosApi = PedidosApi::create();
        $pedidosApi->clientInvoices($this->context->customer->id);

        return $this->display(__FILE__, 'views/templates/hook/customclientinfo.tpl');
    }

    public function processCronTask() {
        StateController::checkSynchronizationClients();
        StateController::checkSynchronizationProducts();
        StateController::checkSynchronizationRates();
        StateController::checkSynchronizationWebTemplates();
        StateController::checkSynchronizationProductTemplateValues();
        StateController::checkSynchronizationAsignFamiliesWithTemplates();
        StateController::checkSynchronizationBrands();
    }


}
