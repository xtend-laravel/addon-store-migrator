<?php

namespace XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\Concerns\WithBasicAuthenticator;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\Concerns\WithDefaultQuery;

class AddressesRequest extends Request
{
    use WithBasicAuthenticator;
    use WithDefaultQuery;

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
        return 'addresses';
    }
}
