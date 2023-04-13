<?php

namespace XtendLunar\Addons\StoreMigrator\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\PrestashopConnector;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\ManufacturersRequest;

class BrandSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var Collection
     */
    protected Collection $brand;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected int $brandId
    ) {}

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $request = new ManufacturersRequest(manufacturerId: $this->brandId);
        $request->query()->add('display', 'full');
        $response = PrestashopConnector::make()->send($request);

        $manufacturer = $response->json('manufacturers')[0];

        // Brand::query()->where('name', $manufacturer['name'])->update(['legacy_data' => [
        //     'id' => $manufacturer['id'],
        // ]]);
    }
}
