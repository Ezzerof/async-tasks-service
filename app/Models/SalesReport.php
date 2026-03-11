<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReport extends Model
{
    use HasFactory;

    const STATUS_PENDING    = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED  = 'completed';
    const STATUS_FAILED     = 'failed';

    protected $fillable = [
        'user_id',
        'status',
        'progress',
        'file_path',
        'row_count',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'progress'  => 'integer',
            'row_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
