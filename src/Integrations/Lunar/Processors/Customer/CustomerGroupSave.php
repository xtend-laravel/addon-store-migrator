<?php

namespace XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Customer;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Processor;

class CustomerGroupSave extends Processor
{
    public function process(Collection $customer): mixed
    {
        /** @var \Lunar\Models\Customer $customerModel */
        $customerModel = $customer->get('customerModel');
        if ($customer->has('groups')) {
            collect($customer->get('groups'))->each(
                fn ($group) => $this->saveGroup($group, $customerModel)
            );
        }

        return $customer;
    }

    protected function saveGroup(Collection $group, Customer $customerModel)
    {
        $name = $group->get('name')->get(app()->getLocale());
        $customerModel->customerGroups()->updateOrCreate([
            'handle' => Str::slug($name),
        ], [
            'default' => CustomerGroup::count() === 0,
            'name' => $group['name'],
            'legacy_data' => $group,
        ]);
    }
}
