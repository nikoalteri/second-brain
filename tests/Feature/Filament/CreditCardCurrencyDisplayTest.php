<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Models\CreditCard;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreditCardCurrencyDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_cards_list_renders_with_default_eur_formatting(): void
    {
        $user = $this->createAdminUser();
        CreditCard::factory()->create([
            'user_id' => $user->id,
            'current_balance' => 1234.56,
        ]);

        $response = $this->actingAs($user)->get('/admin/credit-cards');

        $response->assertOk()->assertSee('1.234,56');
    }

    public function test_credit_cards_list_reformats_amounts_when_user_selects_czk(): void
    {
        $user = $this->createAdminUser();
        UserSetting::create([
            'user_id' => $user->id,
            'setting_key' => UserSetting::KEY_DISPLAY_CURRENCY,
            'setting_value' => 'CZK',
        ]);
        CreditCard::factory()->create([
            'user_id' => $user->id,
            'current_balance' => 1234.56,
        ]);

        $response = $this->actingAs($user)->get('/admin/credit-cards');

        $response->assertOk()->assertSee('Kč');
    }

    private function createAdminUser(): User
    {
        Role::findOrCreate('superadmin');

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('superadmin');

        return $user;
    }
}
