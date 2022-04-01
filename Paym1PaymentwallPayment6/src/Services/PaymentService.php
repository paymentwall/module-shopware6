<?php
namespace Paym1PaymentwallPayment6\Services;

use Paym1PaymentwallPayment6\Controllers\PaymentSystemController;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Symfony\Component\HttpFoundation\Session\Session;
use Paymentwall_Widget;
use Paymentwall_Product;
use Symfony\Component\Routing\RouterInterface;

class PaymentService implements AsynchronousPaymentHandlerInterface
{
    const SESSION_NAME_PW_PAYMENT_SUCCESS_URL = 'pw_payment_success_url';
    protected $transactionStateHandler;
    protected $pluginConfigService;
    protected $paymentProvider;
    protected $session;
    protected $router;

    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        PluginConfigService $pluginConfigService,
        PaymentProvider $paymentProvider,
        Session $session,
        RouterInterface $router
    ) {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->paymentProvider = $paymentProvider;
        $this->pluginConfigService = $pluginConfigService;
        $this->session = $session;
        $this->router = $router;
    }

    public function pay(
        AsyncPaymentTransactionStruct $transaction,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext
    ): RedirectResponse {
        // Method that sends the return URL to the external gateway and gets a redirect URL back
        try {
            $checkout = $this->prepareCheckoutWidget($transaction, $salesChannelContext);
        } catch (\Exception $e) {
            throw new AsyncPaymentProcessException(
                $transaction->getOrderTransaction()->getId(),
                'An error occurred during the communication with external payment gateway' . PHP_EOL . $e->getMessage()
            );
        }

        // Redirect to external gateway
        return new RedirectResponse($checkout->getUrl());
    }

    /**
     * @throws CustomerCanceledAsyncPaymentException
     */
    public function finalize(
        AsyncPaymentTransactionStruct $transaction,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): void {
    }

    public function prepareCheckoutWidget(
        AsyncPaymentTransactionStruct $transaction,
        SalesChannelContext $salesChannelContext
    ): Paymentwall_Widget {
        $this->paymentProvider->initPaymentwallInstance($salesChannelContext->getSalesChannelId());

        $order = $transaction->getOrder();
        $customer = $order->getOrderCustomer();
        $pluginConfig = $this->pluginConfigService->getPaymentwallPluginConfigForSalesChannel($salesChannelContext->getSalesChannelId());
        $selectedPaymentMethod = ($this->session->has(PaymentSystemController::SELECTED_PAYMENT_METHOD))
            ? $this->session->get(PaymentSystemController::SELECTED_PAYMENT_METHOD) : [] ;
        $successUrl = $this->prepareSuccessUrl($transaction);
        $extraParams = [
            'integration_module' => 'shopware6',
            'ps' => !empty($selectedPaymentMethod['id']) ? $selectedPaymentMethod['id'] : 'all',
            'test_mode' => $pluginConfig->getTestMode(),
            'success_url' =>  empty($successUrl) ? $transaction->getReturnUrl() : $successUrl,
            'pingback_url' => $this->preparePingbackUrl(),
        ];
        $configWidgetCode = $pluginConfig->getWidgetCode();
        if (empty($configWidgetCode)) {
            $configWidgetCode = 'pw';
            $extraParams['version'] = '1.2';
        }
        return new Paymentwall_Widget(
            !empty($customer->getCustomerId()) ? $customer->getCustomerId() : $customer->getEmail(),
            $configWidgetCode,
            [
                new Paymentwall_Product(
                    $order->getId(),
                    $order->getAmountTotal(),
                    $salesChannelContext->getCurrency()->getIsoCode(),
                    'Order #' . $order->getOrderNumber(),
                    Paymentwall_Product::TYPE_FIXED
                )
            ],
            $extraParams
        );
    }

    protected function preparePingbackUrl(): ?string
    {
        return $this->router->generate('paymentwall.pingback', [], RouterInterface::ABSOLUTE_URL);
    }

    protected function prepareSuccessUrl(AsyncPaymentTransactionStruct $transaction)
    {
        $orderId = $transaction->getOrder()->getId();
        $this->session->set(self::getPaymentwallSuccessUrlSessionName($orderId), $transaction->getReturnUrl());
        return $this->router->generate('paymentwall.payment.success', ['orderId' => $orderId], RouterInterface::ABSOLUTE_URL);
    }

    public static function getPaymentwallSuccessUrlSessionName($orderId)
    {
        return self::SESSION_NAME_PW_PAYMENT_SUCCESS_URL . $orderId;
    }

}
