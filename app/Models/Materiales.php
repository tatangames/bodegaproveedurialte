<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Materiales extends Model
{
    use HasFactory;
    protected $table = 'materiales';
    public $timestamps = false;

    public function unidadMedida()
    {
        return $this->belongsTo(UnidadMedida::class, 'id_medida');
    }

    public function objetoEspecifico()
    {
        return $this->belongsTo(ObjetoEspecifico::class, 'id_objespecifico');
    }


}
