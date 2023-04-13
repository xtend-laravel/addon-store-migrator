<?php

namespace XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Order;

use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Lunar\Base\OrderModifiers;
use Lunar\Base\OrderReferenceGeneratorInterface;
use Lunar\Models\Cart;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Processor;

class OrderSave extends Processor
{
    protected $referenceGenerator;

    public function __construct(OrderReferenceGeneratorInterface $generator)
    {
        $this->referenceGenerator = $generator;
    }

    public function process(Collection $order): mixed
    {
        $cartModel = $this->lookupCart($order);
        if (! $cartModel) {
            dump('NO CART FOR THIS ORDER "'.$order->get('reference').'" => id_cart:'.$order->get('legacy')->get('id_cart').' >>> '.$order->get('created_at'));

            return $order;
        }

        $cartModel->getManager()->calculate();

        $pipeline = app(Pipeline::class)
            ->send($cartModel)
            ->through(
                app(OrderModifiers::class)->getModifiers()->toArray()
            );

        $cart = $pipeline->via('creating')->thenReturn();

        $placedAtValid = Carbon::parse($order->get('placed_at'))->isCurrentCentury();

        $orderModel = Order::updateOrCreate([
            'legacy_data->id_order' => $order->get('legacy')->get('id_order'),
        ], [
            'cart_id'            => $cart->id,
            'user_id'            => $cart->user_id,
            'channel_id'         => $cart->channel_id,
            'customer_id'        => $this->lookupCustomerId($order),
            'status'             => $this->getCurrentState($order),
            'reference'          => $order->get('reference') ?: 0,
            'customer_reference' => null,
            'sub_total'          => $cart->subTotal->value,
            'total'              => $cart->total->value,
            'discount_total'     => $cart->discountTotal?->value,
            'shipping_total'     => $cart->shippingTotal?->value ?: 0,
            'tax_breakdown'      => $cart->taxBreakdown->map(function ($tax) {
                return [
                    'description' => $tax['description'],
                    'identifier'  => $tax['identifier'],
                    'percentage'  => $tax['amounts']->sum('percentage'),
                    'total'       => $tax['total']->value,
                ];
            })->values(),
            'tax_total'             => $cart->taxTotal->value,
            'currency_code'         => $cart->currency->code,
            'exchange_rate'         => $cart->currency->exchange_rate,
            'compare_currency_code' => Currency::getDefault()?->code,
            'placed_at'             => $placedAtValid ? Carbon::parse($order->get('placed_at'))->toDateTime() : $order->get('created_at'),
            'legacy_data'           => $order->get('legacy'),
        ]);

        $orderModel->setCreatedAt($order->get('created_at'));
        $orderModel->setUpdatedAt($order->get('updated_at'));
        $orderModel->save();

        $cartModel->order()->associate($orderModel);
        $cartModel->save();

        $order->put('orderModel', $orderModel);

        return $order;
    }

    protected function lookupCart(Collection $order): ?Cart
    {
        return Cart::where('legacy_data->id_cart', $order->get('legacy')->get('id_cart'))->first();
    }

    protected function lookupCustomerId(Collection $order): ?int
    {
        return Customer::where('legacy_data->id_customer', $order->get('legacy')->get('id_customer'))->value('id');
    }

    protected function getCurrentState(Collection $order): string
    {
        return match ($order->get('legacy')->get('current_state')) {
            '2' => 'payment-received',
            '3' => 'processing',
            '4' => 'dispatched',
            '5' => 'delivered',
            '6' => 'cancelled',
            '8' => 'error',
            default => config('lunar.orders.draft_status'),
        };
    }

    protected function convertIntPrice(mixed $price): int
    {
        return (int) number_format($price, 2, '', '');
    }
}
