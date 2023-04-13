<?php

namespace XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Transformers;

use Illuminate\Support\Collection;
use XtendLunar\Addons\StoreMigrator\Integrations\TransformerInterface;

class VariantsTransformer implements TransformerInterface
{
    public function transform(Collection $data, \Closure $next): Collection
    {
        if (collect($data->get('options'))->isEmpty()) {
            return $next($data);
        }

        $data->put('options', [1, 2, 3]);

        // $result = collect($data)
        //     ->except(['fieldMapper'])
        //     ->filter(fn ($value, $field) => $fieldMap->has($field))
        //     ->mapWithKeys(fn ($value, $field) => [$fieldMap->get($field) => $value]);

        /**
        "options": [
        {
            "name": "Size",
            "values": [
                "35 1/2"
            ]
        },
        {
            "name": "Color",
            "values": [
                "Rose"
            ]
        }]
         */

        return $next($data);
    }
}
