<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistorialEntradasDeta extends Model
{
    use HasFactory;
    protected $table = 'historial_entradas_deta';
    public $timestamps = false;

    protected $fillable = [
        'id_material',
        'id_historial',
        'cantidad',
    ];
}
