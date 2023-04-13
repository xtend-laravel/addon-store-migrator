<?php

namespace XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests;

use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\Concerns\WithBasicAuthenticator;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\Concerns\WithDefaultQuery;
use Saloon\Enums\Method;
use Saloon\Http\Request;

class OrdersRequest extends Request
{
    use WithBasicAuthenticator;
    use WithDefaultQuery;

    /**
     * The OrdersRequest instance.
     *
     * @param  int|null  $orderId
     */
    public function __construct(
        public ?int $orderId = null,
    ) {}

    /**
     * The HTTP verb the request will use.
     *
     * @var string|null
     */
    protected Method $method = Method::GET;

    /**
     * The endpoint of the request.
     *
     * @return string
     */
    public function resolveEndpoint(): string
    {
        return "orders/$this->orderId";
    }
}
