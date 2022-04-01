<?php
namespace Paym1PaymentwallPayment6\Subscribers;

use Paym1PaymentwallPayment6\Components\Extension\PaymentwallDataExtension;
use Paym1PaymentwallPayment6\Controllers\PaymentSystemController;
use Paym1PaymentwallPayment6\Services\PluginConfigService;
use Paym1PaymentwallPayment6\Services\UtilService;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Paym1PaymentwallPayment6\Services\PaymentSystemService;
use Symfony\Component\HttpFoundation\Session\Session;

class PaymentSystemSubscriber implements EventSubscriberInterface
{
    private $paymentSystemService;
    private $utilService;
    private $session;
    private $pluginConfigService;

    public function __construct(
        PaymentSystemService $paymentSystemService,
        UtilService $utilService,
        Session $session,
        PluginConfigService $pluginConfigService
    ) {
        $this->paymentSystemService = $paymentSystemService;
        $this->utilService = $utilService;
        $this->session = $session;
        $this->pluginConfigService = $pluginConfigService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class  => 'loadPaymentMethods',
        ];
    }

    public function loadPaymentMethods(PageLoadedEvent $event)
    {
        if (!($event instanceof CheckoutConfirmPageLoadedEvent)) {
            return;
        }
        $paymentMethodCollection = $event->getPage()->getPaymentMethods();

        $salesChannelContext = $event->getSalesChannelContext();
        $salesChannel = $salesChannelContext->getSalesChannel();

        $paymentwallPaymentMethodId = $this->utilService->getPaymentMethodId($salesChannelContext->getContext());

        if (!$paymentMethodCollection->has($paymentwallPaymentMethodId)) {
            $this->session->remove(PaymentSystemController::SELECTED_PAYMENT_METHOD);
            return;
        }
        $pluginConfig = $this->pluginConfigService->getPaymentwallPluginConfigForSalesChannel($salesChannel->getId());

        $paymentMethods = $this->paymentSystemService->getLocalPaymentMethods($salesChannel);
        $selectedPaymentMethod = ($this->session->has(PaymentSystemController::SELECTED_PAYMENT_METHOD))
            ? $this->session->get(PaymentSystemController::SELECTED_PAYMENT_METHOD)
            : '';

        $paymentwalData = new PaymentwallDataExtension([
            'paymentwallPaymentId' => $paymentwallPaymentMethodId,
            'selectedPaymentMethod' => $selectedPaymentMethod,
            'paymentMethods' => $paymentMethods
        ]);

        $event->getPage()->addExtension(PaymentwallDataExtension::PAYMENTWALL_DATA_EXTENSION_NAME, $paymentwalData);
    }
}
