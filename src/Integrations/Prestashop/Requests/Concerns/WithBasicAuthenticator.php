<?php

namespace XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\Concerns;

use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\BasicAuthenticator;

trait WithBasicAuthenticator
{
    public function defaultAuth(): ?Authenticator
    {
        return new BasicAuthenticator(config('services.prestashop_webservice.key'), '');
    }
}
