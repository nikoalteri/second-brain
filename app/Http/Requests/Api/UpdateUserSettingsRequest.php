<?php

namespace App\Http\Requests\Api;

use App\Models\UserSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            UserSetting::KEY_THEME => ['required', Rule::in(array_keys(UserSetting::optionsFor(UserSetting::KEY_THEME)))],
            UserSetting::KEY_NOTIFICATIONS => ['required', Rule::in(array_keys(UserSetting::optionsFor(UserSetting::KEY_NOTIFICATIONS)))],
            UserSetting::KEY_PRIVACY => ['required', Rule::in(array_keys(UserSetting::optionsFor(UserSetting::KEY_PRIVACY)))],
        ];
    }
}
