<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Entradas;
use App\Models\HistorialSalidas;
use App\Models\HistorialSalidasDeta;
use App\Models\HistorialTransferido;
use App\Models\HistorialTransferidoDetalle;
use App\Models\Materiales;
use App\Models\TipoProyecto;
use App\Models\UnidadMedida;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SalidasController extends Controller
{

    public function indexRegistroSalida(){

        $tipoproyecto = TipoProyecto::orderBy('nombre')->get();
        return view('backend.admin.repuestos.salidas.vistasalidaregistro', compact('tipoproyecto'));
    }

    public function guardarSalida(Request $request){

        $rules = array(
            'fecha' => 'required',
        );

        $validator = Validator::make($request->all(), $rules);
        if ( $validator->fails()){
            return ['success' => 0];
        }

        DB::beginTransaction();

        try {

            // GUARDAR UN HISTORIAL DE SALIDAS

            $histoSalida = new HistorialSalidas();
            $histoSalida->fecha = $request->fecha;
            $histoSalida->descripcion = $request->descripcion;
            $histoSalida->id_tipoproyecto = $request->idproyecto;
            $histoSalida->save();


            // HOY GUARDAR HISTORIAL DEL DETALLE
            for ($i = 0; $i < count($request->salida); $i++) {

                $histoDetalle = new HistorialSalidasDeta();
                $histoDetalle->id_historial_salidas = $histoSalida->id;
                $histoDetalle->id_material = $request->idmaterial[$i];
                $histoDetalle->cantidad = $request->salida[$i];
                $histoDetalle->save();
            }


            for ($i = 0; $i < count($request->salida); $i++) {

                if($infoEntrada = Entradas::where('id_material', $request->idmaterial[$i])
                    ->where('id_tipoproyecto', $request->idproyecto)
                    ->first()){

                    $resta = $infoEntrada->cantidad - $request->salida[$i];

                    if($resta < 0){
                        // ERROR, LA CANTIDAD NO PUEDE SER MENOR A 0
                        return ['success' => 1, 'fila' => ($i),
                            'cantidadactual' => $infoEntrada->cantidad,
                            'cantidadrestar' => $request->salida[$i]];
                    }

                    // RESTAR LA CANTIDAD
                    Entradas::where('id', $infoEntrada->id)->update([
                        'cantidad' => $resta
                    ]);
                }else{

                    // ERROR MATERIAL NO ENCONTRADO EN PROYECTO
                    return ['success' => 2, 'fila' => ($i)];
                }
            }


            // CORRECTO

            DB::commit();
            return ['success' => 3];

        }catch(\Throwable $e){
            Log::info('ee' . $e);
            DB::rollback();
            return ['success' => 99];
        }
    }


    // BUSCADOR DE MATERIALES QUE TENGAN CANTIDAD MAYOR A 0 SEGUN ID DEL PROYECTO
    public function buscadorMaterialPorProyecto(Request $request){

        if($request->get('query')){

            $nombre = $request->get('query');

            $idproyecto = $request->tipoproyecto;


            // BUSCAR MATERIAL QUE COINCIDA CON NOMBRE O CODIGO DE LAS ENTRADAS DE UN ID PROYECTO
            /*$arrayEntradas = Materiales::where('nombre', 'LIKE', "%{$nombre}%")
                ->orWhere('codigo', 'LIKE', "%{$nombre}%")
                ->get();*/


            $arrayEntradas = DB::table('entradas AS en')
                ->join('materiales AS ma', 'en.id_material', '=', 'ma.id')
                ->select('ma.nombre', 'ma.id', 'ma.codigo', 'en.cantidad', 'en.id_tipoproyecto')
                ->where('en.id_tipoproyecto', $idproyecto)
                ->where(function ($query) use ($nombre) {
                    $query->where('ma.nombre', 'like', "%{$nombre}%")
                        ->orWhere('ma.codigo', 'like', "%{$nombre}%");
                })
                ->orderBy('ma.nombre', 'ASC')
                ->get();

            $pilaArrayIdMaterial = array();

            // FILTRAR MATERIALES QUE TENGAN CANTIDAD
            foreach ($arrayEntradas as $data){

                // VERIFICAR QUE LA CANTIDAD SEA MAYOR A 0 PARA PODER MOSTRARLO

                if($data->cantidad > 0){
                    array_push($pilaArrayIdMaterial, $data->id);
                }
            }


            $filtrado = Materiales::whereIn('id', $pilaArrayIdMaterial)->get();

            foreach ($filtrado as $dd){
                if($info = UnidadMedida::where('id', $dd->id_medida)->first()){
                    $dd->medida = "- " . $info->nombre;
                }else{
                    $dd->medida = "";
                }

                if($dd->codigo != null){
                    $dd->code = "- " . $dd->codigo;
                }else{
                    $dd->code = "";
                }
            }

            $output = '<ul class="dropdown-menu" style="display:block; position:relative;">';
            $tiene = true;
            foreach($filtrado as $row){

                // si solo hay 1 fila, No mostrara el hr, salto de linea
                if(count($filtrado) == 1){
                    if(!empty($row)){
                        $tiene = false;
                        $output .= '
                 <li onclick="modificarValor(this)" id="'.$row->id.'"><a href="#" style="margin-left: 3px">'.$row->nombre . '  ' .$row->medida . ' ' .$row->code .'</a></li>
                ';
                    }
                }

                else{
                    if(!empty($row)){
                        $tiene = false;
                        $output .= '
                 <li onclick="modificarValor(this)" id="'.$row->id.'"><a href="#" style="margin-left: 3px">'.$row->nombre . ' ' .$row->medida . ' ' .$row->code .'</a></li>
                   <hr>
                ';
                    }
                }
            }
            $output .= '</ul>';
            if($tiene){
                $output = '';
            }
            echo $output;
        }
    }


    public function bloqueCantidades(Request $request){

        // OBTENER CANTIDAD DEL ITEM SELECCIONADO


        if($infoEntrada = Entradas::where('id_material', $request->idmaterial)
            ->where('id_tipoproyecto', $request->idproy)
            ->first()){
            // MATERIAL ENCONTRADO

            $infoMaterial = Materiales::where('id', $request->idmaterial)->first();

            $nombremedida = "";
            if($infoMedida = UnidadMedida::where('id', $infoMaterial->id_medida)->first())
            {
                $nombremedida = $infoMedida->nombre;
            }

            return ['success' => 1,
                'infomaterial' => $infoMaterial,
                'medida' => $nombremedida,
                'cantidad' => $infoEntrada->cantidad];
        }else{
            return ['success' => 2,];
        }
    }




    // *****************************

    public function indexTransferencias(){

        // LISTADO DE PROYECTOS (MENOS EL ID 1 YA QUE SERA EL INVENTARIO GENERAL)
        // Y QUE NO HAYAN SIDO TRANSFERIDOS

        $tipoproyecto = TipoProyecto::orderBy('nombre')
            ->where('id', '!=', 1)
            ->where('transferido', '!=', 1)
            ->get();

        return view('backend.admin.repuestos.registros.vistatransferidos', compact('tipoproyecto'));
    }


    public function geenrarSalidaTransferencia(Request $request){

        $rules = array(
            'fecha' => 'required',
        );

        $validator = Validator::make($request->all(), $rules);
        if ( $validator->fails()){
            return ['success' => 0];
        }

        DB::beginTransaction();

        try {

            // EVITAR QUE SEA TRANSFERIDO 2 VECES
            if(TipoProyecto::where('id', $request->idproyecto)
                ->where('transferido', 1)->first()){

                return ['success' => 1];
            }

            // ESTABLECER A TRANSFERIDO
            TipoProyecto::where('id', $request->idproyecto)->update([
                'transferido' => 1
            ]);



            if ($request->hasFile('documento')) {

                $cadena = Str::random(15);
                $tiempo = microtime();
                $union = $cadena . $tiempo;
                $nombre = str_replace(' ', '_', $union);

                $extension = '.' . $request->documento->getClientOriginalExtension();
                $nomDocumento = $nombre . strtolower($extension);
                $avatar = $request->file('documento');
                $archivo = Storage::disk('archivos')->put($nomDocumento, \File::get($avatar));

                if ($archivo) {


                    // GUARDAR UN HISTORIAL DE TRANSFERENCIA

                    $histoSalida = new HistorialTransferido();
                    $histoSalida->fecha = $request->fecha;
                    $histoSalida->descripcion = $request->descripcion;
                    $histoSalida->id_tipoproyecto = $request->idproyecto;
                    $histoSalida->documento = $nomDocumento;
                    $histoSalida->save();

                    // HOY GUARDAR HISTORIAL DEL DETALLE DE LAS CANTIDADES MAYOR A 0
                    $arrayMateriales = Entradas::where('id_tipoproyecto', $request->idproyecto)->get();

                    $boolHayMateriales = false;

                    foreach ($arrayMateriales as $info){

                        if($info->cantidad > 0){
                            $boolHayMateriales = true;

                            $histoDetalle = new HistorialTransferidoDetalle();
                            $histoDetalle->id_historial_transf = $histoSalida->id;
                            $histoDetalle->id_material = $info->id_material;
                            $histoDetalle->cantidad = $info->cantidad;
                            $histoDetalle->save();


                            // ACTUALIZAR CANTIDAD A INVENTARIO GENERAL O AGREGAR EL NUEVO MATERIAL SINO EXISTE

                            if($infoEn = Entradas::where('id_tipoproyecto', 1)->where('id_material', $info->id_material)
                                ->first()){
                                // EXISTE EN INVENTARIO GENERAL, ASI QUE SOLO SUMAR LA CANTIDAD
                                $suma = $infoEn->cantidad + $info->cantidad;

                                Entradas::where('id', $infoEn->id)->update([
                                    'cantidad' => $suma
                                ]);

                            }else{
                                //MATERIAL NO EXISTE EN INVENTARIO GENERAL, CREAR NUEVO MATERIAL

                                $nuevo = new Entradas();
                                $nuevo->id_material = $info->id_material;
                                $nuevo->id_tipoproyecto = 1;
                                $nuevo->cantidad = $info->cantidad;
                                $nuevo->save();
                            }

                            // ELIMINAR LA CANTIDAD DEL PROYECTO QUE TENIA
                            Entradas::where('id', $info->id)->update([
                                'cantidad' => 0
                            ]);
                        }
                    }

                    if(!$boolHayMateriales){
                        // EL PROYECTO NO TIENE MATERIALES O NO TIENE CANTIDAD NINGUNO
                        return ['success' => 2];
                    }


                    // CORRECTO

                    DB::commit();
                    return ['success' => 3];


                }
            }else{


                // GUARDAR UN HISTORIAL DE TRANSFERENCIA

                $histoSalida = new HistorialTransferido();
                $histoSalida->fecha = $request->fecha;
                $histoSalida->descripcion = $request->descripcion;
                $histoSalida->id_tipoproyecto = $request->idproyecto;
                $histoSalida->save();

                // HOY GUARDAR HISTORIAL DEL DETALLE DE LAS CANTIDADES MAYOR A 0
                $arrayMateriales = Entradas::where('id_tipoproyecto', $request->idproyecto)->get();

                $boolHayMateriales = false;

                foreach ($arrayMateriales as $info){

                    if($info->cantidad > 0){
                        $boolHayMateriales = true;

                        $histoDetalle = new HistorialTransferidoDetalle();
                        $histoDetalle->id_historial_transf = $histoSalida->id;
                        $histoDetalle->id_material = $info->id_material;
                        $histoDetalle->cantidad = $info->cantidad;
                        $histoDetalle->save();


                        // ACTUALIZAR CANTIDAD A INVENTARIO GENERAL O AGREGAR EL NUEVO MATERIAL SINO EXISTE

                        if($infoEn = Entradas::where('id_tipoproyecto', 1)->where('id_material', $info->id_material)
                            ->first()){
                            // EXISTE EN INVENTARIO GENERAL, ASI QUE SOLO SUMAR LA CANTIDAD
                            $suma = $infoEn->cantidad + $info->cantidad;

                            Entradas::where('id', $infoEn->id)->update([
                                'cantidad' => $suma
                            ]);

                        }else{
                            //MATERIAL NO EXISTE EN INVENTARIO GENERAL, CREAR NUEVO MATERIAL

                            $nuevo = new Entradas();
                            $nuevo->id_material = $info->id_material;
                            $nuevo->id_tipoproyecto = 1;
                            $nuevo->cantidad = $info->cantidad;
                            $nuevo->save();
                        }

                        // ELIMINAR LA CANTIDAD DEL PROYECTO QUE TENIA
                        Entradas::where('id', $info->id)->update([
                            'cantidad' => 0
                        ]);
                    }
                }

                if(!$boolHayMateriales){
                    // EL PROYECTO NO TIENE MATERIALES O NO TIENE CANTIDAD NINGUNO
                    return ['success' => 2];
                }


                // CORRECTO

                DB::commit();
                return ['success' => 3];
            }

        }catch(\Throwable $e){
            Log::info('ee' . $e);
            DB::rollback();
            return ['success' => 99];
        }
    }


}
