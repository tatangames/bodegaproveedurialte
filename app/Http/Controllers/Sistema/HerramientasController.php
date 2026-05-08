<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Herramientas;
use App\Models\UnidadMedida;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class HerramientasController extends Controller
{

    public function indexInventarioHerramientas(){

        $lUnidad = UnidadMedida::orderBy('nombre')->get();

        return view('backend.admin.herramientas.inventario.vistainventario', compact('lUnidad'));
    }

    public function tablaInventarioHerramientas(){

        $lista = Herramientas::orderBy('nombre', 'ASC')->get();

        foreach ($lista as $item) {
            $medida = '';
            if($dataUnidad = UnidadMedida::where('id', $item->id_medida)->first()){
                $medida = $dataUnidad->nombre;
            }

            $item->medida = $medida;
        }

        return view('backend.admin.herramientas.inventario.tablainventario', compact('lista'));
    }

    public function nuevaHerramienta(Request $request){

        $regla = array(
            'nombre' => 'required',
        );

        $validar = Validator::make($request->all(), $regla);

        if ($validar->fails()){ return ['success' => 0];}

        $dato = new Herramientas();
        $dato->id_medida = $request->unidad;
        $dato->nombre = $request->nombre;
        $dato->codigo = $request->codigo;
        $dato->cantidad = 0;

        if($dato->save()){
            return ['success' => 1];
        }else{
            return ['success' => 2];
        }
    }


    public function informacionHerramienta(Request $request){
        $regla = array(
            'id' => 'required',
        );

        $validar = Validator::make($request->all(), $regla);

        if ($validar->fails()){ return ['success' => 0];}

        if($lista = Herramientas::where('id', $request->id)->first()){

            $arrayUnidad = UnidadMedida::orderBy('nombre', 'ASC')->get();

            return ['success' => 1, 'herramienta' => $lista, 'unidad' => $arrayUnidad];
        }else{
            return ['success' => 2];
        }
    }


    public function editarMaterial(Request $request){

        $regla = array(
            'nombre' => 'required',
        );

        $validar = Validator::make($request->all(), $regla);

        if ($validar->fails()){ return ['success' => 0];}


        Log::info($request->all());

        Herramientas::where('id', $request->id)->update([
            'id_medida' => $request->unidad,
            'nombre' => $request->nombre,
            'codigo' => $request->codigo
        ]);

        return ['success' => 1];
    }


}
