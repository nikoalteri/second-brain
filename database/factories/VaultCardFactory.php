<?php

namespace Database\Factories;

use App\Enums\CardBrand;
use App\Enums\VaultCardType;
use App\Models\User;
use App\Models\VaultCard;
use Illuminate\Database\Eloquent\Factories\Factory;

class VaultCardFactory extends Factory
{
    protected $model = VaultCard::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => null,
            'name' => $this->faker->word(),
            'type' => VaultCardType::DEBIT,
            'brand' => CardBrand::VISA,
            'card_number' => $this->faker->numerify('################'),
            'expiry_month' => $this->faker->numberBetween(1, 12),
            'expiry_year' => $this->faker->numberBetween(2026, 2035),
            'cvv' => $this->faker->numerify('###'),
            'pin' => $this->faker->numerify('####'),
        ];
    }

    public function debit(): static
    {
        return $this->state(['type' => VaultCardType::DEBIT]);
    }

    public function prepaid(): static
    {
        return $this->state(['type' => VaultCardType::PREPAID]);
    }

    public function amex(): static
    {
        return $this->state([
            'brand' => CardBrand::AMEX,
            'cvv' => $this->faker->numerify('####'),
            'security_code' => $this->faker->numerify('###'),
        ]);
    }
}
