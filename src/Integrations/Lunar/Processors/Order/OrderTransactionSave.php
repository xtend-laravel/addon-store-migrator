<?php

namespace XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Order;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Processor;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\PrestashopConnector;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\OrderPaymentsRequest;
use XtendLunar\Addons\StoreMigrator\Models\StoreMigratorResourceModel;

class OrderTransactionSave extends Processor
{
    public function process(Collection $order, ?StoreMigratorResourceModel $resourceModel = null): mixed
    {
        /** @var \Xtend\Extensions\Lunar\Models\Order $orderModel */
        $orderModel = $order->get('orderModel');
        if (! $orderModel) {
            return $order;
        }

        $transaction = $this->lookupTransaction($orderModel->reference);
        if (! $transaction) {
            dump('No transaction found for order: '.$orderModel->reference);

            return $order;
        }

        // Make sure we don't have any existing transactions for this order
        $orderModel->transactions()->delete();
        $orderModel->transactions()->create([
            'success' => Str::of($orderModel->status)->contains([
                'processing',
                'payment-received',
                'dispatched',
                'delivered',
            ]),
            'status' => 'paid',
            'driver' => $orderModel->legacy_data->get('module'),
            'amount' => $this->convertIntPrice($transaction['amount']),
            'reference' => $transaction['transaction_id'],
            'card_type' => $transaction['card_brand'] ?? '--',
            'last_four' => $transaction['card_number'] ? substr($transaction['card_number'], -4) : null,
            'created_at' => $transaction['date_add'],
            'meta' => $transaction,
        ]);

        $order->put('orderModel', $orderModel);

        return $order;
    }

    protected function lookupTransaction(string $orderReference): array
    {
        $request = new OrderPaymentsRequest;
        $request->query()->merge([
            'filter[order_reference]' => $orderReference,
            'display' => 'full',
        ]);

        $response = PrestashopConnector::make()->send($request);
        return $response->json('order_payments')[0] ?? [];
    }

    protected function convertIntPrice(mixed $price): int
    {
        return (int) number_format($price, 2, '', '');
    }
}
