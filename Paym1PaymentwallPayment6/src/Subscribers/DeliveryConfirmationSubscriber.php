<?php

namespace Paym1PaymentwallPayment6\Subscribers;

use Paym1PaymentwallPayment6\Services\OrderService;
use Paym1PaymentwallPayment6\Services\DeliveryConfirmationService;
use Paym1PaymentwallPayment6\Services\UtilService;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DeliveryConfirmationSubscriber implements EventSubscriberInterface
{
    protected $deliveryConfirmationService;
    protected $orderService;
    protected $shippingMethodRepo;
    protected $container;
    protected $utilService;

    public function __construct(
        DeliveryConfirmationService $deliveryConfirmationService,
        OrderService                $orderService,
        EntityRepositoryInterface   $shippingMethodRepo,
        ContainerInterface          $container,
        UtilService                 $utilService
    ) {
        $this->deliveryConfirmationService = $deliveryConfirmationService;
        $this->orderService = $orderService;
        $this->container = $container;
        $this->shippingMethodRepo = $shippingMethodRepo;
        $this->utilService = $utilService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StateMachineTransitionEvent::class => 'updateOrderState',
            OrderEvents::ORDER_DELIVERY_WRITTEN_EVENT => 'updateOrderTrackingCode',
        ];
    }

    /**
     * @param StateMachineTransitionEvent $event
     */
    public function updateOrderState(StateMachineTransitionEvent $event)
    {
        $stateTo = $event->getToPlace()->getTechnicalName();

        if (!$this->isSatisfyEntityName($event) || !$this->isSatisfyState($event, $stateTo)) {
            return;
        }
        $context = $event->getContext();
        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }
        $entityId = $event->getEntityId();
        $entityName = $event->getEntityName();
        if ($entityName == OrderDeliveryDefinition::ENTITY_NAME) {
            $orderDelivery = $this->orderService->getOrderDeliveryByField('id', $entityId, $context);
        } elseif ($entityName == OrderDefinition::ENTITY_NAME) {
            $orderDelivery = $this->orderService->getOrderDeliveryByField('orderId', $entityId, $context);
        }
        if (empty($orderDelivery)) {
            return;
        }

        $currentOrderPaymentMethodId = $this->getCurrentOrderPaymentMethodId($orderDelivery->getOrderId(), $context);
        $paymentwallPaymentMethodId = $this->utilService->getPaymentMethodId($context);
        if (!UtilService::isPaymentwallPaymentMethod($currentOrderPaymentMethodId, $paymentwallPaymentMethodId)) {
            return;
        }

        $trackingCode = $orderDelivery->getTrackingCodes() ? $orderDelivery->getTrackingCodes()[0] : null;
        $shippingMethodName = $this->getShippingMethodName($orderDelivery->getOrderId(), $event->getContext());

        $order = $this->orderService->getOrderById($orderDelivery->getOrderId(), $context);
        if (empty($order)) {
            return;
        }

        $this->sendDeliveryConfirmation(
            $orderDelivery->getOrderId(),
            $event->getContext(),
            $this->setDeliveryConfirmationStatus($stateTo),
            $this->setTrackingData($trackingCode, $shippingMethodName),
            OrderService::getSalesChannelIdFromOrder($order)
        );
    }

    /**
     * @param $entityId
     * @param Context $context
     * @return string|void
     */
    protected function getCurrentOrderPaymentMethodId($entityId, Context $context)
    {
        $orderTransaction = $this->orderService->getOrderTransactionByField('orderId', $entityId, $context);
        if (empty($orderTransaction)) {
            return;
        }

        return $orderTransaction->getPaymentMethodId();
    }

    /**
     * @param $event
     * @param $stateTo
     * @return bool
     */
    protected function isSatisfyState($event, $stateTo)
    {
        if ( ($event->getEntityName() == OrderDeliveryDefinition::ENTITY_NAME && $stateTo != OrderDeliveryStates::STATE_SHIPPED )
            || ($event->getEntityName() == OrderDefinition::ENTITY_NAME && ($stateTo != OrderStates::STATE_COMPLETED && OrderStates::STATE_IN_PROGRESS != $stateTo))) {
            return false;
        }

        return true;
    }

    /**
     * @param $event
     * @return bool
     */
    protected function isSatisfyEntityName($event)
    {
        if ($event->getEntityName() !== OrderDeliveryDefinition::ENTITY_NAME
            && $event->getEntityName() !== OrderDefinition::ENTITY_NAME) {
            return false;
        }

        return true;
    }

    /**
     * @param $stateTo
     * @return string
     */
    private function setDeliveryConfirmationStatus($stateTo) : string
    {
        if ($stateTo == OrderDeliveryStates::STATE_SHIPPED) {
            return DeliveryConfirmationService::STATUS_ORDER_SHIPPED;
        }
        if ($stateTo == OrderStates::STATE_COMPLETED) {
            return DeliveryConfirmationService::STATUS_DELIVERED;
        }
        if ($stateTo == OrderStates::STATE_IN_PROGRESS) {
            return DeliveryConfirmationService::STATUS_ORDER_PREPARED;
        }

        return '';
    }

    /**
     * @param $trackingCode
     * @param $shippingMethodName
     * @return array
     */
    private function setTrackingData($trackingCode, $shippingMethodName)
    {
        return [
            'carrier_tracking_id' => $trackingCode,
            'carrier_type' => $shippingMethodName
        ];
    }

    public function updateOrderTrackingCode(EntityWrittenEvent $event)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
            return;
        }
        
        $payload = $event->getPayloads()[0];
        if (empty($payload['trackingCodes'])) {
            return;
        }

        $trackingCode = array_pop($payload['trackingCodes']);
        $shippingMethodName = $this->getShippingMethodName($payload['orderId'], $event->getContext());

        $order = $this->orderService->getOrderById($payload['orderId'], $event->getContext());
        if (empty($order)) {
            return;
        }

        $this->sendDeliveryConfirmation(
            $payload['orderId'],
            $event->getContext(),
            DeliveryConfirmationService::STATUS_ORDER_SHIPPED,
            $this->setTrackingData($trackingCode, $shippingMethodName),
            OrderService::getSalesChannelIdFromOrder($order)
        );

    }

    protected function sendDeliveryConfirmation($orderId, $context, $status, $trackingData, $salesChannelId)
    {
        $order = $this->orderService->getOrderById($orderId, $context);
        if (empty($order)) {
            return;
        }

        $preparedData = $this->deliveryConfirmationService->prepareDeliveryData($order, $status, $context, $trackingData);
        $this->deliveryConfirmationService->sendDeliveryData($preparedData, $salesChannelId);
    }

    protected function getShippingMethodName($orderId, $context): ?string
    {
        $orderDelivery = $this->orderService->getOrderDeliveryByField('orderId', $orderId, $context);
        if (empty($orderDelivery)) {
            return null;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $orderDelivery->getShippingMethodId()));
        $shipping =  $this->shippingMethodRepo->search($criteria, $context)->first();

        return $shipping->getName();
    }
}
