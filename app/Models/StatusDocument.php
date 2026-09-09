<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nama_status'])]
class StatusDocument extends Model
{
    public const DRAFT = 'DRAFT';

    public const PROPOSED = 'PROPOSED';

    public const APPROVED = 'APPROVED';

    public const REJECTED = 'REJECTED';

    public const CANCELLED = 'CANCELLED';

    public const OBSOLETE = 'OBSOLETE';

    protected $table = 'm_status_document';

    public $timestamps = false;

    public static function findByName(string $name): self
    {
        return self::where('nama_status', $name)->firstOrFail();
    }
}
