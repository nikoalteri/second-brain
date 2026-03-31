<?php

namespace App\Models;

use App\Enums\UITheme;
use App\Enums\PrivacyLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUserScoping;

class UserSetting extends Model
{
    use HasFactory, HasUserScoping;

    protected $fillable = [
        'user_id',
        'theme',
        'privacy_level',
        'notifications_enabled',
        'email_notifications',
        'dark_mode',
    ];

    protected $casts = [
        'notifications_enabled' => 'boolean',
        'email_notifications' => 'boolean',
        'dark_mode' => 'boolean',
        'theme' => UITheme::class,
        'privacy_level' => PrivacyLevel::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
