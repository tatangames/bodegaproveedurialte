<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Entradas;
use App\Models\HistorialEntradas;
use App\Models\HistorialSalidas;
use App\Models\HistorialTransferido;
use App\Models\TipoProyecto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TipoProyectoController extends Controller
{

    public function index(){
        return view('backend.admin.tipoproyecto.vistatipoproyecto');
    }

    public function tablaProyectos(Request $request){
        $filtro = $request->get('filtro', 'vigente'); // por defecto vigentes

        if($filtro === 'cerrado'){
            $lista = TipoProyecto::where('transferido', 1)->orderBy('nombre', 'ASC')->get();
        } elseif($filtro === 'todos'){
            $lista = TipoProyecto::orderBy('nombre', 'ASC')->get();
        } else {
            $lista = TipoProyecto::where('transferido', 0)->orderBy('nombre', 'ASC')->get();
        }

        return view('backend.admin.tipoproyecto.tablatipoproyecto', compact('lista'));
    }

    public function nuevoProyecto(Request $request){
        $regla = array(
            'nombre' => 'required',
        );

        $validar = Validator::make($request->all(), $regla);

        if ($validar->fails()){ return ['success' => 0];}

        $dato = new TipoProyecto();
        $dato->nombre = $request->nombre;
        $dato->transferido = 0;

        if($dato->save()){
            return ['success' => 1];
        }else{
            return ['success' => 2];
        }
    }

    public function informacionProyecto(Request $request){
        $regla = array(
            'id' => 'required',
        );

        $validar = Validator::make($request->all(), $regla);

        if ($validar->fails()){ return ['success' => 0];}

        if($lista = TipoProyecto::where('id', $request->id)->first()){

            return ['success' => 1, 'info' => $lista];
        }else{
            return ['success' => 2];
        }
    }

    public function editarProyecto(Request $request){

        $regla = array(
            'id' => 'required',
            'nombre' => 'required'
        );

        $validar = Validator::make($request->all(), $regla);

        if ($validar->fails()){ return ['success' => 0];}

        if(TipoProyecto::where('id', $request->id)->first()){

            TipoProyecto::where('id', $request->id)->update([
                'nombre' => $request->nombre
            ]);

            return ['success' => 1];
        }else{
            return ['success' => 2];
        }
    }
    // borrar Proyecto solicitado
    public function borrarProyecto(Request $request){
        $regla = array(
            'id' => 'required',
        );

        $validar = Validator::make($request->all(), $regla);

        if ($validar->fails()){ return ['success' => 0];}

        //if(TipoProyecto::where('id', $request->id)->first()){
        // TipoProyecto::where('id', $request->id)->delete();
        //}
        $tipoProyecto = TipoProyecto::find($request->id);

        if (!$tipoProyecto) {
            return ['success' => 0] ;
        }
        // Verificar si el ID tiene registros activos en alguna de las tablas relacionadas
        $hasActiveRecords =
            HistorialEntradas::where('id_tipoproyecto', $request->id)->exists() ||
            HistorialSalidas::where('id_tipoproyecto', $request->id)->exists() ||
            Entradas::where('id_tipoproyecto', $request->id)->exists() ||
            HistorialTransferido::where('id_tipoproyecto', $request->id)->exists();

        if ($hasActiveRecords) {
            return ['success' => 2];
        }
        // Si no hay registros activos, eliminar el proyecto
        $tipoProyecto->delete();
        return ['success' => 1];
    }

}
