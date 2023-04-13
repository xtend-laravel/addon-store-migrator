<?php

namespace XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Transformers;

use Illuminate\Support\Collection;
use XtendLunar\Addons\StoreMigrator\Integrations\TransformerInterface;

class FieldLegacyTransformer implements TransformerInterface
{
    public function transform(Collection $data, \Closure $next): Collection
    {
        $mappedData = $data->filter(fn ($value, $field) => ! str_starts_with($field, 'legacy->'));

        return $next($mappedData->merge([
            'legacy' => $data->filter(fn ($value, $field) => str_starts_with($field, 'legacy->'))
                             ->mapWithKeys(fn ($value, $field) => [str_replace('legacy->', '', $field) => $value]),
        ]));
    }
}
