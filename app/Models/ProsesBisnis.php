<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['kode', 'nama_proses_bisnis'])]
class ProsesBisnis extends Model
{
    protected $table = 'm_proses_bisnis';

    public $timestamps = false;
}
