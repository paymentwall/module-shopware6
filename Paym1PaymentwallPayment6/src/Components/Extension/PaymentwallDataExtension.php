<?php

namespace Paym1PaymentwallPayment6\Components\Extension;

use Shopware\Core\Framework\Struct\Struct;

class PaymentwallDataExtension extends Struct
{
    const PAYMENTWALL_DATA_EXTENSION_NAME = 'paymentwall_data_extension';

    protected $paymentwallPaymentId;
    protected $selectedPaymentMethod;
    protected $paymentMethods;

    public function __construct(?array $data)
    {
        if (empty($data)) {
            return;
        }

        $this->assign($data);
    }

    public function getPaymentwallPaymentId()
    {
        return $this->paymentwallPaymentId;
    }

    public function getSelectedPaymentMethod()
    {
        return $this->selectedPaymentMethod;
    }

    public function getPaymentMethods()
    {
        return $this->paymentMethods;
    }
}
