<?php

namespace XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Cart;

use App\Models\User;
use Illuminate\Support\Collection;
use Lunar\Models\Cart;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Processor;
use XtendLunar\Addons\StoreMigrator\Models\StoreMigratorResourceModel;

class CartSave extends Processor
{
    public function process(Collection $cart, ?StoreMigratorResourceModel $resourceModel = null): mixed
    {
        $cartModel = Cart::updateOrCreate([
            'legacy_data->id_cart' => $cart->get('legacy')->get('id_cart'),
        ], [
            //'customer_id' => $this->lookupCustomer($cart)?->id,
            'user_id' => $this->lookupCustomerDefaultUser($cart)?->id,
            'currency_id' => Currency::getDefault()->id,
            'channel_id' => Channel::getDefault()->id,
            'legacy_data' => $cart->get('legacy'),
        ]);

        $cartModel->setCreatedAt($cart->get('created_at'));
        $cartModel->setUpdatedAt($cart->get('updated_at'));
        $cartModel->save();

        $cart->put('cartModel', $cartModel);

        $resourceModel->destination_model_type = Cart::class;
        $resourceModel->destination_model_id = $cartModel->id;
        $resourceModel->status = 'processing';
        $resourceModel->save();

        return $cart;
    }

    protected function lookupCustomer(Collection $cart): ?Customer
    {
        return Customer::where('legacy_data->id_customer', $cart->get('legacy')->get('id_customer'))->first();
    }

    protected function lookupCustomerDefaultUser(Collection $cart): ?User
    {
        return $this->lookupCustomer($cart)?->users?->first();
    }
}
