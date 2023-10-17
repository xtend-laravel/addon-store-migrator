<?php

namespace XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Cart;

use Illuminate\Support\Collection;
use Lunar\Models\ProductVariant;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Processor;
use XtendLunar\Addons\StoreMigrator\Models\StoreMigratorResourceModel;

class CartLinesSave extends Processor
{
    public function process(Collection $cart, ?StoreMigratorResourceModel $resourceModel = null): mixed
    {
        /** @var \Xtend\Extensions\Lunar\Models\Cart $cartModel */
        $cartModel = $cart->get('cartModel');

        collect($cart->get('cartLines') ?? [])->each(function ($cartLine) use ($cartModel) {
            if ($purchasableId = $this->lookupPurchasableId($cartLine)) {

                $cartModel->lines()->updateOrCreate([
                    'purchasable_type' => ProductVariant::class,
                    'purchasable_id' => $purchasableId,
                ], [
                    'quantity' => $cartLine['quantity'] ?? 1,
                ]);
            }
        });

        return $cart;
    }

    protected function lookupPurchasableId(array $cartLine): ?int
    {
        return $cartLine['id_product_attribute'] > 0
            ? ProductVariant::where('legacy_data->id', $cartLine['id_product_attribute'])->first()?->id
            : ProductVariant::where('legacy_data->id_product', $cartLine['id_product'])->first()?->id;
    }
}
