<?php

namespace XtendLunar\Addons\StoreMigrator\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use XtendLunar\Addons\StoreMigrator\Concerns\InteractsWithPipeline;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Order\AddressAssociation;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Order\OrderLinesSave;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Order\OrderSave;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Order\OrderTransactionSave;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Transformers\FieldLegacyTransformer;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Transformers\FieldMapKeyTransformer;

use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\PrestashopConnector;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\OrdersRequest;

class OrderSync implements ShouldQueue
{
    use Dispatchable, InteractsWithPipeline, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var Collection
     */
    protected Collection $order;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected int $orderId
    ) {}

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->prepare();

        if ($this->order->has('orderLines')) {
            Model::withoutEvents(function () {
                DB::transaction(fn () => $this->sync());
            });
        }
    }

    protected function prepare()
    {
        $this->prepareOrder();
    }

    protected function prepareOrder(): void
    {
        $request = new OrdersRequest(orderId: $this->orderId);
        $request->query()->add('display', 'full');
        $response = PrestashopConnector::make()->send($request);

        $orderLines = $response->json('orders')[0]['associations']['order_rows'] ?? [];

        $this->order = $this->prepareThroughPipeline(
            passable: PrestashopConnector::mapFields(
                collect($response->json('orders')[0]), 'orders', 'lunar'
            ),
            pipes: [
                FieldMapKeyTransformer::class,
                FieldLegacyTransformer::class,
            ],
            method: 'transform',
        );

        if ($orderLines) {
            $this->order->put('orderLines', $this->prepareOrderLines($orderLines));
        }
    }

    protected function prepareOrderLines(array $orderLines): Collection
    {
        return collect($orderLines)->map(function ($orderLine) {
            return $this->prepareThroughPipeline(
                passable: PrestashopConnector::mapFields(collect($orderLine), 'order_details', 'lunar'),
                pipes: [
                    FieldMapKeyTransformer::class,
                    FieldLegacyTransformer::class,
                ],
                method: 'transform',
            );
        });
    }

    protected function sync(): void
    {
        $this->prepareThroughPipeline(
            passable: $this->order,
            pipes: [
                OrderSave::class,
                OrderLinesSave::class,
                AddressAssociation::class,
                OrderTransactionSave::class,
            ],
        );
    }
}
