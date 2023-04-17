<?php

namespace XtendLunar\Addons\StoreMigrator\Livewire\Components;

use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Lunar\Hub\Http\Livewire\Traits\Notifies;
use XtendLunar\Addons\StoreMigrator\Models\StoreMigratorIntegration;

class StoreMigratorIntegrationsTable extends Component implements Tables\Contracts\HasTable
{
    use Notifies;
    use Tables\Concerns\InteractsWithTable;

    /**
     * {@inheritDoc}
     */
    protected function getTableQuery(): Builder
    {
        return StoreMigratorIntegration::query();
    }

    /**
     * {@inheritDoc}
     */
    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('name'),
	        Tables\Columns\TextColumn::make('integration')->sortable(),
	        Tables\Columns\TagsColumn::make('resources'),
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getTableActions(): array
    {
        return [
             Tables\Actions\ActionGroup::make([
                 Tables\Actions\ViewAction::make(),
             ]),
        ];
    }

    /**
     * Render the livewire component.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function render()
    {
        return view('adminhub::livewire.components.tables.base-table')
            ->layout('adminhub::layouts.base');
    }
}
