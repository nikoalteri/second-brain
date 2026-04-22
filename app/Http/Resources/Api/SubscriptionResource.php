<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'name'                    => $this->name,
            'subscription_frequency_id' => $this->subscription_frequency_id,
            'frequency'               => $this->frequency,
            'frequency_label'         => $this->frequency_label,
            'frequency_option'        => $this->whenLoaded('frequencyOption', fn () => [
                'id' => $this->frequencyOption?->id,
                'name' => $this->frequencyOption?->name,
                'slug' => $this->frequencyOption?->slug,
                'months_interval' => $this->frequencyOption?->months_interval,
            ]),
            'account_id'              => $this->account_id,
            'credit_card_id'          => $this->credit_card_id,
            'payment_source_type'     => $this->payment_source_type,
            'monthly_cost'            => (float) $this->monthly_cost,
            'annual_cost'             => (float) $this->annual_cost,
            'billing_amount'          => (float) $this->billing_amount,
            'day_of_month'            => $this->day_of_month,
            'next_renewal_date'       => $this->next_renewal_date?->toDateString(),
            'auto_create_transaction' => (bool) $this->auto_create_transaction,
            'status'                  => $this->status,
            'notes'                   => $this->notes,
            'account'                 => $this->whenLoaded('account', fn () => [
                'id' => $this->account?->id,
                'name' => $this->account?->name,
            ]),
            'credit_card'             => $this->whenLoaded('creditCard', fn () => [
                'id' => $this->creditCard?->id,
                'name' => $this->creditCard?->name,
            ]),
            'category'                => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
            ]),
            'created_at'              => $this->created_at->toISOString(),
            'updated_at'              => $this->updated_at->toISOString(),
        ];
    }
}
