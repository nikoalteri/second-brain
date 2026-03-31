<?php

namespace App\Models;

use App\Enums\JournalMood;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUserScoping;

class JournalEntry extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected $fillable = [
        'user_id',
        'date',
        'title',
        'content',
        'mood',
        'tags',
    ];

    protected $casts = [
        'date' => 'date',
        'tags' => 'array',
        'mood' => JournalMood::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
