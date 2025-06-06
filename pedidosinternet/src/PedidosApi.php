<?php
declare(strict_types=1);


namespace PedidosInternet;

use PrestaShopLogger;
use Configuration;
use Hook;
use ImageManager;
use ImageType;
use PedidosInternet\Dto\DtoAccessToken;
use PedidosInternet\Dto\DtoCliente;
use PedidosInternet\Dto\DtoOrderNote;
use PedidosInternet\Helpers\CurlHelper;
use PrestaShop\PrestaShop\Adapter\Entity\Image;
use PrestaShop\PrestaShop\Adapter\Entity\Shop;
use PrestaShop\PrestaShop\Adapter\Entity\Feature;
use PrestaShop\PrestaShop\Adapter\Entity\Language;
use Symfony\Component\HttpFoundation\JsonResponse;
use PedidosInternet\Dto\DtoWebProduct;
use PedidosInternet\Dto\DtoFeature;
use PedidosInternet\Dto\DtoFeatureValue;
use PedidosInternet\Dto\DtoFamiliesWithTemplates;
use PedidosInternet\Controller\Stock;

class PedidosApi
{
    private static DtoAccessToken $dtoAccessToken;

    private string $baseUrl = "https://webapi.basterra.pedidosinternet.com:7443/";

    private string $connectUrl;
    private string $username;
    private string $pass;
    private string $clientId;
    private string $clientSecret;
    private string $scope;
    private string $resource;

    private static ?PedidosApi $pedidosApi = null;

    private function __construct()
    {
        $sqlQuery = new \DbQuery();
        $sqlQuery->select('usuario_api, password_api, client_id, client_secret, scope, url, url_append');
        $sqlQuery->from('pedidosinternet_configuracion');
        $sqlQuery->limit(1);
        $row = \Db::getInstance()->executeS($sqlQuery);
        if (!empty($row)) {
            $this->username = $row[0]['usuario_api'];
            $this->pass = $row[0]['password_api'];
            $this->baseUrl = $row[0]['url'];
            $this->clientId = $row[0]['client_id'];
            $this->clientSecret = $row[0]['client_secret'];
            $this->scope = $row[0]['scope'];
            $this->resource = $this->baseUrl . $row[0]['url_append'];

            $this->connectUrl = $this->baseUrl . "connect/token";
        }
    }

    public static function create(): ?PedidosApi
    {
        if (is_null(self::$pedidosApi)) {
            self::$pedidosApi = new PedidosApi();
        }

        try {
            $row = \Db::getInstance()->executeS(
                'SELECT `access_token`, `refresh_token`,`expiracion_token` FROM `' . _DB_PREFIX_ . 'pedidosinternet_configuracion`  WHERE id = 1'
            );

            if (!empty($row)) {
                if (empty($row[0]['access_token'])) {
                    self::$pedidosApi->accessToken();
                } else {
                    $expirationToken = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row[0]['expiracion_token']);
                    self::$dtoAccessToken = DtoAccessToken::createFromDatabase($row[0]);
                    if ($expirationToken < (new \DateTimeImmutable())) {
                        self::$pedidosApi->refreshToken();
                    }
                }
                // El tiempo de vida del access token es aún válido
                return self::$pedidosApi;
            } else {
                return null;
            }
        } catch (\Exception $ex) {
            var_dump($ex);
            return null;
        }
    }

    /**
     * @throws \Exception
     */
    public function accessToken()
    {
        $parameters = [
            'grant_type' => 'password',
            'username' => $this->username,
            'password' => $this->pass,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope' => $this->scope,
            'resource' => $this->resource
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->connectUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $server_output = curl_exec($ch);
        echo $server_output;
        if (curl_errno($ch) > 0) {
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($http_code !== 200) {
                throw new \Exception("Error en la conexión con el ERP");
            }
        }

        curl_close($ch);
        $this->saveServerAccessOrRefreshTokenOutput($server_output);
    }

    /**
     * @throws \Exception
     */
    public function refreshToken()
    {
        $parameters = [
            'grant_type' => 'refresh_token',
            'username' => $this->username,
            'password' => $this->pass,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope' => 'openid offline_access profile roles SSOScope',
            'refresh_token' => self::$dtoAccessToken->refreshToken,
            'resource' => $this->resource
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->connectUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $server_output = curl_exec($ch);
        // Si ha habido algún tipo de error en el refresh intento obtener un nuevo acceso
        if (empty($server_output) || (curl_errno($ch) > 0)) {
            curl_close($ch);
            $this->accessToken();
            return;
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_code !== 200) {
            throw new \Exception("Error en la conexión con el ERP");
        }
        curl_close($ch);
        $this->saveServerAccessOrRefreshTokenOutput($server_output);
    }

    /**
     *
     * @param string $serverOutput
     * @return void
     */
    private function saveServerAccessOrRefreshTokenOutput(string $serverOutput)
    {
        self::$dtoAccessToken = DtoAccessToken::create($serverOutput);
        \Db::getInstance()->update('pedidosinternet_configuracion', [
            'access_token' => self::$dtoAccessToken->accessToken,
            'refresh_token' => self::$dtoAccessToken->refreshToken,
            'expiracion_token' => self::$dtoAccessToken->expirationTime->format('Y-m-d H:i:s'),
        ], 'id = ' . 1);
    }

    /**
     * Devuelve una lista de clientes que se han actualizado desde una fecha.
     * También devuelve el id del log creado.
     *
     * @param \DateTimeImmutable $since
     * @return array{
     *     clients: DtoCliente[],
     *     id: integer
     * }
     * @throws \Exception
     */
    public function getClientsFromDate(\DateTimeImmutable $since): array
    {
        $url = $this->resource . "/PCCOM.Distrib.Clients.ECommerce/Clients?lastUpdatedDate={$since->format('Y-m-d')}";
        $ch = CurlHelper::getPetition(
            $url,
            self::$dtoAccessToken->accessToken
        );
        $clients = CurlHelper::executeAndCheckCode($ch, 200);

        return [
            'clients' => array_values(array_filter(
                array_map(function ($row) { return DtoCliente::create($row); }, $clients),
                fn(?DtoCliente $cliente) => !is_null($cliente)
            )),
            'id' => intval(Log::$lastInsertedId)
        ];
    }

    public function getFamiliesFromDate(): array
    {
        $url = $this->resource . "/PCCOM.Distrib.Products.ECommerce/WebFamilies";
        
        $ch = CurlHelper::getPetition(
            $url,
            self::$dtoAccessToken->accessToken
        );
        $families = CurlHelper::executeAndCheckCode($ch, 200);

        return $families;
    }

    public function getTaxonomies(): array
    {
        $url = $this->resource . "/PCCOM.Distrib.Dobuss/Taxonomy";

        $ch = CurlHelper::getPetition(
            $url,
            self::$dtoAccessToken->accessToken
        );
        $taxonomies = CurlHelper::executeAndCheckCode($ch, 200);

        return $taxonomies;
    }

    /**
     * @throws \Exception
     */
    public function getClientsByRole(string $role)
    {
        $ch = CurlHelper::getPetition(
            $this->resource . "/PCCOM.Distrib.Clients.ECommerce/Clients?idRole={$role}",
            self::$dtoAccessToken->accessToken
        );
        $clients = CurlHelper::executeAndCheckCode($ch, 200);
        return array_map(function ($row){ return DtoCliente::create($row); }, $clients);
    }

    /**
     * @param $parameter
     * @return void
     */
    public function createClient(DtoCliente $client): array
    {


        $ch = CurlHelper::postPetitionFormData(
            $this->resource . "/PCCOM.Distrib.Dobuss/Clients",
            self::$dtoAccessToken->accessToken,
            $client->toApiArray(),
            true
        );

        PrestaShopLogger::addLog("Intentando crear el cliente con id: " . $client->id, 1);
        PrestaShopLogger::addLog("Datos enviados: " . json_encode($client->toApiArray(false)), 1);

        $client_created = CurlHelper::executeAndCheckCodeClient($ch, 201, true);

        return $client_created;
    }

    /**
     *
     */
    public function updateClient(DtoCliente $client): bool
    {

        $ch = CurlHelper::putPetition(
            $this->resource . "/PCCOM.Distrib.Clients.ECommerce/Clients/{$client->id}",
            self::$dtoAccessToken->accessToken,
            $client->toApiArray(false)
        );

        PrestaShopLogger::addLog("Intentando actualizar la dirección del cliente con id: " . $client->id, 1);
        PrestaShopLogger::addLog("Datos enviados: " . json_encode($client->toApiArray(true)), 1);

        $serverOutput = curl_exec($ch);

        if (!curl_errno($ch)) {
            if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
                PrestaShopLogger::addLog('Curl: '. $serverOutput, 1);
                PrestaShopLogger::addLog('Curl Error al actualizar Usuario: '. curl_getinfo($ch, CURLINFO_HTTP_CODE), 1);
                return false;
            }
        }
        curl_close($ch);

        return true;
    }

    public function updateAddress(DtoCliente $client, \Address $address): bool
    {

        $ch = CurlHelper::putPetition(
            $this->resource . "/PCCOM.Distrib.Clients.ECommerce/Clients/{$client->id}",
            self::$dtoAccessToken->accessToken,
            $client->toApiArray(false, $address)
        );

        PrestaShopLogger::addLog("Intentando actualizar la dirección del cliente con id: " . $client->id, 1);
        PrestaShopLogger::addLog("Datos enviados: " . json_encode($client->toApiArray(true, $address)), 1);

        $serverOutput = curl_exec($ch);

        if (!curl_errno($ch)) {
            if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
                PrestaShopLogger::addLog('Curl: '. $serverOutput, 1);
                PrestaShopLogger::addLog('Curl Error al actualizar dirección de Usuario: '. curl_getinfo($ch, CURLINFO_HTTP_CODE), 1);
                return false;
            }
        }
        curl_close($ch);

        return true;
    }


    public function clientInvoices(int $clientId): array
    {
        $today = new \DateTimeImmutable();
        $oneYearAgo = $today->sub(new \DateInterval("P1Y"));
        $url = $this->resource . "/PCCOM.Distrib.Dobuss/InvoiceSummary?dateFrom=" .
            $oneYearAgo->format('Y-m-d') . "&dateTo=" . $today->format('Y-m-d') .
            "&clientId=84";// . $clientId;
        $ch = CurlHelper::getPetition(
            $url,
            self::$dtoAccessToken->accessToken
        );
        CurlHelper::executeAndCheckCode($ch, 200);

        return [];
    }

    /**
     *
     */
    public function getClientById(string $id): DtoCliente
    {
        $ch = CurlHelper::getPetition(
            $this->resource . "/PCCOM.Distrib.Clients.ECommerce/Clients/" . $id,
            self::$dtoAccessToken->accessToken
        );
        $serverOutput = curl_exec($ch);

        if (!curl_errno($ch)) {
            if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
                throw new \Exception();
            }
        }

        curl_close($ch);
        return DtoCliente::create(json_decode($serverOutput, true));
    }


    /**
     * Obtiene el listado de productos del API.
     *
     * @return array["errors" => array, "success" => array]
     * @throws \Exception
     */
    public function getWebProducts(): array
    {
        $today = new \DateTime();
        $today->sub(new \DateInterval("P30D"));

        $url = $this->resource . "/PCCOM.Distrib.Products.ECommerce/WebProducts";
        $ch = CurlHelper::getPetition(
            $url,
            self::$dtoAccessToken->accessToken
        );
        $products = CurlHelper::executeAndCheckCode($ch, 200);

        

        $errors = [];
        $success = [];

        $imagesUrl = $this->resource . "/PCCOM.Distrib.Products.ECommerce/WebProductImage/";

        // Procesamos cada producto recibido. Si tiene los datos mínimos necesarios se crea en la BD
        foreach ($products as $position => $product) {
            $webProduct = DtoWebProduct::create($product);
            if (is_null($webProduct)) {
                continue;
            }
            
            $created = $webProduct->createOrSaveProduct();
            
            if ($created === null) {
                $errors[] = $webProduct;
            } else {
                $created->deleteImages();
                $position_image = 1;
                $sql = 'SELECT `id_product` FROM ' . _DB_PREFIX_ . 'product WHERE `reference` = \'' . pSQL($created->reference) . '\'';
                $product_id = \Db::getInstance()->getValue($sql);
                Stock::updateProductStock($product_id);

                if (count($webProduct->imagesInformation) > 0) {
                    
                    foreach ($webProduct->imagesInformation as $image) {
                        if($image['sendToWeb'] == true) {
                            $chImage = CurlHelper::getPetition(
                                $imagesUrl . $image['id'],
                                self::$dtoAccessToken->accessToken
                            );
                            $imageResult = CurlHelper::executeAndCheckCode($chImage, 200);
                            
                            $this->addImageToProduct(intval($created->id), $imageResult, $position_image);
                    
                            $position_image++;
                        }
                    }
                }
            }
                $success[] = $webProduct;
            }

        return [
            "errors" => $errors,
            "success" => $success
        ];
    }

    public function getRates(): array
    {
        $initialTime = new \DateTimeImmutable();
        
        $url = $this->resource . "/PCCOM.Distrib.Rates.ECommerce/RatePrices";
        $ch = CurlHelper::getPetition(
            $url,
            self::$dtoAccessToken->accessToken
        );
        $curlinfo = curl_getinfo($ch);
        $rates = CurlHelper::executeAndCheckCode($ch, 200);

        Log::create($initialTime, new \DateTimeImmutable(), Log::API, $url, $rates, $curlinfo);

        foreach ($rates as $rate) {
            
            DtoWebProduct::updatePrice(
                $rate['reference'],
                floatval($rate['ratePrice']),
                boolval($rate['isPVP'])
            );

            DtoWebProduct::asignProductToPriceCategory($rate['reference'], floatval($rate['ratePrice']), boolval($rate['isPVP']));
        }

        return [];
    }

    public function getWebTemplates(): array
    {
        $initialTime = new \DateTimeImmutable();

        $langDefault = (int)\Configuration::get('PS_LANG_DEFAULT');

        $shops = Shop::getShops(true, null, true);

        $currentShopId = array_values($shops)[0];

        $urlTemplate = $this->resource . "/PCCOM.Distrib.Dobuss/Template";

        $chTemplate = CurlHelper::getPetition(
            $urlTemplate,
            self::$dtoAccessToken->accessToken
        );

        $curlinfo = curl_getinfo($chTemplate);
        $webTemplates = CurlHelper::executeAndCheckCode($chTemplate, 200);

        $endTime = new \DateTimeImmutable();
        Log::create($initialTime, $endTime, Log::API, $urlTemplate, $webTemplates, $curlinfo);
        $initialTime = new \DateTimeImmutable();

        $urlTaxonomies = $this->resource . "/PCCOM.Distrib.Dobuss/Taxonomy";

        $chTaxonomies = CurlHelper::getPetition(
            $urlTaxonomies,
            self::$dtoAccessToken->accessToken
        );

        $curlinfo = curl_getinfo($chTaxonomies);
        $taxonomies = CurlHelper::executeAndCheckCode($chTaxonomies, 200);
         
        Log::create($initialTime, new \DateTimeImmutable(), Log::API, $urlTaxonomies, $taxonomies, $curlinfo);

        foreach ($webTemplates as $template) {
            
            if($template['id'] == 8) {
                
                foreach($template['fieldsWithoutGroup'] as $featureValue) {

                    $conbinedValue = $featureValue['id'] . '-' . $featureValue['name'];
                    
                    switch ($conbinedValue) {
                        case "50-Nombre del vino":
                            break;
                        case "54-Descripción":
                            break;
                        case "57-Cuerpo (Ligero - Potente)":
                            DtoFeature::createFeature($langDefault, $currentShopId, $featureValue);
                            DtoFeatureValue::setNumbericFeatureValues($langDefault, $featureValue);
                        case "58-Fruta (Frutal - Madura)":
                            DtoFeature::createFeature($langDefault, $currentShopId, $featureValue);
                            DtoFeatureValue::setNumbericFeatureValues($langDefault, $featureValue);
                        case "59-Acidez (Fresco - Cálido)":
                            DtoFeature::createFeature($langDefault, $currentShopId, $featureValue);
                            DtoFeatureValue::setNumbericFeatureValues($langDefault, $featureValue);
                        case "70-MWP: Maridaje":
                            break;
                        case "71-Oferta Flash":
                            break;
                        default:        
                            DtoFeature::createFeature($langDefault, $currentShopId, $featureValue);
                            DtoFeatureValue::createFeatureValue($langDefault, $featureValue, $taxonomies);
                    }

                }
            }

        }

        return [];

    }

    public function getProductTemplateValues(): array
    {
        $initialTime = new \DateTimeImmutable();

        $iteration = 0;

        while($iteration >= 0) {

            PrestaShopLogger::addLog('Llamando a la página '. $iteration .' de ProductTemplateValues', 1);

            $url = $this->resource . "/PCCOM.Distrib.Dobuss/ProductTemplateValues/?PageNum=" . $iteration;
            
            $ch = CurlHelper::getPetition(
                $url,
                self::$dtoAccessToken->accessToken
            );
            
            $curlinfo = curl_getinfo($ch);
            $ProductTemplateValues = CurlHelper::executeAndCheckCode($ch, 200);
            
            if($iteration == 0 && !empty($ProductTemplateValues)) {
                $sql = 'TRUNCATE TABLE `'._DB_PREFIX_.'feature_product`';
                \Db::getInstance()->execute($sql);
            }

            $iteration++;

            if(empty($ProductTemplateValues)) {
                $iteration =- $iteration;
                PrestaShopLogger::addLog('Total de páginas de ProductTemplateValues: '. $iteration, 1);
                $iteration = -1;
                return [];
            }

            $url_template = $this->resource . "/PCCOM.Distrib.Dobuss/Template";
            $ch_template = CurlHelper::getPetition(
                $url_template,
                self::$dtoAccessToken->accessToken
            );
        
            $curl_info = curl_getinfo($ch_template);
            $WebTemplates = CurlHelper::executeAndCheckCode($ch_template, 200);

            Log::create($initialTime, new \DateTimeImmutable(), Log::API, $url_template, $WebTemplates, $curl_info);

            foreach($ProductTemplateValues as $key => $templateValues) {
                
                $product_reference = strval($key);
                
                foreach($templateValues as $templateValue) {
                    $sql = 'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'product WHERE `reference` = \'' . pSQL($product_reference) . '\'';
                    $product_exist = \Db::getInstance()->getValue($sql);

                    if($product_exist) {
                        $sql = 'SELECT `id_product` FROM ' . _DB_PREFIX_ . 'product WHERE `reference` = \'' . pSQL($product_reference) . '\'';
                        $product_id = \Db::getInstance()->getValue($sql);

                        $id_feature = $templateValue["fieldId"];
                        $value = $templateValue["value"];

                        if($id_feature == 50) {
                            DtoWebProduct::updateProductName($product_id, $value);
                        }

                        if($id_feature == 54) {
                            DtoWebProduct::updateProductShortDescription($product_id, $value);
                        }

                        if($id_feature == 57 || $id_feature == 58 || $id_feature == 59) {
                            
                            $data_feature_product = [
                                "id_product" => $product_id,
                                "id_feature" => $id_feature,
                                "id_feature_value" => $id_feature . $value,
                            ];

                            $conditionalSQL = 'SELECT * FROM `'._DB_PREFIX_.'feature_product` WHERE `id_product` = '. $product_id. ' AND `id_feature` = '. $id_feature .' AND `id_feature_value` = '. $id_feature . $value;
                            
                            if(!\DB::getInstance()->getValue($conditionalSQL)) {
                                \Db::getInstance()->insert('feature_product',$data_feature_product);
                            }

                        }

                        if($id_feature == 71) {
                            DtoFamiliesWithTemplates::assignToFlashOffersCategory($product_id);
                        }

                        foreach ($WebTemplates as $template) {
                
                            if($template['id'] != 8) {
                                dump($template['name'] . ' no se añade');
                            } else {
                
                                foreach($template['fieldsWithoutGroup'] as $taxonomy) {
                                    if($taxonomy['id'] == $id_feature) {
                                        $taxonomyID = $taxonomy['taxonomyId'];
                                    }
                                }
                            }
                        }

                        if($taxonomyID && $id_feature != 70) {

                            $values = explode(";", $value);

                            foreach($values as $val) {
                                $data_feature_product = [
                                    "id_product" => $product_id,
                                    "id_feature" => $id_feature,
                                    "id_feature_value" => $taxonomyID . 0 . $val,
                                ];

                                $conditionalSQL = 'SELECT * FROM `'._DB_PREFIX_.'feature_product` WHERE `id_product` = '. $product_id. ' AND `id_feature` = '. $id_feature .' AND `id_feature_value` = '. $taxonomyID . 0 . $val;
                                
                                if(!\Db::getInstance()->getValue($conditionalSQL)) {
                                    \Db::getInstance()->insert('feature_product',$data_feature_product);
                                }
                            }

                        }

                    }
                }

            }

        }

        return [];
        
    }

    public function asignFamiliesWithTemplates(): void 
    {
        DtoFamiliesWithTemplates::asignValues();
    }

    public function getInvoiceSummary(): array
    {
        $url = $this->resource . "/PCCOM.Distrib.Dobuss/InvoiceSummary?dateFrom=2023-06-01&dateTo=2023-08-09";
        $ch = CurlHelper::getPetition(
            $url,
            self::$dtoAccessToken->accessToken
        );
        $rates = CurlHelper::executeAndCheckCode($ch, 200);

        return [];
    }

    public function getPickingChanges(): array
    {
        $url = $this->resource . "/PCCOM.Distrib.Dobuss/OrderPickingChanges?lastUpdateDate=2023-08-01";
        $ch = CurlHelper::getPetition(
            $url,
            self::$dtoAccessToken->accessToken
        );
        $rates = CurlHelper::executeAndCheckCode($ch, 200);

        return [];
    }

    private function addImageToProduct(int $productId, array $image, int $position)
    {
        $tempfile = tempnam(_PS_TMP_IMG_DIR_, 'product_image_');
        $handle = fopen($tempfile, 'w');
        fwrite($handle, base64_decode($image['base64Content']));

        fclose($handle);

        $shops = Shop::getShops(true, null, true);
        $currentShopId = array_values($shops)[0];
        $dbImage = new Image();
        $dbImage->id_product = $productId;
        $dbImage->position = $position;
        if ($position === 1) {
            $dbImage->cover = true;
        }
        else {
            $dbImage->cover = NULL;
        }
        
        $dbImage->delete();
        if (($dbImage->validateFields(false, true)) === true &&
            ($dbImage->validateFieldsLang(false, true)) === true &&
            $dbImage->add()
        ) {
            $dbImage->associateTo($shops);


            $path = $dbImage->getPathForCreation();
            if (!ImageManager::checkImageMemoryLimit($tempfile)) {
                @unlink($tempfile);

                return false;
            }

            $tgt_width = $tgt_height = 0;
            $src_width = $src_height = 0;
            $error = 0;
            ImageManager::resize($tempfile, $path . '.jpg', null, null, 'jpg', false, $error, $tgt_width, $tgt_height, 5, $src_width, $src_height);
            $images_types = ImageType::getImagesTypes('products', true);

            $path_infos = [];
            $path_infos[] = [$tgt_width, $tgt_height, $path . '.jpg'];
            foreach ($images_types as $image_type) {
                $tmpfile = self::get_best_path($image_type['width'], $image_type['height'], $path_infos);

                if (ImageManager::resize(
                    $tmpfile,
                    $path . '-' . stripslashes($image_type['name']) . '.jpg',
                    $image_type['width'],
                    $image_type['height'],
                    'jpg',
                    false,
                    $error,
                    $tgt_width,
                    $tgt_height,
                    5,
                    $src_width,
                    $src_height
                )) {
                    // the last image should not be added in the candidate list if it's bigger than the original image
                    if ($tgt_width <= $src_width && $tgt_height <= $src_height) {
                        $path_infos[] = [$tgt_width, $tgt_height, $path . '-' . stripslashes($image_type['name']) . '.jpg'];
                    }

                    if (is_file(_PS_TMP_IMG_DIR_ . 'product_mini_' . $productId . '.jpg')) {
                        unlink(_PS_TMP_IMG_DIR_ . 'product_mini_' . $productId . '.jpg');
                    }
                    if (is_file(_PS_TMP_IMG_DIR_ . 'product_mini_' . $productId . '_' . $currentShopId . '.jpg')) {
                        unlink(_PS_TMP_IMG_DIR_ . 'product_mini_' . $productId . '_' . $currentShopId . '.jpg');
                    }
                }
            }
        } else {
            return;
        }
    }

    private static function get_best_path($tgt_width, $tgt_height, $path_infos)
    {
        $path_infos = array_reverse($path_infos);
        $path = '';
        foreach ($path_infos as $path_info) {
            list($width, $height, $path) = $path_info;
            if ($width >= $tgt_width && $height >= $tgt_height) {
                return $path;
            }
        }

        return $path;
    }

    /**
     * @param $reference
     * @return DtoWebProduct
     */
    public function getWebProductByReference($reference): DtoWebProduct
    {
        $ch = CurlHelper::getPetition(
            $this->baseUrl . "pruebaswebapi/PCCOM.Distrib.Products.ECommerce/WebProducts/" . $reference,
            self::$dtoAccessToken->accessToken
        );

        $server_output = curl_exec($ch);

        if (!curl_errno($ch)) {
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            switch ($http_code) {
                case 200:#OK
                    echo "\nTodos los productos obtenidos";
                    break;
                default:
                    echo 'Unexpected HTTP code:', $http_code, "\n";
            }
        }

        curl_close($ch);

        $webProduct = new DtoWebProduct();
        $webProduct = $webProduct->create(json_decode($server_output, true));

        return $webProduct;
    }

    /**
     * @return array
     */
    public function getWebProductsPendingOfSynchronization(): array
    {
        // @todo Falta poner la fecha dateFrom
        $ch = CurlHelper::getPetition(
            $this->baseUrl . "pruebaswebapi/PCCOM.Distrib.Products.ECommerce/WebProducts/pendingOfSynchronization",
            self::$dtoAccessToken->accessToken,
            [
                "PageNum" => 5,
                "PageSize" => 5
            ]
        );
        $server_output = curl_exec($ch);

        if (!curl_errno($ch)) {
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            switch ($http_code) {
                case 200:#OK
                    echo "\nTodos los productos obtenidos\n";
                    break;
                default:
                    echo "\nUnexpected HTTP code: ", $http_code, "\n";
            }
        }

        curl_close($ch);
        var_dump($server_output);

        $arrWebProducts = [];
        foreach (json_decode($server_output, true) as $key => $value) {
            $arrWebProducts[] = self::$webProduct = DtoWebProduct::create($value);

        }
        return $arrWebProducts;
    }

    /**
     * @deprecated
     *
     * @return array{
     *          clients: bool,
     *          orderLines: bool,
     *          products: bool,
     *          promotions: bool,
     *          rates: bool,
     *          warehouses: bool,
     *          lastChange: \DateTimeImmutable
     *     }
     * @throws \PrestaShopDatabaseException
     * @throws \Exception Se lanzará si la fecha devuelta por el API no es correcta. No debería suceder nunca
     */
    public function getLastChanges(): array
    {
        $dateOneYear = date("Y-m-d H:i:s", strtotime(date("Y-m-d H:i:s") . "- 1 year"));
        $url = $this->baseUrl .
            "pruebaswebapi/PCCOM.Distrib.Synchronization.ECommerce/LastChanges?dateFromWhichRetrieveChangesUtcDateTime={$dateOneYear}";
        $ch = CurlHelper::getPetition($url, self::$dtoAccessToken->accessToken);
        $serverOutput = curl_exec($ch);

        if (curl_errno($ch)) {
            return new JsonResponse(['error' => curl_error($ch)]);
        }
        curl_close($ch);
        $jsonRecibido = json_decode($serverOutput, true, 512, 0);

        Log::create(new \DateTimeImmutable(), new \DateTimeImmutable(),Log::API, $url, $jsonRecibido, []);

        return [
            //'id' => Log::$lastInsertedId,
            'clients' => true,//$jsonRecibido['isClientsPendingToSynchronize'],
            'orderLines' => $jsonRecibido['isOrderNoteLinesPendingToSynchronize'],
            'products' => $jsonRecibido['isProductsPendingToSynchronize'],
            'promotions' => $jsonRecibido['isPromotionsPendingToSynchronize'],
            'rates' => $jsonRecibido['isRatesPendingToSynchronize'],
            'warehouses' => $jsonRecibido['isWareHouseLinesPendingToSynchronize'],
            'lastChange' => new \DateTimeImmutable($jsonRecibido['lastChangesRetrievalRequestDateTime']),
        ];
    }

    /**
     * Crea una orden en el API
     *
     * @param DtoOrderNote $orderNote
     * @return bool
     */
    public function createOrder(DtoOrderNote $orderNote): bool
    {

        $url = $this->resource . "/PCCOM.Distrib.Dobuss/WebB2COrderNotes";
        $ch = CurlHelper::postPetition(
            $url,
            self::$dtoAccessToken->accessToken,
            $orderNote->toApiArray(true),
            false
        );
        
        PrestaShopLogger::addLog($orderNote->toApiArray(true), 1);
        PrestaShopLogger::addLog($ch, 1);

        CurlHelper::executeAndCheckCode($ch, 201, true);

        return true;
    }

    public function asignBrandsToProduct() {

        $products = \Product::getProducts(\Context::getContext()->language->id, 0, 0, 'id_product', 'ASC');
        $featureValues = \FeatureValue::getFeatureValuesWithLang(\Context::getContext()->language->id, 51);

        foreach ($products as $product) {

            $features = \Product::getFeaturesStatic($product['id_product']);

            foreach ($features as $feature) {

                $featureId = $feature['id_feature'];

                if($featureId == 51) {

                    $featureValueId = $feature['id_feature_value'];

                    $featureValues = \FeatureValue::getFeatureValueLang($featureValueId, \Context::getContext()->language->id);
                    
                    foreach($featureValues as $featureValue) {

                        $DtoProduct = new \Product($product['id_product']);

                        $DtoProduct->id_manufacturer = 0;
                        $DtoProduct->update();

                        $manufacturerId = \Manufacturer::getIdByName($featureValue['value']);
                        $DtoProduct->id_manufacturer = $manufacturerId;
                        $DtoProduct->update();
                        
                    }
                }
            }
        }
    }

    public function updateAddressOrderOnClient(\Order $order, \Customer $customer) {

        $id_address_delivery = $order->id_address_delivery;
        $id_address_invoice = $order->id_address_invoice;

        $dtoCliente = DtoCliente::fromPrestashopCustomer($customer, $id_address_delivery, $id_address_invoice);
        $pedidosApi = PedidosApi::create();
        $updated = $pedidosApi->updateClient($dtoCliente);

        if($updated) {
            PrestaShopLogger::addLog("Cliente actualizado con id: " . $customer->id . " y api_id: " . $dtoCliente->id, 1);
        } else {
            PrestaShopLogger::addLog("No se ha actualizado correctamente el cliente: " . $customer->id, 1);
        }
 
    }
    
}
