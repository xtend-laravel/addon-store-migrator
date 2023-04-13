<?php

namespace XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Order;

use Illuminate\Support\Collection;
use Lunar\Models\Order;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Processor;

class AddressAssociation extends Processor
{
    protected ?Order $orderModel;

    /**
     * @throws \Lunar\Exceptions\Carts\ShippingAddressMissingException
     */
    public function process(Collection $order): mixed
    {
        /** @var \Xtend\Extensions\Lunar\Models\Order $orderModel */
        $this->orderModel = $order->get('orderModel');

        if ($this->orderModel) {
            $this->setBillingAddress();
            $this->setShippingAddress();

            $order->put('orderModel', $this->orderModel);
        }

        return $order;
    }

    protected function setBillingAddress(): void
    {
        if (! $this->orderModel->cart->billingAddress) {
            return;
        }

        $this->orderModel->billingAddress()->delete();
        $this->orderModel->billingAddress()->create(
            $this->orderModel->cart->billingAddress->toArray()
        );
    }

    protected function setShippingAddress(): void
    {
        if (! $this->orderModel->cart->shippingAddress) {
            return;
        }

        $this->orderModel->shippingAddress()->delete();
        $this->orderModel->shippingAddress()->create(
            $this->orderModel->cart->shippingAddress->toArray()
        );
    }
}
