<?php

namespace XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests;

use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\Concerns\WithBasicAuthenticator;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\Concerns\WithDefaultQuery;
use Saloon\Enums\Method;
use Saloon\Http\Request;

class FeatureRequest extends Request
{
    use WithBasicAuthenticator;
    use WithDefaultQuery;

    /**
     * The OptionRequest instance.
     *
     * @param  int|null  $featureId
     */
    public function __construct(
        public ?int $featureId = null,
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
        return "product_features/$this->featureId";
    }
}
