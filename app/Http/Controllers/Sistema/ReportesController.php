<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Departamentos;
use App\Models\Entradas;
use App\Models\EntradasDetalle;
use App\Models\InformacionGeneral;
use App\Models\Materiales;
use App\Models\Salidas;
use App\Models\SalidasDetalle;
use App\Models\SalidasDetalleEntregas;
use App\Models\TipoProyecto;
use App\Models\Transferencia;
use App\Models\TransferenciaDetalle;
use App\Models\UnidadMedida;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ReportesController extends Controller
{



    public function vistaReporteGenerales()
    {
        return view('backend.reportes.vistareportegenerales');
    }


    public function generarPDFExistencias()
    {
        // ── Existencias actuales: entradas_detalle con cantidad disponible > 0 ──
        $arrayInfo = EntradasDetalle::with('material.objetoEspecifico', 'material.unidadMedida', 'entrada')
            ->get();

        $totalColumna   = 0;
        $arrayDetalle   = collect();
        $arrayPendientes = collect();

        foreach ($arrayInfo as $fila) {

            $totalSalido = SalidasDetalle::where('id_entrada_detalle', $fila->id)
                ->sum('cantidad_salida');

            $cantidadActual = (int) $fila->cantidad_inicial - (int) $totalSalido;

            // Solo mostrar si queda existencia
            if ($cantidadActual <= 0) {
                continue;
            }

            $material      = $fila->material;
            $objEspecifico = $material->objetoEspecifico ?? null;

            $multiplicado = $cantidadActual * $fila->precio;
            $totalColumna += $multiplicado;

            $arrayDetalle->push((object)[
                'lote'           => $fila->entrada->lote ?? '',
                'nombreMaterial' => $material->nombre ?? '',
                'unidadMedida'   => $material->unidadMedida->nombre ?? '',
                'cantidadActual' => $cantidadActual,
                'precioFormat'   => "$" . number_format((float) $fila->precio, 4, '.', ','),
                'multiplicado'   => "$" . number_format((float) $multiplicado, 2, '.', ','),
                'nombreCodigo'   => $objEspecifico->codigo ?? '',
            ]);

            // ── Items pendientes (kits abiertos) de este lote ──
            $pendientesQuery = SalidasDetalle::where('id_entrada_detalle', $fila->id)
                ->where('estado', 'pendiente')
                ->get();

            foreach ($pendientesQuery as $pend) {

                $entregas = SalidasDetalleEntregas::where('id_salida_detalle', $pend->id)
                    ->orderBy('fecha_entrega', 'asc')
                    ->get();

                $arrayPendientes->push((object)[
                    'nombreMaterial'  => $material->nombre ?? '',
                    'cantidad_salida' => $pend->cantidad_salida,
                    'unidadMedida'    => $material->unidadMedida->nombre ?? '',
                    'entregas'        => $entregas,
                ]);
            }
        }

        $totalColumnaFmt = "$" . number_format((float) $totalColumna, 2, '.', ',');
        $arrayDetalle    = $arrayDetalle->sortBy('nombreMaterial')->values();
        $arrayPendientes = $arrayPendientes->sortBy('nombreMaterial')->values();
        $fechaFormat     = date("d-m-Y", strtotime(Carbon::now('America/El_Salvador')));

        $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
        $mpdf->SetTitle('Existencias General');
        $mpdf->showImageErrors = false;

        $logoalcaldia = 'images/gobiernologo.jpg';
        $logosantaana = 'images/logo.png';

        $tabla = "
        <table style='width: 100%; border-collapse: collapse;'>
            <tr>
                <td style='width: 15%; text-align: left;'>
                    <img src='$logosantaana' alt='Santa Ana Norte' style='max-width: 100px; height: auto;'>
                </td>
                <td style='width: 60%; text-align: center;'>
                    <h1 style='font-size: 16px; margin: 0; color: #003366; text-transform: uppercase;'>ALCALDÍA MUNICIPAL DE SANTA ANA NORTE</h1>
                </td>
                <td style='width: 10%; text-align: right;'>
                    <img src='$logoalcaldia' alt='Gobierno de El Salvador' style='max-width: 60px; height: auto;'>
                </td>
            </tr>
        </table>
        <hr style='border: none; border-top: 2px solid #003366; margin: 0;'>
        ";

        $tabla .= "
        <div style='text-align: center; margin-top: 20px;'>
            <h1 style='font-size: 15px; margin: 0; color: #000;'>EXISTENCIAS</h1>
        </div>
        <div style='text-align: left; margin-top: 10px;'>
        <p style='font-size: 13px; margin: 0; color: #000;'>Fecha: $fechaFormat</p>
    </div>
    ";

        // ── Tabla principal: Producto, Cantidad, Obj. Específico ──
        $tabla .= "<table id='tablaFor' style='width: 100%; border-collapse: collapse; margin-top: 35px'>
    <tbody>
        <tr>
            <th style='text-align: center; font-size:15px; width: 80%; font-weight: bold; border: 1px solid black;'>Producto</th>
            <th style='text-align: center; font-size:15px; width: 15%; font-weight: bold; border: 1px solid black;'>Cantidad</th>
            <th style='text-align: center; font-size:15px; width: 14%; font-weight: bold; border: 1px solid black;'>Obj. Específico</th>
        </tr>
    ";

        foreach ($arrayDetalle as $fila) {
            $tabla .= "<tr>
                <td style='text-align: left; font-size:14px; border: 1px solid black; padding: 3px;'>$fila->nombreMaterial</td>
                <td style='text-align: center; font-size:14px; border: 1px solid black;'>$fila->cantidadActual</td>
                <td style='text-align: center; font-size:14px; border: 1px solid black;'>$fila->nombreCodigo</td>
            </tr> ";
        }

        $tabla .= "</tbody></table>";

        // ── Tabla de pendientes (kits abiertos) ──
        if ($arrayPendientes->isNotEmpty()) {

            $tabla .= "
            <div style='text-align: left; margin-top: 25px;'>
                <h1 style='font-size: 16px; margin: 0; color: #000;'>KITS PENDIENTES / ABIERTOS</h1>
            </div>
        ";

            $tabla .= "<table id='tablaPendientes' style='width: 100%; border-collapse: collapse; margin-top: 10px'>
        <tbody>
            <tr>
                <th style='text-align: center; font-size:15px; width: 35%; font-weight: bold; border: 1px solid black;'>Producto</th>
                <th style='text-align: center; font-size:15px; width: 12%; font-weight: bold; border: 1px solid black;'>Cant. Salida</th>
                <th style='text-align: center; font-size:15px; width: 53%; font-weight: bold; border: 1px solid black;'>Detalle de entregas (fecha — cantidad — descripción)</th>
            </tr>
        ";

            foreach ($arrayPendientes as $pend) {

                $detalleEntregas = '';

                if ($pend->entregas->isEmpty()) {
                    $detalleEntregas = "<span style='font-style: italic; color:#888;'>Sin entregas registradas</span>";
                } else {
                    $lineas = [];
                    foreach ($pend->entregas as $ent) {
                        $fechaEnt = date('d-m-Y', strtotime($ent->fecha_entrega));
                        $obs      = $ent->observacion ?: '—';
                        $lineas[] = "{$ent->cantidad} {$pend->unidadMedida} — $obs";
                    }
                    $detalleEntregas = implode('<br>', $lineas);
                }

                $tabla .= "<tr>
                    <td style='text-align: left; font-size:14px; border: 1px solid black; padding: 3px; vertical-align: top;'>$pend->nombreMaterial</td>
                    <td style='text-align: center; font-size:14px; border: 1px solid black; vertical-align: top;'>{$pend->cantidad_salida} {$pend->unidadMedida}</td>
                    <td style='text-align: left; font-size:14px; border: 1px solid black; padding: 3px;'>$detalleEntregas</td>
                </tr> ";
            }

            $tabla .= "</tbody></table>";
        }

        $stylesheet = file_get_contents('css/cssbodega.css');
        $mpdf->WriteHTML($stylesheet, 1);

        $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
        $mpdf->WriteHTML($tabla, 2);

        $mpdf->Output();
    }

}
