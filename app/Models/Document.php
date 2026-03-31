<?php

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUserScoping;

class Document extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected $fillable = [
        'user_id',
        'title',
        'type',
        'file_path',
        'upload_date',
        'expiry_date',
        'notes',
    ];

    protected $casts = [
        'upload_date' => 'date',
        'expiry_date' => 'date',
        'type' => DocumentType::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
