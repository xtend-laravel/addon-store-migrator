<?php

namespace XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Order;

use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Collection;
use Lunar\Base\OrderModifiers;
use Lunar\Models\ProductVariant;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Processor;

class OrderLinesSave extends Processor
{
    public function process(Collection $order): mixed
    {
        /** @var \Xtend\Extensions\Lunar\Models\Order $orderModel */
        $orderModel = $order->get('orderModel');
        if (! $orderModel) {
            return $order;
        }

        /** @var \Lunar\Models\Cart $cart */
        $cart = $orderModel->cart;
        $cart->getManager()->calculate();

        $pipeline = app(Pipeline::class)
            ->send($cart)
            ->through(
                app(OrderModifiers::class)->getModifiers()->toArray()
            );

        $cart = $pipeline->via('creating')->thenReturn();

        $orderLines = $cart->lines->map(function ($line) {
            return [
                'purchasable_type' => $line->purchasable_type,
                'purchasable_id'   => $line->purchasable_id,
                'type'             => $line->purchasable->getType(),
                'description'      => $line->purchasable->getDescription(),
                'option'           => $line->purchasable->getOption(),
                'identifier'       => $line->purchasable->getIdentifier() ?? '--',
                'unit_price'       => $line->unitPrice->value,
                'unit_quantity'    => $line->purchasable->getUnitQuantity(),
                'quantity'         => $line->quantity,
                'sub_total'        => $line->subTotal->value,
                'discount_total'   => $line->discountTotal?->value,
                'tax_breakdown'    => $line->taxBreakdown->amounts->map(function ($amount) {
                    return [
                        'description' => $amount->description,
                        'identifier' => $amount->identifier ?? '--',
                        'percentage' => $amount->percentage,
                        'total'      => $amount->price->value,
                    ];
                })->values(),
                'tax_total' => $line->taxAmount->value,
                'total'     => $line->total->value,
                'notes'     => null,
                'meta'      => $line->meta,
            ];
        });

        $orderModel->lines()->delete();
        $orderModel->lines()->createMany(
            $orderLines->toArray()
        );

        $order->put('orderModel', $orderModel);

        // collect($order->get('orderLines') ?? [])->each(function ($orderLine) use ($orderModel) {
        //     if ($purchasableId = $this->lookupPurchasableId($orderLine)) {
        //         $orderModel->lines()->updateOrCreate([
        //             'purchasable_type' => ProductVariant::class,
        //             'purchasable_id' => $purchasableId,
        //             'type' => 'physical',
        //         ], [
        //             'description' => $orderLine['description'] ?? '',
        //             'quantity' => $orderLine['quantity'] ?? 1,
        //             'identifier' => $orderLine['identifier'] ?? 0,
        //             'unit_price' => $this->convertIntPrice($orderLine['unit_price'] ?? 0),
        //             'sub_total' => $this->convertIntPrice($orderLine['sub_total'] ?? 0) ,
        //             'total' => $this->convertIntPrice($orderLine['total'] ?? 0) ,
        //             'tax_total' => $this->convertIntPrice($orderLine['tax_total'] ?? 0),
        //             'tax_breakdown' => [],
        //         ]);
        //     }
        // });

        return $order;
    }

    protected function lookupPurchasableId(Collection $orderLine): ?int
    {
        $productId = $orderLine->get('legacy')->get('product_id');
        $ipa = $orderLine->get('legacy')->get('product_attribute_id');

        return $ipa > 0
            ? ProductVariant::where('legacy_data->id', $ipa)->first()?->id
            : ProductVariant::where('legacy_data->id_product', $productId)->first()?->id;
    }

    protected function convertIntPrice(mixed $price): int
    {
        return (int) number_format($price, 2, '', '');
    }
}
