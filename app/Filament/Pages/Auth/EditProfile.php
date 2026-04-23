<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Models\UserSetting;
use App\Services\UserSettingsService;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class EditProfile extends \Filament\Auth\Pages\EditProfile
{
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = parent::mutateFormDataBeforeFill($data);
        $data['language'] = $this->getUser()->preferredLocale();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $language = Arr::pull($data, 'language');

        parent::handleRecordUpdate($record, $data);

        if ($record instanceof User && $language !== null) {
            app(UserSettingsService::class)->update($record, [
                UserSetting::KEY_LANGUAGE => $language,
            ]);
        }

        return $record->refresh();
    }

    protected function getLanguageFormComponent(): Component
    {
        return Select::make('language')
            ->label(__('Language'))
            ->options(UserSetting::optionsFor(UserSetting::KEY_LANGUAGE))
            ->required()
            ->native(false)
            ->selectablePlaceholder(false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getLanguageFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }
}
