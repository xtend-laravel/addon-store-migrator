<?php

namespace XtendLunar\Addons\StoreMigrator\Commands;

use Illuminate\Console\Command;
use Laravel\Pennant\Feature;
use Lunar\Models\Brand;
use Lunar\Models\Cart;
use Lunar\Models\Collection;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Lunar\Models\Product;
use Lunar\Models\ProductOption;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\PrestashopConnector;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\CartsRequest;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\CategoriesRequest;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\CustomersRequest;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\FeatureRequest;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\ManufacturersRequest;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\OptionRequest;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\OrdersRequest;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\ProductsRequest;
use XtendLunar\Addons\StoreMigrator\Jobs\BrandSync;
use XtendLunar\Addons\StoreMigrator\Jobs\CartSync;
use XtendLunar\Addons\StoreMigrator\Jobs\CategorySync;
use XtendLunar\Addons\StoreMigrator\Jobs\CollectionParentSync;
use XtendLunar\Addons\StoreMigrator\Jobs\CustomerSync;
use XtendLunar\Addons\StoreMigrator\Jobs\FeatureSync;
use XtendLunar\Addons\StoreMigrator\Jobs\OptionSync;
use XtendLunar\Addons\StoreMigrator\Jobs\OrderSync;
use XtendLunar\Addons\StoreMigrator\Jobs\ProductSync;
use XtendLunar\Features\ProductFeatures\Models\ProductFeature;

class PrestashopMigrationSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrator:sync-prestashop
        {--entity=all : The entity of the request}
        {--onlyNew= : Only insert new records}
        {--limit= : Limit the number of records to sync}
        {--id= : The id of the request}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Prestashop data';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(PrestashopConnector $connector)
    {
        $entity = $this->option('entity');

        if ($entity === 'all') {
            $this->runAll($connector);
            return;
        }

        if (! Collection::count() && $entity !== 'categories') {
            $this->error('No collections found.');
            $this->info("Please run \n \"php artisan ps-migration:sync --entity=categories\"");

            return;
        }

        // @todo Validate the entity of the request

        $request = match ($entity) {
            'manufacturers' => new ManufacturersRequest,
            'categories' => new CategoriesRequest,
            'product_options' => new OptionRequest,
            'product_features' => new FeatureRequest,
            'products' => new ProductsRequest,
            'customers' => new CustomersRequest,
            'carts' => new CartsRequest,
            'orders' => new OrdersRequest,
        };

        if (in_array($entity, ['carts', 'orders'])) {
            // Only fetch customer carts and orders for now
            $request->query()->merge([
                'filter[id_customer]' => '[1,9999999]',
                //'limit' => '1000',
            ]);
        }

        if (in_array($entity, ['products'])) {
            $request->query()->merge([
                'filter[active]' => true,
            ]);
        }

        $response = PrestashopConnector::make()->send($request);
        $response->throw();

        $entityIds = collect($response->json($entity))->pluck('id');

        if ($this->option('id')) {
            $entityIds = $entityIds->filter(function ($id) {
                return $id == (int) $this->option('id');
            });
        }
        if ($this->option('onlyNew') === 'true') {
            $entityIds = $entityIds->filter(function ($id) use ($entity) {
                return match($entity) {
                    'manufacturers' => ! Brand::where('legacy_data->id', $id)->exists(),
                    'categories' => ! Collection::where('legacy_data->id', $id)->exists(),
                    'product_options' => ! ProductOption::where('legacy_data->id', $id)->exists(),
                    'product_features' => ! ProductFeature::where('legacy_data->id', $id)->exists(),
                    'products' => ! Product::where('legacy_data->id_product', $id)->exists(),
                    'customers' => ! Customer::where('legacy_data->id_customer', $id)->exists(),
                    'carts' => ! Cart::where('legacy_data->id_cart', $id)->exists(),
                    'orders' => ! Order::where('legacy_data->id_order', $id)->exists(),
                };
            });
        }

        $entityIds->take($this->option('limit'))->each(
            fn ($entityId) => match ($entity) {
                'manufacturers' => BrandSync::dispatchSync($entityId),
                'categories' => CategorySync::dispatchSync($entityId),
                'product_options' => OptionSync::dispatchSync($entityId),
                'product_features' => FeatureSync::dispatchSync($entityId),
                'products' => ProductSync::dispatch($entityId)->onQueue('products'),
                'customers' => CustomerSync::dispatch($entityId)->onQueue('customers'),
                'carts' => CartSync::dispatch($entityId)->onQueue('carts'),
                'orders' => OrderSync::dispatch($entityId)->onQueue('orders'),
            }
        );

        if ($entity === 'categories') {
            CollectionParentSync::dispatchSync();
        }
    }

    protected function runAll(PrestashopConnector $connector): void
    {
        //$this->runCategories($connector);
        //$this->runProductOptions($connector);
        //$this->runProductFeatures($connector);
        $this->runProducts($connector);
        //$this->runCustomers($connector);
        //$this->runCarts($connector);
        //$this->runOrders($connector);
    }

    protected function runCategories(PrestashopConnector $connector)
    {
        $response = $connector->send(new CategoriesRequest);
        $response->throw();

        $entityIds = collect($response->json('categories'))->pluck('id')->skip(1);

        $entityIds->each(
			fn ($entityId) => CategorySync::dispatch($entityId)->onQueue('categories'),
        );

		// @todo this is a slow process, need to optimize
        CollectionParentSync::dispatchSync();
    }

    protected function runProductOptions(PrestashopConnector $connector)
    {
        $response = $connector->send(new OptionRequest);
        $response->throw();

        $entityIds = collect($response->json('product_options'))->pluck('id');
        $entityIds->each(
			fn ($entityId) => OptionSync::dispatch($entityId)->onQueue('product_options'),
        );
    }

    protected function runProductFeatures(PrestashopConnector $connector)
    {
	    if (Feature::inactive('product-features')) {
			return;
	    }

        $response = $connector->send(new FeatureRequest);
        $response->throw();

        $entityIds = collect($response->json('product_features'))->pluck('id');
        $entityIds->each(
			fn ($entityId) => FeatureSync::dispatch($entityId)->onQueue('product_features'),
        );
    }

    protected function runProducts(PrestashopConnector $connector)
    {
	    $request = new ProductsRequest;
        $request->query()->add('limit', 200);
	    $response = $connector->send($request);
        $response->throw();

        $entityIds = collect($response->json('products'))->pluck('id');
        $entityIds->each(
			fn ($entityId) => ProductSync::dispatchSync($entityId),
        );
    }

    protected function runCustomers(PrestashopConnector $connector)
    {
        $response = $connector->send(new CustomersRequest);
        //$request->query()->add('limit', 100);
        $response->throw();

        $entityIds = collect($response->json('customers'))->pluck('id');
        $entityIds->each(fn ($entityId) => CustomerSync::dispatch($entityId)->onQueue('customers'));
    }

    protected function runCarts(PrestashopConnector $connector)
    {
        $request = new CartsRequest;
        $request->query()->add('filter[id_customer]', '[1,9999999]');
        //$request->query()->add('limit', 100);
        $response = $connector->send($request);
        $response->throw();

        $entityIds = collect($response->json('carts'))->pluck('id');
        $entityIds->each(fn ($entityId) => CartSync::dispatch($entityId)->onQueue('carts'));
    }

    protected function runOrders(PrestashopConnector $connector)
    {
        $request = new OrdersRequest;
        $request->query()->add('filter[id_customer]', '[1,9999999]');
        //$request->query()->add('limit', 100);
        $response = $connector->send($request);
        $response->throw();

        $entityIds = collect($response->json('orders'))->pluck('id');
        $entityIds->each(fn ($entityId) => OrderSync::dispatch($entityId)->onQueue('orders'));
    }
}
