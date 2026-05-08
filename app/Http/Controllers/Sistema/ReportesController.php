<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\HistorialEntradas;
use App\Models\HistorialSalidas;
use App\Models\TipoProyecto;
use App\Models\UnidadMedida;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportesController extends Controller
{
    public function indexEntradaReporte(){
        return view('backend.admin.repuestos.reporte.vistaentradasalidareporte');
    }

    public function reportePdfEntradaSalida($tipo, $desde, $hasta){

        $start = Carbon::parse($desde)->startOfDay();
        $end = Carbon::parse($hasta)->endOfDay();

        $resultsBloque = array();
        $index = 0;

        $desdeFormat = date("d-m-Y", strtotime($desde));
        $hastaFormat = date("d-m-Y", strtotime($hasta));


        // entrada
        if($tipo == 1) {

            // lista de entradas
            $listaEntrada = HistorialEntradas::whereBetween('fecha', [$start, $end])
                ->orderBy('fecha', 'ASC')
                ->get();

            foreach ($listaEntrada as $ll){

                $ll->fecha = date("d-m-Y", strtotime($ll->fecha));

                $infoProyecto = TipoProyecto::where('id', $ll->id_tipoproyecto)->first();

                $ll->nombreproy = $infoProyecto->nombre;

                array_push($resultsBloque, $ll);

                // obtener detalle
                $listaDetalle = DB::table('historial_entradas_deta AS ed')
                    ->join('materiales AS m', 'ed.id_material', '=', 'm.id')
                    ->select('m.nombre', 'm.codigo', 'ed.cantidad', 'm.id_medida')
                    ->where('ed.id_historial', $ll->id)
                    ->orderBy('m.id', 'ASC')
                    ->get();

                foreach ($listaDetalle as $dd){
                    if($info = UnidadMedida::where('id', $dd->id_medida)->first()){
                        $dd->medida = $info->nombre;
                    }else{
                        $dd->medida = "";
                    }
                }

                $resultsBloque[$index]->detalle = $listaDetalle;
                $index++;
            }


            //$mpdf = new \Mpdf\Mpdf(['format' => 'LETTER']);
            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
            $mpdf->SetTitle('Entradas');

            // mostrar errores
            $mpdf->showImageErrors = false;

            $logoalcaldia = 'images/logo.png';


            $tabla = "
                <table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:6px;'>
                    <tr>
                        <td style='width:30%; border:0.8px solid #000; padding:6px 8px;'>
                            <table width='100%'>
                                <tr>
                                    <td style='width:35%; text-align:left;'>
                                        <img src='{$logoalcaldia}' style='height:40px'>
                                    </td>
                                    <td style='width:65%; text-align:left; color:#104e8c;
                                                font-size:12px; font-weight:bold; line-height:1.4;'>
                                        SANTA ANA NORTE<br>EL SALVADOR
                                    </td>
                                </tr>
                            </table>
                        </td>

                        <td style='width:70%; border:0.8px solid #000;
                                    padding:8px; text-align:center; vertical-align:middle;'>

                            <h2 style='margin:0;'>Reporte de Entradas</h2>
                            <p style='margin:0; font-size:12px;'>Fecha: $desdeFormat  -  $hastaFormat</p>

                        </td>
                    </tr>
                </table>
                ";


            foreach ($listaEntrada as $dd) {

                $tabla .= "<table width='100%' id='tablaFor'>
                <tbody>";

                $tabla .= "<tr>
                    <td style='font-weight: bold; width: 20%; font-size: 13px'>Fecha</td>
                     <td style='font-weight: bold; width: 45%; font-size: 13px'>Proyecto</td>
                     <td style='font-weight: bold; width: 15%; font-size: 13px'>Descripción</td>
                </tr>
                ";

                $tabla .= "<tr>
                    <td style='font-size: 12px'>$dd->fecha</td>
                     <td style='font-size: 12px'>$dd->nombreproy</td>
                     <td style='font-size: 12px'>$dd->descripcion</td>
                </tr>
                ";


                $tabla .= "</tbody></table>";

                $tabla .= "<table width='100%' id='tablaFor' style='margin-top: 20px'>
            <tbody>";

                $tabla .= "<tr>
                    <td style='font-weight: bold; width: 25%; font-size: 13px'>Repuesto</td>
                    <td style='font-weight: bold; width: 8%; font-size: 13px'>Medida</td>
                    <td style='font-weight: bold; width: 8%; font-size: 13px'>Cantidad</td>
                </tr>";

                foreach ($dd->detalle as $gg) {
                    $tabla .= "<tr>
                    <td style='font-size: 12px'>$gg->nombre</td>
                    <td style='font-size: 12px'>$gg->medida</td>
                    <td style='font-size: 12px'>$gg->cantidad</td>
                </tr>";
                }

                $tabla .= "</tbody></table>";
            }


            $tabla .= "<table width='100%' id='tablaFor'>
            <tbody>";

            $tabla .= "</tbody></table>";

            $stylesheet = file_get_contents('css/cssregistro.css');
            $mpdf->WriteHTML($stylesheet,1);

            $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
            //$mpdf->WriteHTML($tabla,2);
            $mpdf->WriteHTML($tabla, 2);

            $mpdf->Output();

        }else {
            // salida

            // lista de salidas
            $listaSalida = HistorialSalidas::whereBetween('fecha', [$start, $end])
                ->orderBy('fecha', 'ASC')
                ->get();

            foreach ($listaSalida as $ll){

                $infoProyecto = TipoProyecto::where('id', $ll->id_tipoproyecto)->first();

                $ll->nombreproy = $infoProyecto->nombre;

                $ll->fecha = date("d-m-Y", strtotime($ll->fecha));

                array_push($resultsBloque, $ll);

                // obtener detalle
                $listaDetalle = DB::table('historial_salidas_deta AS ed')
                    ->join('materiales AS m', 'ed.id_material', '=', 'm.id')
                    ->select( 'm.id', 'm.nombre', 'm.codigo', 'ed.cantidad', 'm.id_medida', 'ed.id_historial_salidas')
                    ->where('ed.id_historial_salidas', $ll->id)
                    ->orderBy('m.id', 'ASC')
                    ->get();

                foreach ($listaDetalle as $dd){
                    if($info = UnidadMedida::where('id', $dd->id_medida)->first()){
                        $dd->medida = $info->nombre;
                    }else{
                        $dd->medida = "";
                    }
                }

                $resultsBloque[$index]->detalle = $listaDetalle;
                $index++;
            }


            //$mpdf = new \Mpdf\Mpdf(['format' => 'LETTER']);
            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
            $mpdf->SetTitle('Salidas');

            // mostrar errores
            $mpdf->showImageErrors = false;

            $logoalcaldia = 'images/logo.png';

            $tabla = "
                <table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:6px;'>
                    <tr>
                        <td style='width:30%; border:0.8px solid #000; padding:6px 8px;'>
                            <table width='100%'>
                                <tr>
                                    <td style='width:35%; text-align:left;'>
                                        <img src='{$logoalcaldia}' style='height:40px'>
                                    </td>
                                    <td style='width:65%; text-align:left; color:#104e8c;
                                                font-size:12px; font-weight:bold; line-height:1.4;'>
                                        SANTA ANA NORTE<br>EL SALVADOR
                                    </td>
                                </tr>
                            </table>
                        </td>

                        <td style='width:70%; border:0.8px solid #000;
                                    padding:8px; text-align:center; vertical-align:middle;'>

                            <h2 style='margin:0;'>Reporte de Salidas</h2>
                            <p style='margin:0; font-size:12px;'>Fecha: $desdeFormat  -  $hastaFormat</p>

                        </td>
                    </tr>
                </table>
                ";

            foreach ($listaSalida as $dd) {

                $tabla .= "<table width='100%' id='tablaFor'>
                    <tbody>";

                $tabla .= "<tr>
                     <td  style='width: 20%; font-size: 13px; font-weight: bold'>Fecha</td>
                     <td  style='width: 45%; font-size: 13px; font-weight: bold'>Proyecto</td>
                     <td  style='width: 15%; font-size: 13px; font-weight: bold'>Descripción</td>
                </tr>
                ";

                $tabla .= "<tr>
                    <td style='width: 20%; font-size: 12px'>$dd->fecha</td>
                     <td style='width: 45%; font-size: 12px'>$dd->nombreproy</td>
                     <td style='width: 15%; font-size: 12px'>$dd->descripcion</td>
                </tr>
                ";


                $tabla .= "</tbody></table>";

                $tabla .= "<table width='100%' id='tablaFor' style='margin-top: 20px'>
            <tbody>";

                $tabla .= "<tr>
                    <td style='width: 25%; font-size: 13px; font-weight: bold'>Repuesto</td>
                    <td style='width: 8%; font-size: 13px; font-weight: bold'>Medida</td>
                    <td style='width: 20%; font-size: 13px; font-weight: bold'>Cantidad</td>
                </tr>";

                foreach ($dd->detalle as $gg) {
                    $tabla .= "<tr>
                        <td style='width: 25%; font-size: 12px'>$gg->nombre</td>
                        <td style='width: 8%; font-size: 12px'>$gg->medida</td>
                        <td style='width: 20%; font-size: 12px'>$gg->cantidad</td>
                    </tr>";
                }

                $tabla .= "</tbody></table>";
            }


            $stylesheet = file_get_contents('css/cssregistro.css');
            $mpdf->WriteHTML($stylesheet,1);

            $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
            $mpdf->WriteHTML($tabla,2);

            $mpdf->Output();
        }

    }

}
