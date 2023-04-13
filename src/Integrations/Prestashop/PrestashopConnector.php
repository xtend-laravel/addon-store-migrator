<?php

namespace XtendLunar\Addons\StoreMigrator\Integrations\Prestashop;

use Illuminate\Support\Collection;
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;

class PrestashopConnector extends Connector
{
    use AcceptsJson;

    /**
     * The Base URL of the API.
     *
     * @return string
     */
    public function resolveBaseUrl(): string
    {
        return (string) config('services.prestashop_webservice.url');
    }

    /**
     * The headers that will be applied to every request.
     *
     * @return string[]
     */
    public function defaultHeaders(): array
    {
        return [];
    }

    /**
     * The config options that will be applied to every request.
     *
     * @return string[]
     */
    public function defaultConfig(): array
    {
        return [
            'timeout' => 300,
        ];
    }

    public static function mapFields(Collection $data, string $entity, string $destination): Collection
    {
        $fieldMapper = new FieldMapper($data, $entity, 'prestashop', $destination);

        return $fieldMapper->map();
    }
}
