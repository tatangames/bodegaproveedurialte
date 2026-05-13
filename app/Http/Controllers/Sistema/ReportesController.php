<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Entradas;
use App\Models\EntradasDetalle;
use App\Models\HistorialEntradas;
use App\Models\HistorialEntradasDeta;
use App\Models\HistorialSalidas;
use App\Models\HistorialSalidasDeta;
use App\Models\HistorialTransferido;
use App\Models\HistorialTransferidoDetalle;
use App\Models\Materiales;
use App\Models\SalidasDetalle;
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

    public function reportePdfEntradaSalida($tipo, $desde = null, $hasta = null)
    {
        $sinFiltro = ($desde === 'todos' || $desde === null);

        $desdeFormat = $sinFiltro ? '' : date("d-m-Y", strtotime($desde));
        $hastaFormat = $sinFiltro ? '' : date("d-m-Y", strtotime($hasta));

        $logoalcaldia = 'images/logo.png';
        $periodoTexto = $sinFiltro ? 'Todo el historial' : "Fecha: $desdeFormat  -  $hastaFormat";

        // ── ENTRADAS ──────────────────────────────────────────────────
        if ($tipo == 1) {

            $query = Entradas::with([
                'tipoproyecto',
                'detalle.material.unidadMedida',
            ])->orderBy('fecha', 'ASC');

            if (!$sinFiltro) {
                $start = date('Y-m-d 00:00:00', strtotime($desde));
                $end   = date('Y-m-d 23:59:59', strtotime($hasta));
                $query->whereBetween('fecha', [$start, $end]);
            }

            $listaEntrada = $query->get();

            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
            $mpdf->SetTitle('Entradas');
            $mpdf->showImageErrors = false;

            $tabla = "
        <table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:6px;'>
            <tr>
                <td style='width:30%; border:1px solid #000; padding:6px 8px;'>
                    <table width='100%'>
                        <tr>
                            <td style='width:35%; text-align:left;'>
                                <img src='{$logoalcaldia}' style='height:40px'>
                            </td>
                            <td style='width:65%; text-align:left; color:#104e8c; font-size:12px; font-weight:bold; line-height:1.4;'>
                                SANTA ANA NORTE<br>EL SALVADOR
                            </td>
                        </tr>
                    </table>
                </td>
                <td style='width:70%; border:1px solid #000; padding:8px; text-align:center; vertical-align:middle;'>
                    <h2 style='margin:0;'>Reporte de Entradas</h2>
                    <p style='margin:0; font-size:12px;'>{$periodoTexto}</p>
                </td>
            </tr>
        </table>
        ";

            foreach ($listaEntrada as $entrada) {

                $fechaFmt   = date("d-m-Y", strtotime($entrada->fecha));
                $proyecto   = $entrada->tipoproyecto->nombre ?? '—';
                $descripcion = $entrada->descripcion ?? '—';
                $factura    = $entrada->factura ?? '—';

                $tabla .= "
            <table width='100%' style='border-collapse:collapse; margin-bottom:4px;'>
                <tbody>
                    <tr>
                        <td style='width:15%; font-size:13px; font-weight:bold; border:1px solid #000; padding:4px 6px;'>Fecha</td>
                        <td style='width:15%; font-size:13px; font-weight:bold; border:1px solid #000; padding:4px 6px;'>Factura</td>
                        <td style='width:35%; font-size:13px; font-weight:bold; border:1px solid #000; padding:4px 6px;'>Proyecto</td>
                        <td style='width:35%; font-size:13px; font-weight:bold; border:1px solid #000; padding:4px 6px;'>Descripción</td>
                    </tr>
                    <tr>
                        <td style='width:15%; font-size:12px; border:1px solid #000; padding:4px 6px;'>$fechaFmt</td>
                        <td style='width:15%; font-size:12px; border:1px solid #000; padding:4px 6px;'>$factura</td>
                        <td style='width:35%; font-size:12px; border:1px solid #000; padding:4px 6px;'>$proyecto</td>
                        <td style='width:35%; font-size:12px; border:1px solid #000; padding:4px 6px;'>$descripcion</td>
                    </tr>
                </tbody>
            </table>

            <table width='100%' style='margin-top:6px; border-collapse:collapse;'>
                <thead>
                    <tr>
                        <td style='font-weight:bold; width:10%; font-size:12px; padding:4px 6px; border:1px solid #000;'>Código</td>
                        <td style='font-weight:bold; width:35%; font-size:12px; padding:4px 6px; border:1px solid #000;'>Material</td>
                        <td style='font-weight:bold; width:15%; font-size:12px; padding:4px 6px; border:1px solid #000;'>Medida</td>
                        <td style='font-weight:bold; width:10%; font-size:12px; padding:4px 6px; text-align:center; border:1px solid #000;'>Cantidad</td>
                        <td style='font-weight:bold; width:15%; font-size:12px; padding:4px 6px; text-align:right; border:1px solid #000;'>Precio Unit.</td>
                        <td style='font-weight:bold; width:15%; font-size:12px; padding:4px 6px; text-align:right; border:1px solid #000;'>Total</td>
                    </tr>
                </thead>
                <tbody>
            ";

                $totalGeneral = 0;

                foreach ($entrada->detalle as $det) {
                    $codigo       = $det->codigo ?? '—';
                    $nombreMat    = $det->material->nombre ?? '—';
                    $medida       = $det->material->medida->nombre ?? '—';
                    $cantidad     = $det->cantidad_inicial;
                    $precio       = $det->precio;
                    $total        = $cantidad * $precio;
                    $totalGeneral += $total;

                    $precioFormat = number_format($precio, 4);
                    $totalFormat  = number_format($total,  4);

                    $tabla .= "
                    <tr>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000;'>$codigo</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000;'>$nombreMat</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000;'>$medida</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:center;'>$cantidad</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:right;'>$ $precioFormat</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:right;'>$ $totalFormat</td>
                    </tr>
                ";
                }

                $totalGeneralFormat = number_format($totalGeneral, 4);

                $tabla .= "
                    <tr>
                        <td colspan='5' style='font-weight:bold; font-size:12px; padding:4px 6px; text-align:right; border:1px solid #000;'>
                            Total General:
                        </td>
                        <td style='font-weight:bold; font-size:12px; padding:4px 6px; text-align:right; border:1px solid #000;'>
                            $ $totalGeneralFormat
                        </td>
                    </tr>
                </tbody>
            </table>
            <br>
            ";
            }

            $stylesheet = file_get_contents('css/cssregistro.css');
            $mpdf->WriteHTML($stylesheet, 1);
            $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
            $mpdf->WriteHTML($tabla, 2);
            $mpdf->Output();

            // ── SALIDAS ───────────────────────────────────────────────────
        } else {

            $query = \App\Models\Salidas::with([
                'tipoproyecto',
                'detalle.entradaDetalle.material.unidadMedida',
            ])->orderBy('fecha', 'ASC');

            if (!$sinFiltro) {
                $start = date('Y-m-d 00:00:00', strtotime($desde));
                $end   = date('Y-m-d 23:59:59', strtotime($hasta));
                $query->whereBetween('fecha', [$start, $end]);
            }

            $listaSalida = $query->get();

            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
            $mpdf->SetTitle('Salidas');
            $mpdf->showImageErrors = false;

            $tabla = "
        <table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:6px;'>
            <tr>
                <td style='width:30%; border:1px solid #000; padding:6px 8px;'>
                    <table width='100%'>
                        <tr>
                            <td style='width:35%; text-align:left;'>
                                <img src='{$logoalcaldia}' style='height:40px'>
                            </td>
                            <td style='width:65%; text-align:left; color:#104e8c; font-size:12px; font-weight:bold; line-height:1.4;'>
                                SANTA ANA NORTE<br>EL SALVADOR
                            </td>
                        </tr>
                    </table>
                </td>
                <td style='width:70%; border:1px solid #000; padding:8px; text-align:center; vertical-align:middle;'>
                    <h2 style='margin:0;'>Reporte de Salidas</h2>
                    <p style='margin:0; font-size:12px;'>{$periodoTexto}</p>
                </td>
            </tr>
        </table>
        ";

            foreach ($listaSalida as $salida) {

                $fechaFmt    = date("d-m-Y", strtotime($salida->fecha));
                $proyecto    = $salida->tipoproyecto->nombre ?? '—';
                $descripcion = $salida->descripcion ?? '—';

                $tabla .= "
            <table width='100%' style='border-collapse:collapse; margin-bottom:4px;'>
                <tbody>
                    <tr>
                        <td style='width:20%; font-size:13px; font-weight:bold; border:1px solid #000; padding:4px 6px;'>Fecha</td>
                        <td style='width:45%; font-size:13px; font-weight:bold; border:1px solid #000; padding:4px 6px;'>Proyecto</td>
                        <td style='width:35%; font-size:13px; font-weight:bold; border:1px solid #000; padding:4px 6px;'>Descripción</td>
                    </tr>
                    <tr>
                        <td style='width:20%; font-size:12px; border:1px solid #000; padding:4px 6px;'>$fechaFmt</td>
                        <td style='width:45%; font-size:12px; border:1px solid #000; padding:4px 6px;'>$proyecto</td>
                        <td style='width:35%; font-size:12px; border:1px solid #000; padding:4px 6px;'>$descripcion</td>
                    </tr>
                </tbody>
            </table>

            <table width='100%' style='margin-top:6px; border-collapse:collapse;'>
                <thead>
                    <tr>
                        <td style='font-weight:bold; width:10%; font-size:12px; padding:4px 6px; border:1px solid #000;'>Código</td>
                        <td style='font-weight:bold; width:35%; font-size:12px; padding:4px 6px; border:1px solid #000;'>Material</td>
                        <td style='font-weight:bold; width:15%; font-size:12px; padding:4px 6px; border:1px solid #000;'>Medida</td>
                        <td style='font-weight:bold; width:10%; font-size:12px; padding:4px 6px; text-align:center; border:1px solid #000;'>Cantidad</td>
                        <td style='font-weight:bold; width:15%; font-size:12px; padding:4px 6px; text-align:right; border:1px solid #000;'>Precio Unit.</td>
                        <td style='font-weight:bold; width:15%; font-size:12px; padding:4px 6px; text-align:right; border:1px solid #000;'>Total</td>
                    </tr>
                </thead>
                <tbody>
            ";

                $totalGeneral = 0;

                foreach ($salida->detalle as $det) {
                    $entDet       = $det->entradaDetalle;
                    $codigo       = $entDet->codigo ?? '—';
                    $nombreMat    = $entDet->material->nombre ?? '—';
                    $medida       = $entDet->material->medida->nombre ?? '—';
                    $cantidad     = $det->cantidad_salida;
                    $precio       = $entDet->precio ?? 0;
                    $total        = $cantidad * $precio;
                    $totalGeneral += $total;

                    $precioFormat = number_format($precio, 4);
                    $totalFormat  = number_format($total,  4);

                    $tabla .= "
                    <tr>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000;'>$codigo</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000;'>$nombreMat</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000;'>$medida</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:center;'>$cantidad</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:right;'>$ $precioFormat</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:right;'>$ $totalFormat</td>
                    </tr>
                ";
                }

                $totalGeneralFormat = number_format($totalGeneral, 4);

                $tabla .= "
                    <tr>
                        <td colspan='5' style='font-weight:bold; font-size:12px; padding:4px 6px; text-align:right; border:1px solid #000;'>
                            Total General:
                        </td>
                        <td style='font-weight:bold; font-size:12px; padding:4px 6px; text-align:right; border:1px solid #000;'>
                            $ $totalGeneralFormat
                        </td>
                    </tr>
                </tbody>
            </table>
            <br>
            ";
            }

            $stylesheet = file_get_contents('css/cssregistro.css');
            $mpdf->WriteHTML($stylesheet, 1);
            $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
            $mpdf->WriteHTML($tabla, 2);
            $mpdf->Output();
        }
    }




    public function vistaParaReporteInventario(){
        return view('backend.admin.repuestos.reporte.vistareporteinventario');
    }


    public function reporteInventarioActual($tipo)
    {
        $logoalcaldia = 'images/logo.png';
        $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
        $mpdf->showImageErrors = false;

        $encabezado = "
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
                <h2 style='margin:0;'>Inventario de Materiales</h2>
            </td>
        </tr>
    </table>";

        // ── TIPO 1: INVENTARIO GENERAL ────────────────────────────────
        if ($tipo == 1) {

            $mpdf->SetTitle('Inventario General');

            $materiales = Materiales::with('unidadMedida')
                ->orderBy('nombre')
                ->get();

            $granTotal = 0;
            $tabla = $encabezado;

            $tabla .= "
        <table width='100%' style='border-collapse:collapse; margin-top:10px;'>
            <thead>
                <tr>
                    <td style='font-weight:bold; width:10%; font-size:12px; padding:4px 6px; border:1px solid #000;'>Código</td>
                    <td style='font-weight:bold; width:35%; font-size:12px; padding:4px 6px; border:1px solid #000;'>Material</td>
                    <td style='font-weight:bold; width:15%; font-size:12px; padding:4px 6px; border:1px solid #000;'>Medida</td>
                    <td style='font-weight:bold; width:10%; font-size:12px; padding:4px 6px; text-align:center; border:1px solid #000;'>Entradas</td>
                    <td style='font-weight:bold; width:10%; font-size:12px; padding:4px 6px; text-align:center; border:1px solid #000;'>Salidas</td>
                    <td style='font-weight:bold; width:10%; font-size:12px; padding:4px 6px; text-align:center; border:1px solid #000;'>Stock</td>
                    <td style='font-weight:bold; width:10%; font-size:12px; padding:4px 6px; text-align:right; border:1px solid #000;'>Precio Unit.</td>
                    <td style='font-weight:bold; width:10%; font-size:12px; padding:4px 6px; text-align:right; border:1px solid #000;'>Total ($)</td>
                </tr>
            </thead>
            <tbody>
        ";

            foreach ($materiales as $mat) {

                $idsDetalle = EntradasDetalle::where('id_material', $mat->id)->pluck('id');

                $totalEntradas = EntradasDetalle::where('id_material', $mat->id)
                    ->sum('cantidad_inicial');

                $totalSalidas = SalidasDetalle::whereIn('id_entrada_detalle', $idsDetalle)
                    ->sum('cantidad_salida');

                $stock = $totalEntradas - $totalSalidas;

                if ($stock <= 0) continue;

                $ultimoDetalle = EntradasDetalle::where('id_material', $mat->id)
                    ->orderBy('id', 'DESC')
                    ->first();

                $precioUnit   = $ultimoDetalle->precio ?? 0;
                $totalLinea   = $stock * $precioUnit;
                $granTotal   += $totalLinea;

                $codigo       = '—';
                $medida       = $mat->unidadMedida->nombre ?? '—';  // ← corregido
                $precioFormat = number_format($precioUnit, 4);
                $totalFormat  = number_format($totalLinea, 4);

                $tabla .= "
                <tr>
                    <td style='font-size:11px; padding:3px 6px; border:1px solid #000;'>$codigo</td>
                    <td style='font-size:11px; padding:3px 6px; border:1px solid #000;'>$mat->nombre</td>
                    <td style='font-size:11px; padding:3px 6px; border:1px solid #000;'>$medida</td>
                    <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:center;'>$totalEntradas</td>
                    <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:center;'>$totalSalidas</td>
                    <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:center;'>$stock</td>
                    <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:right;'>$ $precioFormat</td>
                    <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:right;'>$ $totalFormat</td>
                </tr>
            ";
            }

            $granTotalFmt = number_format($granTotal, 4);

            $tabla .= "
                <tr>
                    <td colspan='7' style='font-weight:bold; font-size:12px; padding:4px 6px;
                                           text-align:right; border:1px solid #000;'>
                        TOTAL GENERAL:
                    </td>
                    <td style='font-weight:bold; font-size:12px; padding:4px 6px;
                                text-align:right; border:1px solid #000;'>
                        $ $granTotalFmt
                    </td>
                </tr>
            </tbody>
        </table>";

            // ── TIPO 2: INVENTARIO POR PROYECTO ──────────────────────────
        } else {

            $mpdf->SetTitle('Inventario por Proyecto');

            $proyectos = Tipoproyecto::orderBy('nombre')->get();

            $granTotal = 0;
            $tabla = $encabezado;

            foreach ($proyectos as $proyecto) {

                $detalles = EntradasDetalle::with('material.unidadMedida')  // ← corregido
                ->whereHas('entrada', fn($q) => $q->where('id_tipoproyecto', $proyecto->id))
                    ->get();

                if ($detalles->isEmpty()) continue;

                $porMaterial = [];

                foreach ($detalles as $det) {
                    $idMat = $det->id_material;

                    if (!isset($porMaterial[$idMat])) {
                        $porMaterial[$idMat] = [
                            'nombre'   => $det->material->nombre ?? '—',
                            'medida'   => $det->material->unidadMedida->nombre ?? '—',  // ← corregido
                            'codigo'   => $det->codigo ?? '—',
                            'entradas' => 0,
                            'salidas'  => 0,
                            'precio'   => 0,
                        ];
                    }

                    $porMaterial[$idMat]['entradas'] += $det->cantidad_inicial;
                    $porMaterial[$idMat]['precio']    = $det->precio;

                    $salidas = SalidasDetalle::where('id_entrada_detalle', $det->id)
                        ->sum('cantidad_salida');
                    $porMaterial[$idMat]['salidas'] += $salidas;
                }

                $porMaterial = array_filter($porMaterial, fn($m) => ($m['entradas'] - $m['salidas']) > 0);

                if (empty($porMaterial)) continue;

                $tabla .= "
            <table width='100%' style='border-collapse:collapse; margin-bottom:4px; margin-top:12px;'>
                <tr>
                    <td style='font-weight:bold; font-size:13px; padding:4px 6px;
                                border:1px solid #000; background:#e8eef8;'>
                        Proyecto: $proyecto->nombre
                    </td>
                </tr>
            </table>

            <table width='100%' style='border-collapse:collapse; margin-bottom:8px;'>
                <thead>
                    <tr>
                        <td style='font-weight:bold; width:10%; font-size:12px; padding:4px 6px; border:1px solid #000;'>Código</td>
                        <td style='font-weight:bold; width:35%; font-size:12px; padding:4px 6px; border:1px solid #000;'>Material</td>
                        <td style='font-weight:bold; width:13%; font-size:12px; padding:4px 6px; border:1px solid #000;'>Medida</td>
                        <td style='font-weight:bold; width:8%; font-size:12px; padding:4px 6px; text-align:center; border:1px solid #000;'>Entradas</td>
                        <td style='font-weight:bold; width:8%; font-size:12px; padding:4px 6px; text-align:center; border:1px solid #000;'>Salidas</td>
                        <td style='font-weight:bold; width:8%; font-size:12px; padding:4px 6px; text-align:center; border:1px solid #000;'>Stock</td>
                        <td style='font-weight:bold; width:10%; font-size:12px; padding:4px 6px; text-align:right; border:1px solid #000;'>Precio Unit.</td>
                        <td style='font-weight:bold; width:8%; font-size:12px; padding:4px 6px; text-align:right; border:1px solid #000;'>Total ($)</td>
                    </tr>
                </thead>
                <tbody>
            ";

                $subtotal = 0;

                foreach ($porMaterial as $mat) {
                    $stock      = $mat['entradas'] - $mat['salidas'];
                    $totalLinea = $stock * $mat['precio'];
                    $subtotal  += $totalLinea;
                    $granTotal += $totalLinea;

                    $precioFormat = number_format($mat['precio'], 4);
                    $totalFormat  = number_format($totalLinea, 4);

                    $tabla .= "
                    <tr>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000;'>$mat[codigo]</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000;'>$mat[nombre]</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000;'>$mat[medida]</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:center;'>$mat[entradas]</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:center;'>$mat[salidas]</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:center;'>$stock</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:right;'>$ $precioFormat</td>
                        <td style='font-size:11px; padding:3px 6px; border:1px solid #000; text-align:right;'>$ $totalFormat</td>
                    </tr>
                ";
                }

                $subtotalFmt = number_format($subtotal, 4);

                $tabla .= "
                    <tr>
                        <td colspan='7' style='font-weight:bold; font-size:12px; padding:4px 6px;
                                               text-align:right; border:1px solid #000;'>
                            Subtotal $proyecto->nombre:
                        </td>
                        <td style='font-weight:bold; font-size:12px; padding:4px 6px;
                                    text-align:right; border:1px solid #000;'>
                            $ $subtotalFmt
                        </td>
                    </tr>
                </tbody>
            </table>
            ";
            }

            $granTotalFmt = number_format($granTotal, 4);

            $tabla .= "
        <table width='100%' style='margin-top:10px;'>
            <tbody>
                <tr>
                    <td style='font-weight:bold; font-size:14px; text-align:right;
                                border-top:2px solid #000; padding-top:6px;'>
                        TOTAL GENERAL:&nbsp;&nbsp;
                    </td>
                    <td style='font-weight:bold; font-size:14px; width:15%;
                                border-top:2px solid #000; padding-top:6px;'>
                        $ $granTotalFmt
                    </td>
                </tr>
            </tbody>
        </table>";
        }

        $stylesheet = file_get_contents('css/cssregistro.css');
        $mpdf->WriteHTML($stylesheet, 1);
        $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
        $mpdf->WriteHTML($tabla, 2);
        $mpdf->Output();
    }

    public function vistaQueHaSalidoProyecto(){

        // necesito todos los proyectos, ya que solo es reporte
        $proyectos = TipoProyecto::orderBy('nombre', 'ASC')
            ->get();

        return view('backend.admin.repuestos.reporte.vistaquehasalidoproyecto', compact('proyectos'));
    }


    public function pdfQueHaSalidoProyectos($idproy, $desde, $hasta, $tipo){

        $infoProyecto = TipoProyecto::where('id', $idproy)->first();

        $start = Carbon::parse($desde)->startOfDay();
        $end = Carbon::parse($hasta)->endOfDay();

        $desdeFormat = date("d-m-Y", strtotime($desde));
        $hastaFormat = date("d-m-Y", strtotime($hasta));

        $logoalcaldia = 'images/logo.png';

        $encabezado = "
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
                    <h2 style='margin:0;'>Reporte de Materiales Entregados</h2>
                    <p style='margin:0; font-size:12px;'>Fecha: $desdeFormat  -  $hastaFormat</p>
                </td>
            </tr>
        </table>
    ";

        // JUNTOS
        if($tipo == 1){

            $pilaArray = array();

            $arrayHistoSalida = HistorialSalidas::where('id_tipoproyecto', $idproy)
                ->whereBetween('fecha', [$start, $end])
                ->orderBy('fecha', 'ASC')
                ->get();

            foreach ($arrayHistoSalida as $data){
                array_push($pilaArray, $data->id);
            }

            $dataArray = array();

            $arraySalidaDetalle = HistorialSalidasDeta::whereIn('id_historial_salidas', $pilaArray)->get();

            $arrayMateriales = Materiales::all();

            foreach ($arrayMateriales as $data){

                $infoMedida = UnidadMedida::where('id', $data->id_medida)->first();

                $cantidad = 0;

                foreach ($arraySalidaDetalle as $item) {
                    if($item->id_material == $data->id){
                        $cantidad = $cantidad + $item->cantidad;
                    }
                }

                if($cantidad > 0){
                    $dataArray[] = [
                        'nombre'   => $data->nombre,
                        'codigo'   => $data->codigo,
                        'cantidad' => $cantidad,
                        'medida'   => $infoMedida ? $infoMedida->nombre : '—'
                    ];
                }
            }

            usort($dataArray, function ($a, $b) {
                return strcmp($a['nombre'], $b['nombre']);
            });

            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
            $mpdf->SetTitle('Reporte de Materiales Entregados');
            $mpdf->showImageErrors = false;

            $tabla  = $encabezado;

            $tabla .= "<p style='font-size: 15px;'><span style='font-weight: bold;'>Proyecto:</span> $infoProyecto->nombre</p>";

            $tabla .= "<table width='100%' id='tablaFor'>
                <tbody>
                    <tr>
                        <td style='font-weight: bold; width: 15%; font-size: 13px'>Código</td>
                        <td style='font-weight: bold; width: 50%; font-size: 13px'>Material</td>
                        <td style='font-weight: bold; width: 15%; font-size: 13px'>Cantidad</td>
                    </tr>";

            foreach ($dataArray as $info) {
                $codigo   = $info['codigo'];
                $nombre   = $info['nombre'];
                $cantidad = $info['cantidad'];

                $tabla .= "<tr>
                <td style='font-size: 12px'>$codigo</td>
                <td style='text-align: left !important; font-size: 12px'>$nombre</td>
                <td style='font-size: 12px'>$cantidad</td>
            </tr>";
            }

            $tabla .= "</tbody></table>";

            $stylesheet = file_get_contents('css/cssregistro.css');
            $mpdf->WriteHTML($stylesheet, 1);
            $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
            $mpdf->WriteHTML($tabla, 2);
            $mpdf->Output();

        } else {
            // SEPARADOS

            $arrayHistoSalida = HistorialSalidas::where('id_tipoproyecto', $idproy)
                ->whereBetween('fecha', [$start, $end])
                ->orderBy('fecha', 'ASC')
                ->get();

            $resultsBloque = array();
            $index = 0;

            foreach ($arrayHistoSalida as $data){

                array_push($resultsBloque, $data);

                $data->fecha = date("d-m-Y", strtotime($data->fecha));

                $arrayDetalle = HistorialSalidasDeta::where('id_historial_salidas', $data->id)->get();

                foreach ($arrayDetalle as $deta){
                    $infoMate = Materiales::where('id', $deta->id_material)->first();

                    if (!$infoMate) continue;

                    $infoMedida = UnidadMedida::where('id', $infoMate->id_medida)->first();

                    $deta->nombremate = $infoMate->nombre;
                    $deta->codigo     = $infoMate->codigo;
                    $deta->unimedida  = $infoMedida ? $infoMedida->nombre : '—';
                }

                $resultsBloque[$index]->detalle = $arrayDetalle;
                $index++;
            }

            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
            $mpdf->SetTitle('Reporte de Materiales Entregados');
            $mpdf->showImageErrors = false;

            $tabla  = $encabezado;
            $tabla .= "<p style='font-size: 15px;'><span style='font-weight: bold;'>Proyecto:</span> $infoProyecto->nombre</p>";

            foreach ($arrayHistoSalida as $info) {

                $tabla .= "<table width='100%' id='tablaFor'>
                <tbody>
                    <tr>
                        <td style='font-weight: bold; width: 15%; font-size: 13px'>Fecha</td>
                        <td style='font-weight: bold; width: 50%; font-size: 13px'>Descripción</td>
                    </tr>
                    <tr>
                        <td style='font-size: 12px'>$info->fecha</td>
                        <td style='font-size: 12px'>$info->descripcion</td>
                    </tr>
                </tbody>
            </table>";

                $tabla .= "<table width='100%' id='tablaFor'>
                <tbody>
                    <tr>
                        <td style='font-weight: bold; width: 12%; font-size: 13px'>Código</td>
                        <td style='font-weight: bold; width: 12%; font-size: 13px'>Medida</td>
                        <td style='font-weight: bold; width: 30%; font-size: 13px'>Material</td>
                        <td style='font-weight: bold; width: 12%; font-size: 13px'>Cantidad</td>
                    </tr>";

                foreach ($info->detalle as $data) {
                    $tabla .= "<tr>
                    <td style='font-size: 12px'>$data->codigo</td>
                    <td style='font-size: 12px'>$data->unimedida</td>
                    <td style='font-size: 12px'>$data->nombremate</td>
                    <td style='font-size: 12px'>$data->cantidad</td>
                </tr>";
                }

                $tabla .= "</tbody></table>";
            }

            $stylesheet = file_get_contents('css/cssregistro.css');
            $mpdf->WriteHTML($stylesheet, 1);
            $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
            $mpdf->WriteHTML($tabla, 2);
            $mpdf->Output();
        }
    }

    public function vistaQueTengoPorProyecto(){

        $terminados = HistorialTransferido::all();
        $pilaIdTransfe = array();

        foreach ($terminados as $data){
            array_push($pilaIdTransfe, $data->id_tipoproyecto);
        }

        $proyectos = TipoProyecto::orderBy('nombre', 'ASC')
            ->whereNotIn('id', $pilaIdTransfe)
            ->get();

        return view('backend.admin.repuestos.reporte.vistaquetengoporproyecto', compact('proyectos'));
    }

    public function reporteQueTengoPorProyecto($idproy){

        $infoProyecto = TipoProyecto::where('id', $idproy)->first();

        $arrayInventario = Entradas::where('id_tipoproyecto', $idproy)->get();

        foreach ($arrayInventario as $dato){
            $infoMate = Materiales::where('id', $dato->id_material)->first();

            if (!$infoMate) continue;

            $dato->nombreMate = $infoMate->nombre;
            $dato->codigoMate = $infoMate->codigo;
        }

        $fechahoy = Carbon::parse(Carbon::now());
        $fechaFormat = date("d-m-Y", strtotime($fechahoy));

        $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
        $mpdf->SetTitle('Inventario Actual');
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
                    <h2 style='margin:0;'>Inventario de Proyecto</h2>
                    <p style='margin:0; font-size:12px;'>Fecha: $fechaFormat</p>
                </td>
            </tr>
        </table>
    ";

        $tabla .= "<p style='font-size: 15px;'><span style='font-weight: bold;'>Proyecto:</span> $infoProyecto->nombre</p>";

        $tabla .= "<table width='100%' id='tablaFor'>
                <tbody>
                    <tr>
                        <td style='font-weight: bold; width: 15%; font-size: 13px'>Código</td>
                        <td style='font-weight: bold; width: 50%; font-size: 13px'>Material</td>
                        <td style='font-weight: bold; width: 15%; font-size: 13px'>Cantidad</td>
                    </tr>";

        foreach ($arrayInventario as $info) {
            if($info->cantidad > 0){
                $tabla .= "<tr>
                <td style='font-size: 12px'>$info->codigoMate</td>
                <td style='font-size: 12px'>$info->nombreMate</td>
                <td style='font-size: 12px'>$info->cantidad</td>
            </tr>";
            }
        }

        $tabla .= "</tbody></table>";

        $stylesheet = file_get_contents('css/cssregistro.css');
        $mpdf->WriteHTML($stylesheet, 1);
        $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
        $mpdf->WriteHTML($tabla, 2);
        $mpdf->Output();
    }


    public function vistaProyectoCompletado(){

        $transferido = HistorialTransferido::orderBy('fecha', 'ASC')->get();

        foreach ($transferido as $dato){

            $dato->fecha = date("d-m-Y", strtotime($dato->fecha));

            $infoProy = TipoProyecto::where('id', $dato->id_tipoproyecto)->first();

            $dato->nomproy = $infoProy->nombre;
        }

        return view('backend.admin.repuestos.reporte.vistaproyectocompletado', compact('transferido'));
    }

    public function reporteProyectoTerminado($idtrans){

        $infoTrans = HistorialTransferido::where('id', $idtrans)->first();

        $fechaGenerado = date("d-m-Y", strtotime($infoTrans->fecha));

        $infoProyecto = TipoProyecto::where('id', $infoTrans->id_tipoproyecto)->first();

        $listado = HistorialTransferidoDetalle::where('id_historial_transf', $idtrans)->get();

        foreach ($listado as $dato){

            $infoMaterial = Materiales::where('id', $dato->id_material)->first();

            $dato->nommaterial = $infoMaterial->nombre;
            $dato->codmaterial = $infoMaterial->codigo;

            $infoUnidad = UnidadMedida::where('id', $infoMaterial->id_medida)->first();
            $dato->nomunidad = $infoUnidad->nombre;
        }

        //$mpdf = new \Mpdf\Mpdf(['format' => 'LETTER']);
        $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
        $mpdf->SetTitle('Transferido');

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
                    <h2 style='margin:0; font-size: 15px'>Reporte de Proyecto Completado</h2>
                    <p style='margin:0; font-size:13px;'>Fecha: $fechaGenerado</p>
                </td>
            </tr>
        </table>
    ";

        $tabla .= "<p style='font-size: 15px;'><span style='font-weight: bold;'>Proyecto:</span> $infoProyecto->nombre</p>";


        $tabla .= "<table width='100%' id='tablaFor'>
            <tbody>";

        $tabla .= "<tr>
                    <td style='font-weight: bold; width: 14%; font-size: 13px'>Código</td>
                    <td style='font-weight: bold; width: 14%; font-size: 13px'>Medida</td>
                    <td style='font-weight: bold; width: 22%; font-size: 13px'>Material</td>
                    <td style='font-weight: bold; width: 12%; font-size: 13px'>Cantidad</td>
                </tr>
                ";

        foreach ($listado as $dd) {

            $tabla .= "<tr>
                     <td style='font-size: 12px'>$dd->codmaterial</td>
                     <td style='font-size: 12px'>$dd->nomunidad</td>
                     <td style='font-size: 12px'>$dd->nommaterial</td>
                     <td style='font-size: 12px'>$dd->cantidad</td>
                </tr>
                ";
        }

        $tabla .= "</tbody></table>";

        $stylesheet = file_get_contents('css/cssregistro.css');
        $mpdf->WriteHTML($stylesheet,1);

        $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');

        $mpdf->WriteHTML($tabla, 2);

        $mpdf->Output();
    }


    public function vistaSalidaPorMaterial(){

        $arrayMateriales = Materiales::orderBy('nombre', 'ASC')->get();

        return view('backend.admin.repuestos.reporte.vistasalidapormaterial', compact('arrayMateriales'));
    }


    public function pdfReporteMaterialesSalidas($desde, $hasta, $materiales){

        $porciones = explode("-", $materiales);

        $arrayIdSalidas = HistorialSalidasDeta::whereIn('id_material', $porciones)->get();

        $pilaIdSalidas = array();

        $start = Carbon::parse($desde)->startOfDay();
        $end = Carbon::parse($hasta)->endOfDay();

        $resultsBloque = array();
        $index = 0;

        $desdeFormat = date("d-m-Y", strtotime($desde));
        $hastaFormat = date("d-m-Y", strtotime($hasta));

        foreach ($arrayIdSalidas as $dato){
            array_push($pilaIdSalidas, $dato->id_historial_salidas);
        }

        $arraySalidas = HistorialSalidas::whereIn('id', $pilaIdSalidas)
            ->whereBetween('fecha', [$start, $end])
            ->orderBy('fecha', 'ASC')
            ->get();

        $pilaIdSalidasFormat = array();
        foreach ($arraySalidas as $dato){
            array_push($pilaIdSalidasFormat, $dato->id);
        }

        foreach ($arraySalidas as $infoFila){
            array_push($resultsBloque, $infoFila);

            $infoFila->fechaFormat = date("d-m-Y", strtotime($infoFila->fecha));

            $infoTipoProy = TipoProyecto::where('id', $infoFila->id_tipoproyecto)->first();
            $infoFila->nombreProy = $infoTipoProy->nombre;

            $arrayDetalle = DB::table('historial_salidas_deta AS deta')
                ->join('materiales AS ma', 'ma.id', '=', 'deta.id_material')
                ->select('ma.nombre', 'deta.id_material', 'deta.id_historial_salidas', 'deta.cantidad')
                ->where('deta.id_historial_salidas', $infoFila->id)
                ->whereIn('deta.id_material', $porciones)
                ->orderBy('ma.nombre', 'ASC')
                ->get();

            $resultsBloque[$index]->detalle = $arrayDetalle;
            $index++;
        }

        $arrayMaterial = Materiales::whereIn('id', $porciones)->get();

        $dataArray = array();

        foreach ($arrayMaterial as $dato){

            $conteoDetalle = HistorialSalidasDeta::whereIn('id_historial_salidas', $pilaIdSalidasFormat)
                ->where('id_material', $dato->id)
                ->sum('cantidad');

            $conteoDetalle = number_format((float)$conteoDetalle, 2, '.', ',');

            $dataArray[] = [
                'nombre'        => $dato->nombre,
                'cantidadtotal' => $conteoDetalle,
            ];
        }

        $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
        $mpdf->SetTitle('Salida Por Materiales');
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
                    <h2 style='margin:0;'>Reporte Salida de Materiales</h2>
                    <p style='margin:0; font-size:12px;'>Fecha: $desdeFormat  -  $hastaFormat</p>
                </td>
            </tr>
        </table>
    ";

        foreach ($arraySalidas as $info) {

            $tabla .= "<table width='100%' id='tablaFor'>
                    <tbody>
                        <tr>
                            <td style='font-weight: bold; width: 15%; font-size: 12px'>Fecha</td>
                            <td style='font-weight: bold; width: 50%; font-size: 12px'>Proyecto</td>
                            <td style='font-weight: bold; width: 15%; font-size: 12px'>Descripción</td>
                        </tr>
                        <tr>
                            <td style='font-size: 12px'>$info->fechaFormat</td>
                            <td style='font-size: 12px'>$info->nombreProy</td>
                            <td style='font-size: 12px'>$info->descripcion</td>
                        </tr>
                    </tbody>
                </table>";

            $tabla .= "<table width='100%' id='tablaFor'>
                    <tbody>
                        <tr>
                            <td style='font-weight: bold; width: 15%; font-size: 13px'>Material</td>
                            <td style='font-weight: bold; width: 10%; font-size: 13px'>Cantidad</td>
                        </tr>";

            foreach ($info->detalle as $dato) {
                $tabla .= "<tr>
                        <td style='font-size: 12px'>$dato->nombre</td>
                        <td style='font-size: 12px'>$dato->cantidad</td>
                    </tr>";
            }

            $tabla .= "</tbody></table>";
        }

        $tabla .= "<p style='font-weight: bold; margin-top: 30px'>MATERIALES ENTREGADOS</p>";

        $tabla .= "<table width='100%' id='tablaFor'>
                <tbody>
                    <tr>
                        <td style='font-weight: bold; width: 50%; font-size: 13px'>Material</td>
                        <td style='font-weight: bold; width: 15%; font-size: 13px'>Cantidad Total</td>
                    </tr>";

        foreach ($dataArray as $info){
            $infoNombre = $info['nombre'];
            $infoConteo = $info['cantidadtotal'];

            $tabla .= "<tr>
                    <td style='font-size: 12px'>$infoNombre</td>
                    <td style='font-size: 12px'>$infoConteo</td>
                </tr>";
        }

        $tabla .= "</tbody></table>";

        $stylesheet = file_get_contents('css/cssregistro.css');
        $mpdf->WriteHTML($stylesheet, 1);
        $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
        $mpdf->WriteHTML($tabla, 2);
        $mpdf->Output();
    }


    public function vistaQueHaEntradoProyecto(){

        // necesito todos los proyectos, ya que solo es reporte
        $proyectos = TipoProyecto::orderBy('nombre', 'ASC')->get();

        return view('backend.admin.repuestos.reporte.vistaquehaentradoproyecto', compact('proyectos'));
    }



    public function pdfQueHaEntradoProyectos($idproy, $desde, $hasta, $tipo)
    {
        $infoProyecto = TipoProyecto::where('id', $idproy)->first();

        $sinFecha = ($desde === 'null' || $desde === '' || $hasta === 'null' || $hasta === '');

        $logoalcaldia = 'images/logo.png';

        if (!$sinFecha) {
            $start = Carbon::parse($desde)->startOfDay();
            $end   = Carbon::parse($hasta)->endOfDay();

            $desdeFormat = date("d-m-Y", strtotime($desde));
            $hastaFormat = date("d-m-Y", strtotime($hasta));
            $fechaLabel  = "Fecha: $desdeFormat  -  $hastaFormat";
        } else {
            $fechaLabel = "Todas las fechas";
        }

        $encabezado = "
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
                    <h2 style='margin:0;'>Reporte de Materiales Recibidos</h2>
                    <p style='margin:0; font-size:12px;'>$fechaLabel</p>
                </td>
            </tr>
        </table>
    ";

        // ─── TIPO 1: JUNTOS ────────────────────────────────────────────────────
        if ($tipo == 1) {

            $pilaArray = array();

            $query = HistorialEntradas::where('id_tipoproyecto', $idproy);
            if (!$sinFecha) {
                $query->whereBetween('fecha', [$start, $end]);
            }
            $arrayHistoEntrada = $query->orderBy('fecha', 'ASC')->get();

            foreach ($arrayHistoEntrada as $data) {
                array_push($pilaArray, $data->id);
            }

            $dataArray = array();
            $arrayEntradaDetalle = HistorialEntradasDeta::whereIn('id_historial', $pilaArray)->get();
            $arrayMateriales = Materiales::all();

            $granTotal = 0; // ← acumulador global

            foreach ($arrayMateriales as $data) {

                $infoMedida = UnidadMedida::where('id', $data->id_medida)->first();

                $cantidad     = 0;
                $totalMaterial = 0;
                $precioUnitario = 0;

                foreach ($arrayEntradaDetalle as $item) {
                    if ($item->id_material == $data->id) {
                        $cantidad       += $item->cantidad;
                        $totalMaterial  += ($item->precio * $item->cantidad);
                        $precioUnitario  = $item->precio; // último precio registrado
                    }
                }

                if ($cantidad > 0) {
                    $granTotal += $totalMaterial;
                    $dataArray[] = [
                        'nombre'         => $data->nombre,
                        'codigo'         => $data->codigo,
                        'cantidad'       => $cantidad,
                        'precioUnitario' => number_format($precioUnitario, 2),
                        'total'          => number_format($totalMaterial, 2),
                        'medida'         => $infoMedida ? $infoMedida->nombre : '—',
                    ];
                }
            }

            usort($dataArray, function ($a, $b) {
                return strcmp($a['nombre'], $b['nombre']);
            });

            $granTotalFmt = number_format($granTotal, 2);

            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
            $mpdf->SetTitle('Reporte de Materiales Recibidos');
            $mpdf->showImageErrors = false;

            $tabla  = $encabezado;
            $tabla .= "<p style='font-size:15px;'><span style='font-weight:bold;'>Proyecto:</span> {$infoProyecto->nombre}</p>";

            $tabla .= "
        <table width='100%' id='tablaFor'>
            <tbody>
                <tr>
                    <td style='font-weight:bold; width:13%; font-size:13px;'>Código</td>
                    <td style='font-weight:bold; width:35%; font-size:13px;'>Material</td>
                    <td style='font-weight:bold; width:12%; font-size:13px;'>Medida</td>
                    <td style='font-weight:bold; width:11%; font-size:13px;'>Cantidad</td>
                    <td style='font-weight:bold; width:15%; font-size:13px;'>Precio Unit.</td>
                    <td style='font-weight:bold; width:15%; font-size:13px;'>Total ($)</td>
                </tr>";

            foreach ($dataArray as $info) {
                $tabla .= "
            <tr>
                <td style='font-size:12px;'>{$info['codigo']}</td>
                <td style='text-align:left; font-size:12px;'>{$info['nombre']}</td>
                <td style='font-size:12px;'>{$info['medida']}</td>
                <td style='font-size:12px;'>{$info['cantidad']}</td>
                <td style='font-size:12px;'>$ {$info['precioUnitario']}</td>
                <td style='font-size:12px;'>$ {$info['total']}</td>
            </tr>";
            }

            // ── Fila de gran total ──
            $tabla .= "
            <tr>
                <td colspan='5' style='font-weight:bold; font-size:13px; text-align:right;
                                        border-top:1.5px solid #000; padding-top:4px;'>
                    TOTAL GENERAL:
                </td>
                <td style='font-weight:bold; font-size:13px; border-top:1.5px solid #000;
                            padding-top:4px;'>
                    $ $granTotalFmt
                </td>
            </tr>";

            $tabla .= "</tbody></table>";

            $stylesheet = file_get_contents('css/cssregistro.css');
            $mpdf->WriteHTML($stylesheet, 1);
            $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
            $mpdf->WriteHTML($tabla, 2);
            $mpdf->Output();

        } else {
            // ─── TIPO 2: SEPARADOS ─────────────────────────────────────────────

            $query = HistorialEntradas::where('id_tipoproyecto', $idproy);
            if (!$sinFecha) {
                $query->whereBetween('fecha', [$start, $end]);
            }
            $arrayHistoEntrada = $query->orderBy('fecha', 'ASC')->get();

            $resultsBloque = array();
            $index = 0;
            $granTotal = 0; // ← acumulador global

            foreach ($arrayHistoEntrada as $data) {

                array_push($resultsBloque, $data);

                $data->fechaFormat = $data->fecha
                    ? date("d-m-Y", strtotime($data->fecha))
                    : 'Sin fecha';

                $arrayDetalle = HistorialEntradasDeta::where('id_historial', $data->id)->get();

                foreach ($arrayDetalle as $deta) {
                    $infoMate = Materiales::where('id', $deta->id_material)->first();

                    if (!$infoMate) continue;

                    $infoMedida = UnidadMedida::where('id', $infoMate->id_medida)->first();

                    $totalLinea = $deta->precio * $deta->cantidad;
                    $granTotal += $totalLinea;

                    $deta->nombremate    = $infoMate->nombre;
                    $deta->codigomate    = $infoMate->codigo;
                    $deta->unimedida     = $infoMedida ? $infoMedida->nombre : '—';
                    $deta->precioUnitFmt = number_format($deta->precio, 2);
                    $deta->totalFmt      = number_format($totalLinea, 2);
                }

                $resultsBloque[$index]->detalle = $arrayDetalle;
                $index++;
            }

            $granTotalFmt = number_format($granTotal, 2);

            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
            $mpdf->SetTitle('Reporte de Materiales Recibidos');
            $mpdf->showImageErrors = false;

            $tabla  = $encabezado;
            $tabla .= "<p style='font-size:15px;'><span style='font-weight:bold;'>Proyecto:</span> {$infoProyecto->nombre}</p>";

            foreach ($resultsBloque as $info) {

                $tabla .= "
            <table width='100%' id='tablaFor'>
                <tbody>
                    <tr>
                        <td style='font-weight:bold; width:15%; font-size:13px;'>Fecha</td>
                        <td style='font-weight:bold; width:85%; font-size:13px;'>Descripción</td>
                    </tr>
                    <tr>
                        <td style='font-size:12px;'>{$info->fechaFormat}</td>
                        <td style='font-size:12px;'>{$info->descripcion}</td>
                    </tr>
                </tbody>
            </table>";

                $tabla .= "
            <table width='100%' id='tablaFor'>
                <tbody>
                    <tr>
                        <td style='font-weight:bold; width:13%; font-size:13px;'>Código</td>
                        <td style='font-weight:bold; width:12%; font-size:13px;'>Medida</td>
                        <td style='font-weight:bold; width:30%; font-size:13px;'>Material</td>
                        <td style='font-weight:bold; width:11%; font-size:13px;'>Cantidad</td>
                        <td style='font-weight:bold; width:15%; font-size:13px;'>Precio Unit.</td>
                        <td style='font-weight:bold; width:15%; font-size:13px;'>Total ($)</td>
                    </tr>";

                foreach ($info->detalle as $deta) {
                    $tabla .= "
                <tr>
                    <td style='font-size:12px;'>{$deta->codigomate}</td>
                    <td style='font-size:12px;'>{$deta->unimedida}</td>
                    <td style='font-size:12px;'>{$deta->nombremate}</td>
                    <td style='font-size:12px;'>{$deta->cantidad}</td>
                    <td style='font-size:12px;'>$ {$deta->precioUnitFmt}</td>
                    <td style='font-size:12px;'>$ {$deta->totalFmt}</td>
                </tr>";
                }

                $tabla .= "</tbody></table><br>";
            }

            // ── Gran total al final del documento ──
            $tabla .= "
        <table width='100%' style='margin-top:10px;'>
            <tbody>
                <tr>
                    <td style='font-weight:bold; font-size:14px; text-align:right;
                                border-top:2px solid #000; padding-top:6px;'>
                        TOTAL GENERAL:&nbsp;&nbsp;
                    </td>
                    <td style='font-weight:bold; font-size:14px; width:18%;
                                border-top:2px solid #000; padding-top:6px;'>
                        $ $granTotalFmt
                    </td>
                </tr>
            </tbody>
        </table>";

            $stylesheet = file_get_contents('css/cssregistro.css');
            $mpdf->WriteHTML($stylesheet, 1);
            $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
            $mpdf->WriteHTML($tabla, 2);
            $mpdf->Output();
        }
    }



}
