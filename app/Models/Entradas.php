<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Entradas extends Model
{
    use HasFactory;
    protected $table = 'entradas';
    public $timestamps = false;

    protected $fillable = [
        'id_material',
        'id_tipoproyecto',
        'cantidad',
    ];
}
