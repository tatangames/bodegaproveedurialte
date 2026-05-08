<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\HistorialSalidas;
use App\Models\HistorialSalidasDeta;
use App\Models\Materiales;
use App\Models\TipoProyecto;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HistorialController extends Controller
{

    public function indexHistorialRepuestosSalida(){

        return view('backend.admin.historial.salidarepuesto.vistasalidarepuesto');
    }


    public function tablaHistorialRepuestosSalida(){

        $lista = HistorialSalidas::with('tipoproyecto') // 👈 eager loading
        ->orderBy('fecha', 'DESC')
            ->get()
            ->map(function($dato){
                $dato->fecha = Carbon::parse($dato->fecha)->format('d-m-Y');
                $dato->nomproy = $dato->tipoproyecto->nombre;
                return $dato;
            });

        return view('backend.admin.historial.salidarepuesto.tablasalidarepuesto', compact('lista'));
    }


    public function informacionHistorialSalidaRepuesto(Request $request){

        $regla = array(
            'id' => 'required',
        );

        $validar = Validator::make($request->all(), $regla);

        if ($validar->fails()){ return ['success' => 0];}

        if($lista = HistorialSalidas::where('id', $request->id)->first()){


            return ['success' => 1, 'info' => $lista];
        }else{
            return ['success' => 2];
        }

    }



    public function actualizarHistorialSalidaRepuesto(Request $request){


        $regla = array(
            'id' => 'required',
            'fecha' => 'required',
        );

        $validar = Validator::make($request->all(), $regla);

        if ($validar->fails()){ return ['success' => 0];}

        if(HistorialSalidas::where('id', $request->id)->first()){

            HistorialSalidas::where('id', $request->id)->update([
                'fecha' => $request->fecha,
                'descripcion' => $request->descripcion,
            ]);

            return ['success' => 1];
        }else{
            return ['success' => 2];
        }
    }


    public function detalleIndexHistorialSalidas($id){

        return view('backend.admin.historial.salidarepuesto.detalle.vistadetalle', compact('id'));
    }


    public function detalleTablaHistorialSalidas($id){

        $lista = HistorialSalidasDeta::where('id_historial_salidas', $id)->get();

        foreach ($lista as $dato){

            $infoMate = Materiales::where('id', $dato->id_material)->first();

            $dato->nommaterial = $infoMate->nombre;
            $dato->codmaterial = $infoMate->codigo;
        }

        return view('backend.admin.historial.salidarepuesto.detalle.tabladetalle', compact('lista'));
    }



}
