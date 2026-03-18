<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionType extends Model
{
    protected $fillable = [
        'name',
        'is_income',
    ];

    protected $casts = [
        'is_income' => 'boolean',
    ];

    public static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $incomeNames = ['Earnings', 'Cashback'];
            $model->is_income ??= in_array($model->name, $incomeNames);
        });
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
