<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nama_status'])]
class StatusDocument extends Model
{
    protected $table = 'm_status_document';

    public $timestamps = false;
}
