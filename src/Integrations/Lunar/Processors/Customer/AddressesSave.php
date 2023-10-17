<?php

namespace XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Customer;

use Illuminate\Support\Collection;
use Lunar\Models\Country;
use Lunar\Models\Customer;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Processor;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\PrestashopConnector;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\CountriesRequest;
use XtendLunar\Addons\StoreMigrator\Models\StoreMigratorResourceModel;

class AddressesSave extends Processor
{
    public function process(Collection $customer, ?StoreMigratorResourceModel $resourceModel = null): mixed
    {
        /** @var \Lunar\Models\Customer $customerModel */
        $customerModel = $customer->get('customerModel');
        if ($customer->has('addresses')) {
            collect($customer->get('addresses'))->each(
                fn ($address) => $this->saveAddress($address, $customerModel)
            );
        }

        return $customer;
    }

    protected function saveAddress(Collection $address, Customer $customerModel): void
    {
        $customerModel->addresses()->updateOrCreate([
            'legacy_data->id_address' => $address->get('legacy')->get('id_address'),
        ], [
            'country_id' => $this->lookupCountryId($address->get('legacy')->get('country_id')),
            'title' => $customerModel->title,
            'first_name' => $address->get('first_name'),
            'last_name' => $address->get('last_name'),
            'company_name' => $address->get('company_name'),
            'line_one' => $address->get('line_one'),
            'line_two' => $address->get('line_two'),
            'city' => $address->get('city'),
            'state' => $address->get('city'),
            'postcode' => $address->get('postcode'),
            'contact_email' => $customerModel->email,
            'contact_phone' => $address->get('contact_phone'),
            'legacy_data' => $address->get('legacy'),
        ]);

        $customerModel->addresses->filter(
            fn ($addressModel) => $addressModel->legacy_data->get('deleted')
        )->each->delete();
    }

    protected function lookupCountryId(?int $countryId): int
    {
        $request = new CountriesRequest(countryId: $countryId);
        $request->query()->merge([
            'filter[id]' => '['.$countryId.']',
            'display' => 'full',
        ]);

        $response = PrestashopConnector::make()->send($request);
        $iso = $response->json('countries')[0]['iso_code'] ?? null;

        return Country::where('iso2', $iso)->first()->id ?? 0;
    }
}
