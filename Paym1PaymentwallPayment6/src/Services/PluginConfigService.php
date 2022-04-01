<?php

namespace Paym1PaymentwallPayment6\Services;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\Client;
use Paymentwall_Config;
use Paymentwall_Signature_Widget;
use Paymentwall_Signature_Abstract;


class PluginConfigService
{
    private const CONFIG_KEY = 'Paym1PaymentwallPayment6.config';
    private const C_KEY = 'ri@45k($LKd1'; // random string
    private const ERROR_AUTHENTICATION_FAIL = 'Authentication failed.';

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * @param string|null $salesChannelId
     * @return PluginConfig
     */
    public function getPaymentwallPluginConfigForSalesChannel(?string $salesChannelId = null): PluginConfig
    {
        $rawConfig = $this->systemConfigService->get(self::CONFIG_KEY, $salesChannelId);

        return new PluginConfig($rawConfig ?? []);
    }

    /**
     * @param array $credentials
     * @return bool
     */
    public function  validateCredentials(array $credentials): bool
    {
        if (empty($credentials['public_key']) || empty($credentials['private_key'])) {
            return false;
        }

        try {
            $params['query'] = $this->prepareRequestData($credentials);
            $client = new Client(['base_uri' => Paymentwall_Config::API_BASE_URL . '/coupon']);
            $response = $client->request('GET', '', $params);
            $responseBody = json_decode($response->getBody()->getContents(), true);

            if ($response->getStatusCode() == Response::HTTP_OK && $responseBody['error'] != self::ERROR_AUTHENTICATION_FAIL) {
                return true;
            }
        } catch (\Exception $e) {}

        return false;
    }

    /**
     * @param array $credentials
     * @return array
     */
    private function prepareRequestData(array $credentials): array
    {
        PaymentProvider::initPaymentwallConfig($credentials);
        $params = [
            'key' => !empty($credentials['public_key']) ? $credentials['public_key'] : '' ,
            'code' => self::C_KEY,
            'timestamp' => time(),
            'sign_version' => Paymentwall_Signature_Abstract::DEFAULT_VERSION,
        ];
        $params['sign'] = (new Paymentwall_Signature_Widget())->calculate(
            $params,
            $params['sign_version']
        );
        return $params;
    }
}
