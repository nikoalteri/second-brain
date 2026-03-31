<?php

namespace App\Models;

use App\Enums\ContactRelationshipType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUserScoping;

class Contact extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone',
        'relationship_type',
        'notes',
        'birthday',
    ];

    protected $casts = [
        'birthday' => 'date',
        'relationship_type' => ContactRelationshipType::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
