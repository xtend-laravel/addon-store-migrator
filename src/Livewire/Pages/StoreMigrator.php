<?php

namespace XtendLunar\Addons\StoreMigrator\Livewire\Pages;

use Illuminate\View\View;
use Livewire\Component;
use Lunar\Hub\Http\Livewire\Traits\Notifies;

class StoreMigrator extends Component
{
    use Notifies;

    public function render(): View
    {
        return view('adminhub::livewire.pages.store-migrator')
            ->layout('adminhub::layouts.app', [
                'title' => __('Store Migrator'),
            ]);
    }
}
