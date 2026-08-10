<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nama_types'])]
class DocumentType extends Model
{
    protected $table = 'm_document_types';

    public $timestamps = false;
}
