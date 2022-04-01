<?php

namespace Paym1PaymentwallPayment6\Controllers;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Paym1PaymentwallPayment6\Services\PaymentService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @RouteScope(scopes={"storefront"})
 */
class PaymentSuccessController extends StorefrontController
{
    private $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    /**
     * @Route("/paymentwall/payment-success", name="paymentwall.payment.success", methods={"GET"})
     */
    public function paymentSuccessHandler(Request $request, SalesChannelContext $salesChannelContext)
    {
        $orderId = $request->query->get('orderId');
        $baseUrl = $salesChannelContext->getSalesChannel()->getDomains()->first()->getUrl();
        if (empty($orderId)) {
            return new RedirectResponse($baseUrl);
        }

        $successUrl = $this->session->get(PaymentService::getPaymentwallSuccessUrlSessionName($orderId));
        if (empty($successUrl)) {
            return new RedirectResponse($baseUrl);
        }
        $this->session->remove(PaymentService::SESSION_NAME_PW_PAYMENT_SUCCESS_URL . $orderId);
        return new RedirectResponse($successUrl);
    }
}
