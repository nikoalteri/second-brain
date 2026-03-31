<?php

namespace App\Models;

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
        'document_type',
        'upload_path',
        'upload_date',
    ];

    protected $casts = [
        'upload_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
