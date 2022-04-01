<?php

namespace Paym1PaymentwallPayment6\Services;

use Paymentwall_Config;
use Paymentwall_GenerericApiObject;
use Paym1PaymentwallPayment6\Paym1PaymentwallPayment6;
use Paym1PaymentwallPayment6\Services\OrderService;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class DeliveryConfirmationService
{
    const PRODUCT_PHYSICAL = 'physical';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_ORDER_PLACED = 'order_placed';
    const STATUS_ORDER_SHIPPED = 'order_shipped';
    const STATUS_ORDER_PREPARED = 'package_prepared';

    protected $pluginConfigService;
    protected $orderService;
    protected $systemConfigService;
    protected $paymentProvider;

    public function __construct(
        PluginConfigService $pluginConfigService,
        OrderService        $orderService,
        SystemConfigService $systemConfigService,
        PaymentProvider     $paymentProvider
    ) {
        $this->pluginConfigService = $pluginConfigService;
        $this->orderService = $orderService;
        $this->systemConfigService = $systemConfigService;
        $this->paymentProvider = $paymentProvider;
    }

    /**
     * @param OrderEntity $order
     * @param $status
     * @param $salesChanel
     * @param null $trackingData
     * @return array|null
     */
    public function prepareDeliveryData(OrderEntity $order, string $status, $context, $trackingData = null): array
    {
        $pluginConfig = $this->pluginConfigService->getPaymentwallPluginConfigForSalesChannel($order->getSalesChannelId());
        $orderDelivery = $this->orderService->getOrderDeliveryByField('orderId', $order->getId(), $context);
        $orderAddress = $orderDelivery->getShippingOrderAddress();

        $data = [
            'payment_id' => $this->getPaymentwallTransaction($order, $context),
            'merchant_reference_id' => $order->getId(),
            'type' => self::PRODUCT_PHYSICAL,
            'status' => $status,
            'estimated_delivery_datetime' => date('Y/m/d H:i:s'),
            'estimated_update_datetime' => date('Y/m/d H:i:s'),
            'refundable' => 'yes',
            'details' => 'Order status has been updated on ' . date('Y/m/d H:i:s'),
            'product_description' => '',
            'shipping_address[country]' => !empty($orderAddress->getCountry()) ? $orderAddress->getCountry()->getIso() : 'N/A',
            'shipping_address[city]' =>   $orderAddress->getCity(),
            'shipping_address[zip]' => $orderAddress->getZipcode(),
            'shipping_address[state]' => !empty($orderAddress->getCountryState()) ? $orderAddress->getCountryState() : 'N/A',
            'shipping_address[street]' => $orderAddress->getStreet(),
            'shipping_address[phone]' => !empty($orderAddress->getPhoneNumber()) ? $orderAddress->getPhoneNumber() : 'N/A',
            'shipping_address[firstname]' => $orderAddress->getFirstName(),
            'shipping_address[lastname]' =>  $orderAddress->getLastName(),
            'shipping_address[email]' => $order->getOrderCustomer()->getEmail(),
            'reason' => 'none',
            'attachments' => null,
            'is_test' => $pluginConfig->getTestMode(),
        ];

        if (!empty($trackingData)) {
            return array_merge($data, $trackingData);
        }
        return $data;
    }

    public function sendDeliveryData($dataPrepared, $salesChannelId)
    {
        if (empty($dataPrepared)) {
            return;
        }
        $this->paymentProvider->initPaymentwallInstance($salesChannelId);

        $delivery = new Paymentwall_GenerericApiObject('delivery');
        $delivery->post($dataPrepared);
    }

    /**
     * @param OrderEntity $order
     */
    protected function getPaymentwallTransaction($order, $context): ?string
    {
        $orderTransaction = $this->orderService->getOrderTransactionByField('orderId', $order->getId(), $context);
        $customFields = $orderTransaction->getCustomFields();

        return !empty($customFields[Paym1PaymentwallPayment6::ORDER_TRANSACTION_CUSTOM_FIELDS_PAYMENTWALL_TRANSACTION_ID])
        ? $customFields[Paym1PaymentwallPayment6::ORDER_TRANSACTION_CUSTOM_FIELDS_PAYMENTWALL_TRANSACTION_ID] : '';
    }
}