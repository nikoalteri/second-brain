<?php

namespace App\Models;

use App\Enums\MessageImportance;
use App\Enums\MessageCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUserScoping;

class Message extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected $fillable = [
        'user_id',
        'to_user_id',
        'subject',
        'content',
        'read_at',
        'importance',
        'category',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'importance' => MessageImportance::class,
        'category' => MessageCategory::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
