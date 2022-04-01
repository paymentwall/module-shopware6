<?php

namespace Paym1PaymentwallPayment6\Services;

use Paym1PaymentwallPayment6\Paym1PaymentwallPayment6;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class OrderService
{
    protected $orderRepository;
    protected $orderTransactionRepository;
    protected $orderDeliveryRepository;

    public function __construct(
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $orderTransactionRepository,
        EntityRepositoryInterface $orderDeliveryRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->orderDeliveryRepository = $orderDeliveryRepository;
    }

    public function getOrderById($id, Context $context): ?OrderEntity
    {
        if (empty($id) || empty($context)) {
            return null;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $id));
        return $this->orderRepository->search($criteria, $context)->first();

    }

    public function getOrderTransactionByField($field, $value, Context $context): ?OrderTransactionEntity
    {
        if (empty($field) && empty($value)) {
            return null;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter($field, $value));
        return $this->orderTransactionRepository->search($criteria, $context)->first();
    }

    public function addPaymentwallTransactionId($ref, $transactionId, Context $context)
    {
        $customFields = [
            Paym1PaymentwallPayment6::ORDER_TRANSACTION_CUSTOM_FIELDS_PAYMENTWALL_TRANSACTION_ID => $ref,
        ];

        $data = [
            'id' => $transactionId,
            'customFields' => $customFields,
        ];

        $this->orderTransactionRepository->update([$data], $context);
    }

    public function getOrderDeliveryByField($field, $value, Context $context): ?OrderDeliveryEntity
    {
        if (empty($field) || empty($value)) {
            return null;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter($field, $value));
        return $this->orderDeliveryRepository->search($criteria, $context)->first();
    }

    public static function getSalesChannelIdFromOrder($order)
    {
        return $order->getSalesChannelId();
    }
}