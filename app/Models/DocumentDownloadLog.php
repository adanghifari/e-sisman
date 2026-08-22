<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $t_document_id
 * @property int|null $t_document_file_id
 * @property int|null $user_id
 * @property Carbon $downloaded_at
 * @property string|null $ip_address
 * @property string|null $user_agent
 */
#[Fillable([
    't_document_id',
    't_document_file_id',
    'user_id',
    'downloaded_at',
    'ip_address',
    'user_agent',
])]
class DocumentDownloadLog extends Model
{
    protected $table = 't_document_download_logs';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'downloaded_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 't_document_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(DocumentFile::class, 't_document_file_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
