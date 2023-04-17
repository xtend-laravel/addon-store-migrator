<?php

namespace XtendLunar\Addons\StoreMigrator\Livewire\Components;

use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Lunar\Hub\Http\Livewire\Traits\Notifies;
use XtendLunar\Addons\StoreMigrator\Models\Migrator;

class StoreMigratorTable extends Component implements Tables\Contracts\HasTable
{
    use Notifies;
    use Tables\Concerns\InteractsWithTable;

    /**
     * {@inheritDoc}
     */
    protected function getTableQuery(): Builder
    {
        return Migrator::query();
    }

    /**
     * {@inheritDoc}
     */
    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
	        Tables\Columns\SelectColumn::make('integration')->options([
				'prestashop' => 'Prestashop',
			]),
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getTableActions(): array
    {
        return [
            // Tables\Actions\ActionGroup::make([
            //     Tables\Actions\RestoreAction::make(),
            //     Tables\Actions\EditAction::make()->url(fn (Brand $record): string => route('hub.brands.show', ['brand' => $record])),
            // ]),
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
