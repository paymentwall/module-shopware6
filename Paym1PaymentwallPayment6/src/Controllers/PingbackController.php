<?php
namespace Paym1PaymentwallPayment6\Controllers;

use Paym1PaymentwallPayment6\Paym1PaymentwallPayment6;
use Paym1PaymentwallPayment6\Services\DeliveryConfirmationService;
use Paym1PaymentwallPayment6\Services\PaymentProvider;
use Paym1PaymentwallPayment6\Services\OrderService;
use Paym1PaymentwallPayment6\Services\UtilService;
use Paymentwall_Pingback;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class PingbackController extends StorefrontController
{
    const PINGBACK_RESPONSE_OK = 'OK';
    const PINGBACK_RESPONSE_NOT_OK = 'NOK';

    protected $orderService;
    protected $transactionStateHandler;
    protected $paymentProvider;
    protected $deliveryconfirmationService;
    protected $utilService;

    public function __construct(
        OrderService                 $orderService,
        OrderTransactionStateHandler $orderTransactionStateHandler,
        PaymentProvider              $paymentProvider,
        DeliveryConfirmationService  $deliveryConfirmationService,
        UtilService                  $utilService
    ) {
        $this->orderService = $orderService;
        $this->transactionStateHandler = $orderTransactionStateHandler;
        $this->paymentProvider = $paymentProvider;
        $this->deliveryconfirmationService = $deliveryConfirmationService;
        $this->utilService = $utilService;
    }

    /**
     * @Route("/paymentwallPingback", name="paymentwall.pingback", methods={"GET"})
     */
    public function pingbackHandler(Request $request, SalesChannelContext $salesChannelContext)
    {
        $requestParams = $request->query->all();

        /* @var OrderTransactionEntity $orderTransaction */
        $orderTransaction = $this->orderService->getOrderTransactionByField('orderId', $requestParams['goodsid'], $salesChannelContext->getContext());
        $order = $this->orderService->getOrderById($requestParams['goodsid'], $salesChannelContext->getContext());

        if (empty($orderTransaction)) {
            return new Response(self::PINGBACK_RESPONSE_NOT_OK, Response::HTTP_NOT_FOUND);
        }

        $paymentwallPaymentMethodId = $this->utilService->getPaymentMethodId($salesChannelContext->getContext());
        if (!UtilService::isPaymentwallPaymentMethod($orderTransaction->getPaymentMethodId(), $paymentwallPaymentMethodId)) {
            return new Response(self::PINGBACK_RESPONSE_NOT_OK, Response::HTTP_FORBIDDEN);
        }

        $this->paymentProvider->initPaymentwallInstance($salesChannelContext->getSalesChannelId());
        $pingback = new Paymentwall_Pingback($requestParams, '');

        if ($pingback->validate(true)) {
            if ($pingback->isCancelable()) {
                $this->transactionStateHandler->refund($orderTransaction->getId(), $salesChannelContext->getContext());
                return new Response(self::PINGBACK_RESPONSE_OK);
            }
            if ($pingback->isDeliverable()) {

                if ($this->wasOrderPaidBefore($orderTransaction)) {
                    return new Response(self::PINGBACK_RESPONSE_OK);
                }

                $this->transactionStateHandler->paid($orderTransaction->getId(), $salesChannelContext->getContext());

                $this->orderService->addPaymentwallTransactionId(
                    $requestParams['ref'],
                    $orderTransaction->getId(),
                    $salesChannelContext->getContext()
                );

                $this->sendDeliveryConfirmation($order, $salesChannelContext);
                return new Response(self::PINGBACK_RESPONSE_OK);
            }
        }
        return new Response(self::PINGBACK_RESPONSE_NOT_OK, Response::HTTP_BAD_REQUEST);
    }

    protected function sendDeliveryConfirmation($order, $salesChannelContext)
    {
        $preparedData = $this->deliveryconfirmationService->prepareDeliveryData(
            $order,
            DeliveryConfirmationService::STATUS_ORDER_PLACED,
            $salesChannelContext->getContext()
        );
        $this->deliveryconfirmationService->sendDeliveryData($preparedData, $salesChannelContext->getSalesChannelId());
    }

    private function wasOrderPaidBefore($orderTransaction): bool
    {
        if (!empty($orderTransaction->getCustomFields()[Paym1PaymentwallPayment6::ORDER_TRANSACTION_CUSTOM_FIELDS_PAYMENTWALL_TRANSACTION_ID])) {
            return true;
        }

        return false;
    }
}
