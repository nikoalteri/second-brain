<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUserScoping;

class AuditLog extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected $table = 'audit_logs';

    protected $fillable = [
        'user_id',
        'action',
        'model_name',
        'model_id',
        'changes',
        'ip_address',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
