<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUserScoping;

class Notification extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'read_at',
        'action_url',
        'related_model',
        'related_id',
        'notification_condition_id',
        'episode',
        'severity',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'episode' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function condition()
    {
        return $this->belongsTo(NotificationCondition::class, 'notification_condition_id');
    }
}
