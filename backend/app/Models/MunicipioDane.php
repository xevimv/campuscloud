<?php
// app/Models/MunicipioDane.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MunicipioDane extends Model
{
    public $timestamps  = false;
    public $incrementing = false;
    protected $table    = 'municipios_dane';
    protected $keyType  = 'string';
    protected $primaryKey = 'codigo';

    protected $fillable = ['codigo', 'departamento', 'municipio'];
}
