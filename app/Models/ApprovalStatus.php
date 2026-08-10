<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['kode_status', 'nama_status'])]
class ApprovalStatus extends Model
{
    protected $table = 'm_approval_status';

    public $timestamps = false;
}
