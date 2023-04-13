<?php

namespace XtendLunar\Addons\StoreMigrator\Integrations\Lunar;

use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\FieldMapper;
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;

class LunarConnector extends Connector
{
    use AcceptsJson;

    /**
     * The Base URL of the API.
     *
     * @return string
     */
    public function resolveBaseUrl(): string
    {
        return (string) config('services.lunar_webservice.url');
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
            'timeout' => 30,
        ];
    }

    public static function mapFields(array $data, string $entity, string $destination): void
    {
        $fieldMapper = new FieldMapper($data, $entity, 'prestashop', $destination);
        $fieldMapper->map()->dd();
    }
}
