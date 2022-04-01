<?php

namespace Paym1PaymentwallPayment6\Subscribers;

use Paym1PaymentwallPayment6\Services\OrderService;
use Paym1PaymentwallPayment6\Services\PluginConfigService;
use Paym1PaymentwallPayment6\Services\RefundService;
use Paym1PaymentwallPayment6\Services\UtilService;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RefundSubscriber implements EventSubscriberInterface
{
    protected $refundService;
    protected $orderService;
    protected $utilService;
    protected $pluginConfigService;
    protected $salesChannelDomainRepository;

    public function __construct(
        RefundService             $refundService,
        OrderService              $orderService,
        UtilService               $utilService,
        PluginConfigService       $pluginConfigService,
        EntityRepositoryInterface $salesChannelDomainRepository
    ) {
        $this->refundService = $refundService;
        $this->orderService = $orderService;
        $this->utilService = $utilService;
        $this->pluginConfigService = $pluginConfigService;
        $this->salesChannelDomainRepository = $salesChannelDomainRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'state_machine.order.state_changed'  => 'orderStateChange',
        ];
    }

    public function orderStateChange(StateMachineStateChangeEvent $event)
    {
        $context = $event->getContext();
        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $to = $event->getNextState()->getTechnicalName();
        if ($to !== OrderStates::STATE_CANCELLED) {
            return;
        }

        $orderTransactionId = $event->getTransition()->getEntityId();
        $orderTransactionRepository = $this->orderService->getOrderTransactionByField('orderId', $orderTransactionId, $context);

        $order = $this->orderService->getOrderById($orderTransactionRepository->getOrderId(), $context);
        if (empty($order)) {
            return;
        }

        $salesChannelId = OrderService::getSalesChannelIdFromOrder($order);

        $pluginConfig = $this->pluginConfigService->getPaymentwallPluginConfigForSalesChannel($salesChannelId);
        if (!$pluginConfig->isRefundEnabled()) {
            return;
        }
        $domain = $this->getDomain($salesChannelId, $context);
        $ref = $this->utilService->getPaymentwallTransactionByOrderTransactionEntity($orderTransactionRepository);

        $preparedData = $this->refundService->prepareData($ref, $salesChannelId, $domain);
        $this->refundService->sendCancellation($preparedData, $salesChannelId);
    }

    protected function getDomain($salesChannelId, $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        /* @var $salesChannelDomain SalesChannelDomainEntity */
        $salesChannelDomain = $this->salesChannelDomainRepository->search($criteria, $context)->first();
        return $salesChannelDomain->getUrl();
    }
}
