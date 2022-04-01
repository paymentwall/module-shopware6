<?php

namespace Paym1PaymentwallPayment6\Services;

use Paymentwall_Config;

class PaymentProvider
{
    private $pluginConfigService;

    public function __construct(PluginConfigService $pluginConfigService)
    {
        $this->pluginConfigService = $pluginConfigService;
    }

    public function initPaymentwallInstance($saleChannelId = null)
    {
        $pluginConfig = $this->pluginConfigService->getPaymentwallPluginConfigForSalesChannel($saleChannelId);

        $config = [
            'api_type' => Paymentwall_Config::API_GOODS,
            'public_key' => $pluginConfig->getProjectKey(),
            'private_key' => $pluginConfig->getSecretKey()
        ];
        self::initPaymentwallConfig($config);
    }

    public static function initPaymentwallConfig(array $params = [])
    {
        $paymentwallInstance = Paymentwall_Config::getInstance();
        if (!empty($params['public_key'])) {
            $paymentwallInstance->setPublicKey($params['public_key']);
        }
        if (!empty($params['private_key'])) {
            $paymentwallInstance->setPrivateKey($params['private_key']);
        }
        if (!empty($params['api_type'])) {
            $paymentwallInstance->setLocalApiType($params['api_type']);
        }
    }
}