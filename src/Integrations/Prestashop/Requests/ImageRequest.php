<?php

namespace XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests;

use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\Concerns\WithBasicAuthenticator;
use Saloon\Enums\Method;
use Saloon\Http\Request;

class ImageRequest extends Request
{
    use WithBasicAuthenticator;

    /**
     * The ImageRequest instance.
     *
     * @param  string  $imageType
     * @param  int  $entityId
     */
    public function __construct(
        public string $imageType,
        public int $entityId,
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
        return "images/$this->imageType/$this->entityId";
    }
}
