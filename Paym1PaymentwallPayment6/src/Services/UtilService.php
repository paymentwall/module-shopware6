<?php

namespace Paym1PaymentwallPayment6\Services;

use Paym1PaymentwallPayment6\Paym1PaymentwallPayment6;
use Paymentwall_Config;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class UtilService
{
    const USER_ID_GEOLOCATION = 'user101';

    private $pluginConfigService;
    private $paymentRepository;

    public function __construct(PluginConfigService $pluginConfigService, EntityRepositoryInterface $paymentRepository)
    {
        $this->pluginConfigService = $pluginConfigService;
        $this->paymentRepository = $paymentRepository;

    }

    public static function getRealClientIP()
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            $headers = $_SERVER;
        }

        //Get the forwarded IP if it exists
        if (array_key_exists('X-Forwarded-For', $headers) && filter_var($headers['X-Forwarded-For'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $the_ip = $headers['X-Forwarded-For'];
        } elseif (array_key_exists('HTTP_X_FORWARDED_FOR', $headers) && filter_var($headers['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $the_ip = $headers['HTTP_X_FORWARDED_FOR'];
        } elseif (array_key_exists('Cf-Connecting-Ip', $headers)) {
            $the_ip = $headers['Cf-Connecting-Ip'];
        } else {
            $the_ip = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        }

        return $the_ip;
    }

    public function getCountryByIp($ip, $salesChannel)
    {
        $pluginConfig = $this->pluginConfigService->getPaymentwallPluginConfigForSalesChannel($salesChannel->getId());
        if (!empty($ip)) {
            $params = array(
                'key' => $pluginConfig->getProjectKey(),
                'uid' => self::USER_ID_GEOLOCATION,
                'user_ip' => $ip
            );

            $url = Paymentwall_Config::API_BASE_URL . '/rest/country?' . http_build_query($params);
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($curl);

            if (curl_error($curl)) {
                return null;
            }
            $response = json_decode($response, true);

            if (!empty($response['code'])) {
                return $response['code'];
            }
        }
        return null;
    }

    /**
     * @param OrderEntity $order
     */
    public function getPaymentwallTransactionByOrderTransactionEntity(OrderTransactionEntity $orderTransaction): ?string
    {
        $customFields = $orderTransaction->getCustomFields();

        return !empty($customFields[Paym1PaymentwallPayment6::ORDER_TRANSACTION_CUSTOM_FIELDS_PAYMENTWALL_TRANSACTION_ID])
            ? $customFields[Paym1PaymentwallPayment6::ORDER_TRANSACTION_CUSTOM_FIELDS_PAYMENTWALL_TRANSACTION_ID] : '';
    }

    public function getPaymentMethodId(Context $context)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('handlerIdentifier', PaymentService::class));

        return $this->paymentRepository->searchIds($criteria, $context)->firstId();
    }

    /**
     * @param $currentOrderPaymentMethodId
     * @param $paymentwallPaymentMethodId
     * @return bool
     */
    public static function isPaymentwallPaymentMethod($currentOrderPaymentMethodId, $paymentwallPaymentMethodId): bool
    {
        return $currentOrderPaymentMethodId == $paymentwallPaymentMethodId;
    }
}