<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nama_department', 'kode_department'])]
class Department extends Model
{
    public $timestamps = false;
}
