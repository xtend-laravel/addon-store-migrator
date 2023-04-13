<?php

namespace XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Cart;

use Illuminate\Support\Collection;
use Lunar\Models\Address;
use Lunar\Models\Cart;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Processor;

class AddressAssociation extends Processor
{
    protected ?Cart $cartModel;

    public function process(Collection $cart): mixed
    {
        /** @var \Lunar\Models\Cart $cartModel */
        $this->cartModel = $cart->get('cartModel');

        $this->setBillingAddress($cart);
        $this->setShippingAddress($cart);

        $cart->put('cartModel', $this->cartModel);

        return $cart;
    }

    protected function setBillingAddress(Collection $cart): void
    {
        $billingAddress = Address::where('legacy_data->id_address', $cart->get('legacy')->get('id_address_invoice'))->first();
        if (! $billingAddress) {
            return;
        }

        $this->cartModel->getManager()->setBillingAddress($billingAddress);
    }

    protected function setShippingAddress(Collection $cart): void
    {
        $shippingAddress = Address::where('legacy_data->id_address', $cart->get('legacy')->get('id_address_delivery'))->first();
        if (! $shippingAddress) {
            return;
        }

        $this->cartModel->getManager()->setShippingAddress($shippingAddress);
    }
}
