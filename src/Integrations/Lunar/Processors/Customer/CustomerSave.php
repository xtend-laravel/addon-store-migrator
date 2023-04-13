<?php

namespace XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Customer;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Lunar\Models\Customer;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Processor;

class CustomerSave extends Processor
{
    public function process(Collection $customer): mixed
    {
        $dob = $customer->get('legacy')->get('birthday') !== '0000-00-00'
            ? $customer->get('legacy')->get('birthday')
            : null;

        /** @var Customer $customerModel */
        $customerModel = Customer::updateOrCreate([
            'legacy_data->id_customer' => $customer->get('legacy')->get('id_customer'),
        ], [
            'dob' => $dob,
            'title' => $this->lookupTitle($customer),
            'email' => $customer->get('email'),
            'first_name' => $customer->get('first_name'),
            'last_name' => $customer->get('last_name'),
            'company_name' => $customer->get('company_name'),
            'newsletter' => $customer->get('legacy')->get('newsletter'),
            'legacy_data' => $customer->get('legacy'),
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
