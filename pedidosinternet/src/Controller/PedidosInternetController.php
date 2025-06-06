<?php
declare(strict_types=1);

namespace PedidosInternet\Controller;

use Link;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Response;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;

class PedidosInternetController extends FrameworkBundleAdminController
{
    /**
     * @return Response|null
     */
    public function pedidosInternet()
    {
        $link = New Link();
        $router = SymfonyContainer::getInstance()->get('router');

        return $this->render('@Modules/pedidosinternet/views/templates/admin/pedidosInternet.html.twig');

    }
}
