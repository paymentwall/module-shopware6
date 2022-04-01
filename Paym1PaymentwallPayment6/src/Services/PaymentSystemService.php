<?php

namespace Paym1PaymentwallPayment6\Services;

use Paymentwall_Config;
use Paymentwall_Signature_Widget;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class PaymentSystemService
{
    private $utilService;
    private $pluginConfigService;
    private $paymentProvider;

    public function __construct(
        UtilService $utilService,
        PluginConfigService $pluginConfigService,
        PaymentProvider $paymentProvider
    ) {
        $this->utilService = $utilService;
        $this->pluginConfigService = $pluginConfigService;
        $this->paymentProvider = $paymentProvider;
    }

    /**
     * @param SalesChannelEntity $salesChannel
     * @return array
     */
    public function getLocalPaymentMethods(SalesChannelEntity $salesChannel): ?array
    {
        $ip = UtilService::getRealClientIP();
        $userCountry = $this->utilService->getCountryByIp($ip, $salesChannel);
        $response = $this->getPaymentMethodFromApi($userCountry, $salesChannel);
        return  $this->prepareLocalPayment($response);
    }

    /**
     * @param $userCountry
     * @param  SalesChannelEntity $salesChannel
     * @return mixed|null
     */
    protected function getPaymentMethodFromApi($userCountry, $salesChannel = null)
    {
        if (empty($userCountry)) {
            return null;
        }

        $pluginConfig = $this->pluginConfigService->getPaymentwallPluginConfigForSalesChannel($salesChannel->getId());
        $this->paymentProvider->initPaymentwallInstance($salesChannel->getId());
        $params = array(
            'key' => $pluginConfig->getProjectKey(),
            'country_code' => $userCountry,
            'sign_version' => 3,
            'currencyCode' =>  $salesChannel->getCurrency()->getIsoCode(),
        );

        $params['sign'] = (new Paymentwall_Signature_Widget())->calculate(
            $params,
            $params['sign_version']
        );

        $url = Paymentwall_Config::API_BASE_URL . '/payment-systems/?' . http_build_query($params);
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);
        if (curl_error($curl)) {
            return null;
        }

        return json_decode($response, true);
    }

    protected function prepareLocalPayment($payments): ?array
    {
        $methods = [];
        if (!empty($payments)) {
            foreach ($payments as $payment) {
                if (!empty($payment['id']) && !empty($payment['name'])) {
                    $methods[] = [
                        'id' => $payment['id'],
                        'name' => $payment['name'],
                        'img_url' => !empty($payment['img_url']) ? $payment['img_url'] : ''
                    ];
                }
            }
        }
        return $methods;
    }
}