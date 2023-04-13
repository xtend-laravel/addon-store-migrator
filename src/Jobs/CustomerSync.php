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
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Customer\AddressesSave;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Customer\CustomerGroupSave;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Customer\CustomerSave;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Transformers\FieldLegacyTransformer;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Transformers\FieldMapKeyTransformer;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Transformers\TranslationTransformer;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\PrestashopConnector;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\AddressesRequest;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\CustomersRequest;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\GroupsRequest;

class CustomerSync implements ShouldQueue
{
    use Dispatchable, InteractsWithPipeline, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var Collection
     */
    protected Collection $customer;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected int $customerId
    ) {}

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->prepare();
        DB::transaction(fn () => $this->sync());
    }

    protected function prepare()
    {
        $this->prepareCustomer();
    }

    protected function prepareCustomer(): void
    {
        $request = new CustomersRequest(customerId: $this->customerId);
        $request->query()->add('display', 'full');
        $response = PrestashopConnector::make()->send($request);

        $groups = $response->json('customers')[0]['associations']['groups'] ?? [];

        $this->customer = $this->prepareThroughPipeline(
            passable: PrestashopConnector::mapFields(
                collect($response->json('customers')[0]), 'customers', 'lunar'
            ),
            pipes: [
                TranslationTransformer::class,
                FieldMapKeyTransformer::class,
                FieldLegacyTransformer::class,
            ],
            method: 'transform',
        );

        if ($groups) {
            $this->prepareGroups($groups);
        }

        $this->prepareAddresses();
    }

    protected function prepareGroups(array $groups): void
    {
        $groupIds = collect($groups)->pluck('id');
        $request = new GroupsRequest;
        $request->query()->merge([
            'filter[id]' => "[{$groupIds->implode('|')}]",
            'display' => 'full',
        ]);

        $groups = collect($response->json('groups'))->map(function ($optionValue) {
            return $this->prepareThroughPipeline(
                passable: PrestashopConnector::mapFields(collect($optionValue), 'groups', 'lunar'),
                pipes: [
                    TranslationTransformer::class,
                    FieldMapKeyTransformer::class,
                    FieldLegacyTransformer::class,
                ],
                method: 'transform',
            );
        });

        $this->customer->put('groups', $groups);
    }

    protected function prepareAddresses(): void
    {
        $request = new AddressesRequest;
        $request->query()->merge([
            'filter[id_customer]' => $this->customerId,
            //'filter[deleted]' => 0,
            'display' => 'full',
        ]);

        $addresses = collect($response->json('addresses'))->map(function ($optionValue) {
            return $this->prepareThroughPipeline(
                passable: PrestashopConnector::mapFields(collect($optionValue), 'addresses', 'lunar'),
                pipes: [
                    FieldMapKeyTransformer::class,
                    FieldLegacyTransformer::class,
                ],
                method: 'transform',
            );
        });

        $this->customer->put('addresses', $addresses);
    }

    protected function sync(): void
    {
        $this->prepareThroughPipeline(
            passable: $this->customer,
            pipes: [
                CustomerSave::class,
                CustomerGroupSave::class,
                AddressesSave::class,
            ],
        );
    }

    /**
     * The unique ID of the job.
     *
     * @return string
     */
    public function uniqueId()
    {
        return $this->customerId;
    }
}
