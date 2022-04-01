<?php

namespace Paym1PaymentwallPayment6\Controllers;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @RouteScope(scopes={"storefront"})
 */
class PaymentSystemController extends StorefrontController
{
    const SELECTED_PAYMENT_METHOD = 'selected_payment_method';
    private $session;
    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    /**
     * @Route("/paymentwall/savePaymentSystem", name="paymentwall.save.paymentsystem", defaults={"csrf_protected"=false, "XmlHttpRequest"=true}, methods={"POST"})
     */
    public function savePaymentSystem(Request $request, SalesChannelContext $salesChannelContext)
    {
        $requestParam = $request->getContent();
        $data = json_decode($requestParam, true);

        if (empty($data['id']) || empty($data['name'])) {
            return new JsonResponse(['status' => false, 'message' => 'Error, Please contact admin', 'data' =>  $data], Response::HTTP_BAD_REQUEST);
        }

        $this->session->set(self::SELECTED_PAYMENT_METHOD, $data);
        return new JsonResponse(['status' => true, 'data' =>  $data]);
    }
}
