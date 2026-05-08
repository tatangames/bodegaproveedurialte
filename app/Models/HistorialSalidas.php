<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistorialSalidas extends Model
{
    use HasFactory;
    protected $table = 'historial_salidas';
    public $timestamps = false;

    public function tipoproyecto(){
        return $this->belongsTo(TipoProyecto::class, 'id_tipoproyecto');
    }
}
