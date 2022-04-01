<?php

namespace Paym1PaymentwallPayment6\Services;

use Paymentwall_Signature_Widget;
use Paymentwall_GenerericApiObject;
use Paymentwall_Config;

class RefundService
{
    const TYPE_FULL_REFUND = 1;

    protected $pluginConfigService;
    protected $paymentProvider;

    public function __construct(
        PluginConfigService $pluginConfigService,
        PaymentProvider $paymentProvider
    ) {
        $this->pluginConfigService = $pluginConfigService;
        $this->paymentProvider = $paymentProvider;
    }

    public function prepareData($ref, $salesChannelId, $domain): array
    {
        $this->paymentProvider->initPaymentwallInstance($salesChannelId);
        $pluginConfig = $this->pluginConfigService->getPaymentwallPluginConfigForSalesChannel($salesChannelId);

        $params =  [
            'key' => $pluginConfig->getProjectKey(),
            'ref' => $ref,
            'sign_version' => 3,
            'uid' => '',
            'type' => self::TYPE_FULL_REFUND,
            'message' => 'Shopware: website ' . $domain . ' request full refund',
            'test_mode' => (int)$pluginConfig->getTestMode(),
        ];

            $params['sign'] = (new Paymentwall_Signature_Widget())->calculate($params, $params['sign_version']);
        return $params;
    }

    public function sendCancellation($dataPrepared, $salesChannelId)
    {
        $pluginConfig = $this->pluginConfigService->getPaymentwallPluginConfigForSalesChannel($salesChannelId);
        Paymentwall_Config::getInstance()->set(array(
            'api_base_url' => 'https://api.paymentwall.com/developers/api',
            'private_key' => $pluginConfig->getSecretKey()
        ));
        $delivery = new Paymentwall_GenerericApiObject('ticket');
        $delivery->post($dataPrepared);
    }
}
