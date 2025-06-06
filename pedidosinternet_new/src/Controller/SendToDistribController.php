<?php
declare(strict_types=1);

namespace PedidosInternet\Controller;

use PedidosInternet\Validator\SendToDistrib;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\JsonResponse;
use PedidosInternet\PedidosApi;
use PedidosInternet\Dto\DtoCliente;

class SendToDistribController extends FrameworkBundleAdminController
{
    /**
     *
     * @return JsonResponse
     */
    public function sendOrder(): JsonResponse
    {
        $data = json_decode(file_get_contents('php://input'), true);
        /** @var ValidatorInterface $validator */
        $validator = $this->container->get('validator');

        /*$errors = $validator->validate($data, SendToDistrib::ValidateOrder());

        if (count($errors) > 0) {
            return new JsonResponse($errors, 400);
        }*/

        $order = new \Order((int) $data['order_id']);

        $orderNote = \PedidosInternet\Dto\DtoOrderNote::createFromPrestashopOrder($order);

        $pedidosApi = PedidosApi::create();
        
        $pedidosApi->createOrder($orderNote);

        return new JsonResponse([], 202);
    }

    /**
     *
     * @return JsonResponse
     */
    public function sendClient(): JsonResponse
    {
        $data = json_decode(file_get_contents('php://input'), true);
        /** @var ValidatorInterface $validator */
        $validator = $this->container->get('validator');

        /*$errors = $validator->validate($data, SendToDistrib::ValidateClient());

        if (count($errors) > 0) {
            return new JsonResponse($errors, 400);
        }*/

        $customer = new \Customer((int) $data['client_id']);

        $dtoCliente = DtoCliente::fromPrestashopCustomer($customer);

        $pedidosApi = PedidosApi::create();
        $response_curl = $pedidosApi->createClient($dtoCliente);

        if(isset($response_curl['id']) && is_int($response_curl['id'])) {
            $dtoCliente->addId($customer->id, $response_curl['id']);
            \PrestaShopLogger::addLog("Cliente generado con id: " . $customer->id . " y api_id: " . $response_curl['id'], 1);
        }

        \PrestaShopLogger::addLog("Cliente creado en el ERP", 1);

        return new JsonResponse([], 202);
    }


}
