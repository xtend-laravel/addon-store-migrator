<?php

namespace XtendLunar\Addons\StoreMigrator\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use XtendLunar\Addons\StoreMigrator\Concerns\InteractsWithPipeline;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Cart\AddressAssociation;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Cart\CartLinesSave;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Cart\CartSave;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Transformers\FieldLegacyTransformer;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Transformers\FieldMapKeyTransformer;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\FieldMapper;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\PrestashopConnector;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\CartsRequest;

class CartSync implements ShouldQueue
{
    use Dispatchable, InteractsWithPipeline, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var Collection
     */
    protected Collection $cart;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected int $cartId
    ) {}

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $cartLines = $this->prepareCart();

        if ($cartLines) {
            DB::transaction(fn () => $this->sync());
        }
    }

    protected function prepareCart(): array
    {
        $request = new CartsRequest(cartId: $this->cartId);
        $request->query()->add('display', 'full');
        $response = PrestashopConnector::make()->send($request);
        $cartLines = $response->json('carts')[0]['associations']['cart_rows'] ?? [];

        if (! $cartLines) {
            return [];
        }

        $this->cart = $this->prepareThroughPipeline(
            passable: PrestashopConnector::mapFields(
                collect($response->json('carts')[0]), 'carts', 'lunar'
            ),
            pipes: [
                FieldMapKeyTransformer::class,
                FieldLegacyTransformer::class,
            ],
            method: 'transform',
        );

        $this->cart->put('cartLines', $cartLines);

        return $cartLines;
    }

    protected function sync(): void
    {
        $this->cart->put('legacy_lookup', FieldMapper::getLegacyFieldsLookup('lunar', 'carts'));

        $this->prepareThroughPipeline(
            passable: $this->cart,
            pipes: [
                CartSave::class,
                CartLinesSave::class,
                AddressAssociation::class,
            ],
        );
    }
}
