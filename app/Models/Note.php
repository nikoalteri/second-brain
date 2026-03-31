<?php

namespace App\Models;

use App\Enums\NotePriority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUserScoping;

class Note extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected $fillable = [
        'user_id',
        'title',
        'content',
        'priority',
        'date',
        'tags',
        'is_pinned',
    ];

    protected $casts = [
        'date' => 'date',
        'tags' => 'array',
        'is_pinned' => 'boolean',
        'priority' => NotePriority::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
