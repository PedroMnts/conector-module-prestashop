<?php
declare(strict_types=1);

namespace PedidosInternet\Controller;

use PedidosInternet\Log;
use PedidosInternet\PedidosApi;
use PedidosInternet\Dto\DtoFamily;
use PedidosInternet\PrestashopApi;
use Symfony\Component\HttpFoundation\JsonResponse;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;

class StateController extends FrameworkBundleAdminController
{

    public function checkSynchronizationClients(): JsonResponse
    {
        $pedidosApi = PedidosApi::create();

        if (empty($pedidosApi)) {
            return new JsonResponse(["error" => "No se ha podido acceder al API"], 500);
        }

        $syncClientsRole2 = $pedidosApi->getClientsByRole("2");
        $errorsSaving = [];

        foreach ($syncClientsRole2 as $client) {

            $idOrError = PrestashopApi::createOrUpdateClient($client);

            if (is_string($idOrError)) {
                $errorsSaving[] = [
                    'id' => $client->id,
                    'error' => $idOrError,
                    'email' => $client->email,
                    'name' => $client->tradeName,
                ];
            }
        }

        return new JsonResponse(["errors" => $errorsSaving]);
    }

    public function checkSynchronizationFamilies(): JsonResponse
    {
        $pedidosApi = PedidosApi::create();
        if (empty($pedidosApi)) {
            return new JsonResponse(["error" => "No se ha podido acceder al API"], 500);
        }
        $syncInfoTaxonomies = $pedidosApi->getTaxonomies();

        $langDefault = (int)\Configuration::get('PS_LANG_DEFAULT');
        $this->processCategoriesFromTaxonomy($langDefault, $syncInfoTaxonomies, (int)\Configuration::get('PS_HOME_CATEGORY'));

        return new JsonResponse([]);
    }

    private function processCategories(int $langDefault, array $category, int $parent)
    {
        foreach ($category as $family) {
            $createdCategory = DtoFamily::createCategory($langDefault, $family, $parent);

            if (count($family['childrenTerms']) > 0) {
                $this->processCategories($langDefault, $family['childrenTerms'], (int)$createdCategory->id);
            }
        }
    }

    private function processCategoriesFromTaxonomy(int $langDefault, array $taxonomies, int $parent)
    {
        foreach ($taxonomies as $taxonomy) {

            if($taxonomy['id'] == 4 || $taxonomy['id'] == 15 || $taxonomy['id'] == 18) {

                $createdCategory = DtoFamily::createCategoryWithTaxonomy($langDefault, $taxonomy, $parent);

                if (count($taxonomy['terms']) > 0) {
                    foreach($taxonomy['terms'] as $term) {
                        DtoFamily::createCategoryWithTaxonomy($langDefault, $term, (int)$createdCategory->id);
                    }
                   
                }

            }

        }
    }

    public function checkSynchronizationProducts(): JsonResponse
    {
        $initialTime = new \DateTimeImmutable();

        $pedidosApi = PedidosApi::create();
        if (empty($pedidosApi)) {
            return new JsonResponse(["error" => "No se ha podido acceder al API"], 500);
        }

        $result = $pedidosApi->getWebProducts();

        $endTime = new \DateTimeImmutable();

        Log::create($initialTime, $endTime, Log::API, 'https://webapi.basterra.pedidosinternet.com:7443/basterra/PCCOM.Distrib.Products.ECommerce/WebProducts', [], $result);

        return new JsonResponse($result['errors']);
    }

    public function checkSynchronizationRates(): JsonResponse
    {

        $pedidosApi = PedidosApi::create();
        if (empty($pedidosApi)) {
            return new JsonResponse(["error" => "No se ha podido acceder al API"], 500);
        }
        $pedidosApi->getRates();

        return new JsonResponse([]);
    }

    public function checkSynchronizationWebTemplates(): JsonResponse
    {

        $pedidosApi = PedidosApi::create();
        if (empty($pedidosApi)) {
            return new JsonResponse(["error" => "No se ha podido acceder al API"], 500);
        }
        $pedidosApi->getWebTemplates();

        return new JsonResponse([]);
    }

    public function checkSynchronizationProductTemplateValues(): JsonResponse
    {
        $pedidosApi = PedidosApi::create();
        if (empty($pedidosApi)) {
            return new JsonResponse(["error" => "No se ha podido acceder al API"], 500);
        }
        $result = $pedidosApi->getProductTemplateValues();

        return new JsonResponse([]);
    }

    public function checkSynchronizationAsignFamiliesWithTemplates(): JsonResponse
    {
        $initialTime = new \DateTimeImmutable();

        $pedidosApi = PedidosApi::create();
        if (empty($pedidosApi)) {
            return new JsonResponse(["error" => "No se ha podido acceder al API"], 500);
        }

        $result = $pedidosApi->asignFamiliesWithTemplates();

        Log::create($initialTime, new \DateTimeImmutable(), Log::API, 'Valores Asiganos', [], ['Sin valor devuelto']);

        return new JsonResponse([]);
    }

    public function checkSynchronizationBrands(): JsonResponse
    {
        $pedidosApi = PedidosApi::create();
        if (empty($pedidosApi)) {
            return new JsonResponse(["error" => "No se ha podido acceder al API"], 500);
        }

        $pedidosApi->asignBrandsToProduct();

        return new JsonResponse([]);
    }

    public function DeleteLog(): JsonResponse
    {
       Log::delete();

       return new JsonResponse([]);
    }

    public function checkSynchronizationInvoicesSummary(): JsonResponse
    {
        $pedidosApi = PedidosApi::create();
        if (empty($pedidosApi)) {
            return new JsonResponse(["error" => "No se ha podido acceder al API"], 500);
        }
        $result = $pedidosApi->getInvoiceSummary();

        return new JsonResponse([]);
    }

    public function checkSynchronizationPickingChanges(): JsonResponse
    {
        $pedidosApi = PedidosApi::create();
        if (empty($pedidosApi)) {
            return new JsonResponse(["error" => "No se ha podido acceder al API"], 500);
        }
        $result = $pedidosApi->getPickingChanges();

        return new JsonResponse([]);
    }

    public function customerInvoices(): JsonResponse
    {
        return new JsonResponse([]);
    }
}
