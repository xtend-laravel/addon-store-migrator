<?php

namespace XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Customer;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Lunar\Models\Customer;
use Lunar\Models\Product;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Processor;
use XtendLunar\Addons\StoreMigrator\Models\StoreMigratorResourceModel;

class CustomerSave extends Processor
{
    public function process(Collection $customer, ?StoreMigratorResourceModel $resourceModel = null): mixed
    {
        $dob = $customer->get('legacy')->get('birthday') !== '0000-00-00'
            ? $customer->get('legacy')->get('birthday')
            : null;

        /** @var Customer $customerModel */
        $customerModel = Customer::updateOrCreate([
            'legacy_data->id_customer' => $customer->get('legacy')->get('id_customer'),
        ], [
            'title' => $this->lookupTitle($customer),
            'first_name' => $customer->get('first_name'),
            'last_name' => $customer->get('last_name'),
            'company_name' => $customer->get('company_name'),
            'legacy_data' => $customer->get('legacy'),
            'meta' => [
                'dob' => $dob,
                'newsletter' => collect($customer->get('legacy'))->get('newsletter'),
            ]
        ]);

        if ($customerModel->legacy_data->get('deleted')) {
            $customerModel->delete();
        }

        $user = User::updateOrCreate([
            'email' => $customer->get('email'),
        ], [
            'name' => $customer->get('first_name').' '.$customer->get('last_name'),
            'password' => Hash::make("jl$customerModel->id"),
        ]);

        $customerModel->users()->sync($user);

        $customerModel->setCreatedAt($customer->get('created_at'))->save();
        $customer->put('customerModel', $customerModel);

        $resourceModel->destination_model_type = Customer::class;
        $resourceModel->destination_model_id = $customerModel->id;
        $resourceModel->status = 'processing';
        $resourceModel->save();

        return $customer;
    }

    protected function lookupTitle(Collection $customer): string
    {
        return match ($customer->get('legacy')->get('id_gender')) {
            '1' => 'Mr.',
            '2' => 'Mrs.',
            default => '--',
        };
    }
}
