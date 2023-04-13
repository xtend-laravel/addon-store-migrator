<?php

namespace XtendLunar\Addons\StoreMigrator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\CartsRequest;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\CategoriesRequest;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\CustomersRequest;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\FeatureRequest;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\OptionRequest;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\OrdersRequest;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\ProductsRequest;
use XtendLunar\Addons\StoreMigrator\Jobs\CartSync;
use XtendLunar\Addons\StoreMigrator\Jobs\CategorySync;
use XtendLunar\Addons\StoreMigrator\Jobs\CollectionParentSync;
use XtendLunar\Addons\StoreMigrator\Jobs\CustomerSync;
use XtendLunar\Addons\StoreMigrator\Jobs\FeatureSync;
use XtendLunar\Addons\StoreMigrator\Jobs\OptionSync;
use XtendLunar\Addons\StoreMigrator\Jobs\OrderSync;
use XtendLunar\Addons\StoreMigrator\Jobs\ProductSync;
use Throwable;

class OrderMigrationSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrator:order-sync
        {--onlyNew= : Only insert new records}
        {--limit= : Limit the number of records to sync}
        {--id= : The id of the request}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Orders';

    protected PrestashopConnector $connector;

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(PrestashopConnector $connector)
    {
        $request = $connector->request(new OrdersRequest);
        $dateRange = '['.now()->startOfMonth()->format('Y-m-d H:i:s').','.now()->format('Y-m-d H:i:s').']';
        $request->query()->set([
            'date' => ! $this->hasOption('id'),
            'output_format' => 'JSON',
            'filter[id_customer]' => '[1,9999999]',
            'display' => 'full',
        ]);

        $this->hasOption('id')
            ? $request->query()->add('filter[id]', $this->option('id'))
            : $request->query()->add('filter[date_add]', $dateRange);

        //$request->query()->add('limit', 1);
        $response = $request->send();
        $response->throw();

        $orders = collect($response->json('orders'));

        $orders->each(function ($order) {
            $productIds = collect($order['associations']['order_rows'])->pluck('product_id')->unique();
            Bus::chain([
                ...$productIds->map(
                    fn ($productId) => new ProductSync($productId)
                ),
                new CartSync($order['id_cart']),
                new CustomerSync($order['id_customer']),
                new OrderSync($order['id']),
            ])->catch(function (Throwable $e) {
                // Handle the exception
                dd($e);
            })->dispatch();
        });

        //entityIds->each(fn ($entityId) => OrderSync::dispatch($entityId)->onQueue('orders'));

        $this->connector = $connector;
        //
        // if ($this->option('id')) {
        //     $entityIds = $entityIds->filter(function ($id) {
        //         return $id == (int) $this->option('id');
        //     });
        // }
        // if ($this->option('onlyNew') === 'true') {
        //     $entityIds = $entityIds->filter(function ($id) use ($entity) {
        //         return ! Order::where('legacy_data->id_order', $id)->exists();
        //     });
        // }
        //
        // $entityIds->take($this->option('limit'))->each(
        //     fn ($entityId) => $this->orderSync($entityId)
        // );
        //
        //
        // $this->orderSync([
        //     'id_customer' => $this->entityRequest('customers'),
        //     'id_cart' => $this->entityRequest('carts'),
        //     'id_order' => $this->entityRequest('orders'),
        //     'id_products' => $this->entityRequest('products'),
        // ]);
    }

    protected function orderSync(array $entity): void
    {
        OrderSync::dispatch($entity['id_order']);
    }

    protected function entityRequest(string $entity)
    {
        $request = $this->connector->request(
            match ($entity) {
                'customers' => new CustomersRequest,
                'carts' => new CartsRequest,
                'orders' => new OrdersRequest,
            }
        );

        $request->query()->merge([
            'filter[id_customer]' => '[1,9999999]',
            //'limit' => '1000',
        ]);

        $response = $request->send();
        $response->throw();

        $entityIds = collect($response->json($entity))->pluck('id');

    }

    protected function runCategories(PrestashopConnector $connector)
    {
        $request = $connector->request(new CategoriesRequest);
        $response = $request->send();
        $response->throw();

        $entityIds = collect($response->json('categories'))->pluck('id');
        $entityIds->each(fn ($entityId) => CategorySync::dispatch($entityId)->onQueue('categories'));

        CollectionParentSync::dispatchSync();
    }

    protected function runProductOptions(PrestashopConnector $connector)
    {
        $request = $connector->request(new OptionRequest);
        $response = $request->send();
        $response->throw();

        $entityIds = collect($response->json('product_options'))->pluck('id');
        $entityIds->each(fn ($entityId) => OptionSync::dispatch($entityId)->onQueue('product_options'));
    }

    protected function runProductFeatures(PrestashopConnector $connector)
    {
        $request = $connector->request(new FeatureRequest);
        $response = $request->send();
        $response->throw();

        $entityIds = collect($response->json('product_features'))->pluck('id');
        $entityIds->each(fn ($entityId) => FeatureSync::dispatch($entityId)->onQueue('product_features'));
    }

    protected function runProducts(PrestashopConnector $connector)
    {
        $request = $connector->request(new ProductsRequest);
        //$request->query()->add('limit', 100);
        $response = $request->send();
        $response->throw();

        $entityIds = collect($response->json('products'))->pluck('id');
        $entityIds->each(fn ($entityId) => ProductSync::dispatch($entityId)->onQueue('products'));
    }

    protected function runCustomers(PrestashopConnector $connector)
    {
        $request = $connector->request(new CustomersRequest);
        //$request->query()->add('limit', 100);
        $response = $request->send();
        $response->throw();

        $entityIds = collect($response->json('customers'))->pluck('id');
        $entityIds->each(fn ($entityId) => CustomerSync::dispatch($entityId)->onQueue('customers'));
    }

    protected function runCarts(PrestashopConnector $connector)
    {
        $request = $connector->request(new CartsRequest);
        $request->query()->add('filter[id_customer]', '[1,9999999]');
        //$request->query()->add('limit', 100);
        $response = $request->send();
        $response->throw();

        $entityIds = collect($response->json('carts'))->pluck('id');
        $entityIds->each(fn ($entityId) => CartSync::dispatch($entityId)->onQueue('carts'));
    }

    protected function runOrders(PrestashopConnector $connector)
    {
        $request = $connector->request(new OrdersRequest);
        $request->query()->add('filter[id_customer]', '[1,9999999]');
        //$request->query()->add('limit', 100);
        $response = $request->send();
        $response->throw();

        $entityIds = collect($response->json('orders'))->pluck('id');
        $entityIds->each(fn ($entityId) => OrderSync::dispatch($entityId)->onQueue('orders'));
    }
}
