<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nama_proses_fungsi'])]
class ProsesFungsi extends Model
{
    protected $table = 'm_proses_fungsi';

    public $timestamps = false;
}
