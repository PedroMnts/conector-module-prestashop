<?php
declare(strict_types=1);

namespace PedidosInternet\Controller;

use PedidosInternet\Validator\Configuration;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\JsonResponse;

class ConfigurationController extends FrameworkBundleAdminController
{
    public function retrieve(): JsonResponse
    {
        $sqlQuery = new \DbQuery();
        $sqlQuery->select('usuario_api, password_api, client_id, client_secret, scope, url, url_append, ultimos_cambios');
        $sqlQuery->from('pedidosinternet_configuracion');
        $sqlQuery->limit(1);
        $row = \Db::getInstance()->executeS($sqlQuery);
        if (!empty($row)) {
            $data = $row[0];
            return new JsonResponse([
                'username' => $data['usuario_api'],
                'password' => empty($data['password_api']) ? '' : '*',
                'url' => $data['url'],
                'url_append' => $data['url_append'],
                'client_id' => $data['client_id'],
                'client_secret' => $data['client_secret'],
                'scope' => $data['scope'],
                'lastSynchronization' => $data['ultimos_cambios']
            ]);
        }
        return new JsonResponse([], 412);
    }

    /**
     * Guarda la configuración. Si el campo password está vacío o es igual a '*' no se actualiza.
     * Si se ha guardado con éxito devuelve un código 202
     * En caso de error en los campos suministrados devolverá un error 400 con los errores encontrados
     * Recibe: json{
     *      usuario_api: string,
     *      password_api: string,
     *      url: string,
     *      url_append: string,
     *      client_id: string,
     *      client_secret: string,
     *      scope: string,
     * }
     *
     * @return JsonResponse
     */
    public function save(): JsonResponse
    {
        $data = json_decode(file_get_contents('php://input'), true);
        /** @var ValidatorInterface $validator */
        $validator = $this->container->get('validator');

        $errors = $validator->validate($data, Configuration::update());
        if (count($errors) > 0) {
            return new JsonResponse($errors, 400);
        }

        $dataToUpdate = [
            'usuario_api' => $data['username'],
            'url' => $data['url'],
            'url_append' => $data['url_append'],
            'client_id' => $data['client_id'],
            'client_secret' => $data['client_secret'],
            'scope' => $data['scope'],
        ];
        if (!empty($data['password']) || ($data['password'] === '*')) {
            $dataToUpdate['password_api'] = $data['password'];
        }

        \Db::getInstance()->update('pedidosinternet_configuracion', $dataToUpdate);

        return new JsonResponse([], 202);
    }
}
