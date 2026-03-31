<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUserScoping;

class Backup extends Model
{
    use HasFactory, HasUserScoping;

    protected $fillable = [
        'user_id',
        'filename',
        'file_size',
        'backup_date',
        'notes',
    ];

    protected $casts = [
        'backup_date' => 'datetime',
        'file_size' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
