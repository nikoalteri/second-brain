<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Number;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LocaleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'set-user-locale'])->get('/api/test/locale-probe', function () {
            return response()->json([
                'locale' => app()->getLocale(),
                'carbon_locale' => Carbon::getLocale(),
                'number' => Number::format(1234.5, maxPrecision: 1),
                'active' => __('localization.active_locale'),
                'fallback' => __('localization.fallback_probe'),
            ]);
        });
    }

    public function test_locale_middleware_applies_authenticated_user_language_and_backend_fallbacks(): void
    {
        $user = User::factory()->create();
        $user->userSettings()->create([
            'setting_key' => 'language',
            'setting_value' => 'it',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/test/locale-probe');

        $response->assertOk()
            ->assertJsonPath('locale', 'it')
            ->assertJsonPath('carbon_locale', 'it')
            ->assertJsonPath('active', 'Italiano attivo')
            ->assertJsonPath('fallback', 'English fallback probe')
            ->assertJsonPath('number', '1.234,5');
    }

    public function test_locale_middleware_defaults_to_english_without_preference(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/test/locale-probe');

        $response->assertOk()
            ->assertJsonPath('locale', 'en')
            ->assertJsonPath('carbon_locale', 'en')
            ->assertJsonPath('active', 'English active')
            ->assertJsonPath('fallback', 'English fallback probe')
            ->assertJsonPath('number', '1,234.5');
    }
}
