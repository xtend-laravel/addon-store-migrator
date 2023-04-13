<?php

namespace XtendLunar\Addons\StoreMigrator\Integrations;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

abstract class AbstractFieldMapper
{
    protected array $localeMap = [];

    protected string $translationKey;

    public function __construct(
        protected Collection $data,
        protected string $entity,
        protected string $source,
        protected string $destination,
    ) {}

    public function getFieldsByEntity(): Collection
    {
        $integration = class_basename(Str::of(get_called_class())->replaceLast('FieldMapper', '')->value());
        $filePath = __DIR__."/$integration/data/$this->source-$this->destination-$this->entity.json";

        return File::exists($filePath) ? collect(json_decode(File::get($filePath), true)) : collect();
    }

    public function map(): Collection
    {
        $data = $this->data
            ->filter(fn ($value, $key): bool => $this->getFieldsByEntity()->has($key))
            ->merge(['fieldMapper' => $this->toArray()]);

        // if ($this->entity === 'combinations') {
        //     dd($data->toArray());
        // }

        return $data;
    }

    public function toArray(): array
    {
        return [
            'localeMap' => $this->localeMap,
            'translationKey' => $this->translationKey,
            'translatableAttributes' => $this->translatableAttributes()[$this->entity],
            'fieldsByEntity' => $this->getFieldsByEntity(),
        ];
    }

    abstract public function translatableAttributes(): array;
}
