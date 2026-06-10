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






    public function reportePDFInicialPorPeriodos($desde, $hasta)
    {
        $start = Carbon::parse($desde)->startOfDay();
        $end   = Carbon::parse($hasta)->endOfDay();

        $desdeFormat = Carbon::parse($desde)->format('d/m/Y');
        $hastaFormat = Carbon::parse($hasta)->format('d/m/Y');

        // ----- Consulta base (agrupado por producto, sin lotes separados) -----
        $rows = DB::select("
        WITH entradas AS (
            SELECT
                ed.id              AS id_entradadetalle,
                ed.id_material,
                ed.precio,
                ed.codigo          AS codigo_producto,
                ed.nombre          AS nombre_copia,
                en.factura         AS lote,
                ed.cantidad_inicial AS cantidad_entrada,
                en.fecha           AS fecha_entrada
            FROM entradas_detalle ed
            JOIN entradas en ON en.id = ed.id_entradas
        ),
        salidas AS (
            SELECT
                sd.id_entrada_detalle AS id_entradadetalle,
                sd.cantidad_salida,
                sd.fecha AS fecha_salida
            FROM salidas_detalle sd
        ),
        in_before AS (
            SELECT id_entradadetalle, SUM(cantidad_entrada) AS qty_in_before
            FROM entradas
            WHERE fecha_entrada < ?
            GROUP BY id_entradadetalle
        ),
        out_before AS (
            SELECT id_entradadetalle, SUM(cantidad_salida) AS qty_out_before
            FROM salidas
            WHERE fecha_salida < ?
            GROUP BY id_entradadetalle
        ),
        in_period AS (
            SELECT id_entradadetalle, SUM(cantidad_entrada) AS qty_in_period
            FROM entradas
            WHERE fecha_entrada >= ? AND fecha_entrada <= ?
            GROUP BY id_entradadetalle
        ),
        out_period AS (
            SELECT id_entradadetalle, SUM(cantidad_salida) AS qty_out_period
            FROM salidas
            WHERE fecha_salida >= ? AND fecha_salida <= ?
            GROUP BY id_entradadetalle
        ),
        base AS (
            SELECT
                en.id_entradadetalle,
                en.id_material,
                obj.codigo AS codigo,
                COALESCE(m.nombre, en.nombre_copia) AS descripcion,
                en.precio,

                COALESCE(ib.qty_in_before, 0) - COALESCE(ob.qty_out_before, 0) AS saldo_inicial_cant,
                COALESCE(ip.qty_in_period,  0) AS entradas_mes_cant,
                COALESCE(op.qty_out_period, 0) AS salidas_mes_cant,
                (COALESCE(ib.qty_in_before, 0) - COALESCE(ob.qty_out_before, 0)
                 + COALESCE(ip.qty_in_period, 0)
                 - COALESCE(op.qty_out_period, 0)) AS saldo_final_cant,

                ((COALESCE(ib.qty_in_before, 0) - COALESCE(ob.qty_out_before, 0)) * en.precio) AS saldo_inicial_money,
                (COALESCE(ip.qty_in_period,  0) * en.precio) AS entradas_mes_money,
                (COALESCE(op.qty_out_period, 0) * en.precio) AS salidas_mes_money,
                ((COALESCE(ib.qty_in_before, 0) - COALESCE(ob.qty_out_before, 0)
                  + COALESCE(ip.qty_in_period, 0) - COALESCE(op.qty_out_period, 0)) * en.precio) AS saldo_final_money
            FROM entradas en
            LEFT JOIN materiales m ON m.id = en.id_material
            LEFT JOIN objeto_especifico obj ON obj.id = m.id_objespecifico
            LEFT JOIN in_before  ib ON ib.id_entradadetalle  = en.id_entradadetalle
            LEFT JOIN out_before ob ON ob.id_entradadetalle  = en.id_entradadetalle
            LEFT JOIN in_period  ip ON ip.id_entradadetalle  = en.id_entradadetalle
            LEFT JOIN out_period op ON op.id_entradadetalle  = en.id_entradadetalle
        ),
        agrupado AS (
            SELECT
                b.id_material,
                b.codigo,
                b.descripcion,
                b.precio,

                SUM(b.saldo_inicial_cant)  AS saldo_inicial_cant,
                SUM(b.entradas_mes_cant)   AS entradas_mes_cant,
                SUM(b.salidas_mes_cant)    AS salidas_mes_cant,
                SUM(b.saldo_final_cant)    AS saldo_final_cant,

                SUM(b.saldo_inicial_money) AS saldo_inicial_money,
                SUM(b.entradas_mes_money)  AS entradas_mes_money,
                SUM(b.salidas_mes_money)   AS salidas_mes_money,
                SUM(b.saldo_final_money)   AS saldo_final_money

            FROM base b
            GROUP BY
                b.id_material,
                b.codigo,
                b.descripcion,
                b.precio
        )

        -- Filtrar: ocultar materiales en cero SIN movimiento en el período
        SELECT *
        FROM agrupado
        WHERE saldo_final_cant > 0
           OR entradas_mes_cant > 0
           OR salidas_mes_cant > 0

        ORDER BY codigo, descripcion
    ", [
            $start->toDateString(),
            $start->toDateString(),
            $start->toDateString(),
            $end->toDateString(),
            $start->toDateString(),
            $end->toDateString(),
        ]);

        // ----- Totales del período -----
        $totales = [
            'entradas_cant'  => 0,
            'salidas_cant'   => 0,
            'final_cant'     => 0,
            'entradas_money' => 0.0,
            'salidas_money'  => 0.0,
            'final_money'    => 0.0,
            'inicial_cant'   => 0,
            'inicial_money'  => 0.0,
        ];

        // Sumatorias por código (sin descripción)
        $sumPorCodigo = [];

        foreach ($rows as $r) {
            // 1) TOTALES GENERALES
            $totales['inicial_cant']   += (int) ($r->saldo_inicial_cant ?? 0);
            $totales['entradas_cant']  += (int) ($r->entradas_mes_cant  ?? 0);
            $totales['salidas_cant']   += (int) ($r->salidas_mes_cant   ?? 0);
            $totales['final_cant']     += (int) ($r->saldo_final_cant   ?? 0);

            $totales['inicial_money']  += (float) ($r->saldo_inicial_money ?? 0);
            $totales['entradas_money'] += (float) ($r->entradas_mes_money  ?? 0);
            $totales['salidas_money']  += (float) ($r->salidas_mes_money   ?? 0);
            $totales['final_money']    += (float) ($r->saldo_final_money   ?? 0);

            // 2) SUMAS POR CÓDIGO
            $codigo = $r->codigo ?? 'SIN-CODIGO';

            if (!isset($sumPorCodigo[$codigo])) {
                $sumPorCodigo[$codigo] = [
                    'codigo'         => $codigo,
                    'inicial_cant'   => 0,
                    'entradas_cant'  => 0,
                    'salidas_cant'   => 0,
                    'final_cant'     => 0,
                    'inicial_money'  => 0.0,
                    'entradas_money' => 0.0,
                    'salidas_money'  => 0.0,
                    'final_money'    => 0.0,
                ];
            }

            $sumPorCodigo[$codigo]['inicial_cant']   += (int) ($r->saldo_inicial_cant ?? 0);
            $sumPorCodigo[$codigo]['entradas_cant']  += (int) ($r->entradas_mes_cant  ?? 0);
            $sumPorCodigo[$codigo]['salidas_cant']   += (int) ($r->salidas_mes_cant   ?? 0);
            $sumPorCodigo[$codigo]['final_cant']     += (int) ($r->saldo_final_cant   ?? 0);

            $sumPorCodigo[$codigo]['inicial_money']  += (float) ($r->saldo_inicial_money ?? 0);
            $sumPorCodigo[$codigo]['entradas_money'] += (float) ($r->entradas_mes_money  ?? 0);
            $sumPorCodigo[$codigo]['salidas_money']  += (float) ($r->salidas_mes_money   ?? 0);
            $sumPorCodigo[$codigo]['final_money']    += (float) ($r->saldo_final_money   ?? 0);
        }

        // ========== Render PDF ==========
        $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER', 'orientation' => 'L']);

        $mpdf->SetTitle('Reporte de Bodega por Período');
        $mpdf->showImageErrors = false;

        $logoalcaldia = 'images/gobiernologo.jpg';

        $encabezado = "
        <table width='100%' style='border-collapse:collapse; font-family: Arial, sans-serif;'>
            <tr>
                <td style='width:25%; border:0.8px solid #000; padding:6px 8px;'>
                    <table width='100%'>
                        <tr>
                            <td style='width:30%; text-align:left;'>
                                <img src='{$logoalcaldia}' style='height:38px'>
                            </td>
                            <td style='width:70%; text-align:left; color:#104e8c; font-size:13px; font-weight:bold; line-height:1.3;'>
                                SANTA ANA NORTE<br>EL SALVADOR
                            </td>
                        </tr>
                    </table>
                </td>
                <td style='width:75%; border-top:0.8px solid #000; border-bottom:0.8px solid #000; padding:6px 8px; text-align:center; font-size:15px; font-weight:bold;'>
                    CONTROL DE ENTRADAS/SALIDAS<br>
                </td>
            </tr>
        </table>
        <br>
    ";

        $encabezado .= "<span style='font-weight:bold;'>Del {$desdeFormat} al {$hastaFormat}</span><br>";

        if (file_exists(public_path('css/cssbodega.css'))) {
            $stylesheet = file_get_contents(public_path('css/cssbodega.css'));
            $mpdf->WriteHTML($stylesheet, \Mpdf\HTMLParserMode::HEADER_CSS);
        }

        // Tabla detalle
        $html = $encabezado;
        $html .= "
        <table width='100%' border='1' cellspacing='0' cellpadding='4' style='border-collapse:collapse; font-size:11px; margin-top: 8px'>
            <thead style='background:#f2f4f8'>
               <tr>
                <th>#</th>
                <th>Código</th>
                <th>Descripción / Nombre</th>
                <th style='text-align:right; width:8%'>PRECIO</th>
                <th style='text-align:right; width:6%'>INICIAL</th>
                <th style='text-align:right; width:7%'>$ INICIAL</th>
                <th style='text-align:right; width:8%'>ENTRADAS</th>
                <th style='text-align:right; width:9%'>$ ENTRADAS</th>
                <th style='text-align:right; width:8%'>SALIDAS</th>
                <th style='text-align:right; width:8%'>$ SALIDAS</th>
                <th style='text-align:right; width:6%'>SALDO</th>
                <th style='text-align:right; width:7%'>$ SALDO</th>
            </tr>
            </thead>
            <tbody>
    ";

        $i = 1;
        foreach ($rows as $r) {
            $desc = $r->descripcion;

            $html .= "
            <tr>
                <td>{$i}</td>
                <td>".e($r->codigo ?? '')."</td>
                <td>".e($desc)."</td>
                <td style='text-align:right'>$".number_format($r->precio ?? 0, 4)."</td>

                <td style='text-align:right'>".number_format($r->saldo_inicial_cant ?? 0)."</td>
                <td style='text-align:right'>$".number_format($r->saldo_inicial_money ?? 0, 2)."</td>

                <td style='text-align:right'>".number_format($r->entradas_mes_cant ?? 0)."</td>
                <td style='text-align:right'>$".number_format($r->entradas_mes_money ?? 0, 2)."</td>

                <td style='text-align:right'>".number_format($r->salidas_mes_cant ?? 0)."</td>
                <td style='text-align:right'>$".number_format($r->salidas_mes_money ?? 0, 2)."</td>

                <td style='text-align:right'>".number_format($r->saldo_final_cant ?? 0)."</td>
                <td style='text-align:right'>$".number_format($r->saldo_final_money ?? 0, 2)."</td>
            </tr>
        ";
            $i++;
        }

        if (!$rows) {
            $html .= "<tr><td colspan='12' style='text-align:center; color:#888;'>Sin registros en el rango seleccionado.</td></tr>";
        }

        $html .= "
            </tbody>
            <tfoot>
                <tr style='font-weight:bold; background:#f9fafb'>
                     <td colspan='4' style='text-align:right'>Totales:</td>

                    <td style='text-align:right'>".number_format($totales['inicial_cant'])."</td>
                    <td style='text-align:right'>$".number_format($totales['inicial_money'], 2)."</td>

                    <td style='text-align:right'>".number_format($totales['entradas_cant'])."</td>
                    <td style='text-align:right'>$".number_format($totales['entradas_money'], 2)."</td>

                    <td style='text-align:right'>".number_format($totales['salidas_cant'])."</td>
                    <td style='text-align:right'>$".number_format($totales['salidas_money'], 2)."</td>

                    <td style='text-align:right'>".number_format($totales['final_cant'])."</td>
                    <td style='text-align:right'>$".number_format($totales['final_money'], 2)."</td>
                </tr>
            </tfoot>
        </table>
    ";

        // Resumen del período
        $html .= "
        <br>
        <table width='60%' border='1' cellspacing='0' cellpadding='6' style='border-collapse:collapse; font-size:12px'>
            <tr style='background:#eef3ff; font-weight:bold; text-align:center'>
                <td colspan='3'>Resumen del período {$desdeFormat} - {$hastaFormat}</td>
            </tr>
            <tr style='font-weight:bold; background:#f9fafb'>
                <td></td>
                <td style='text-align:right'>Cantidad</td>
                <td style='text-align:right'>Dinero ($)</td>
            </tr>
            <tr>
                <td>Ingresó (Entradas del período)</td>
                <td style='text-align:right'>".number_format($totales['entradas_cant'])."</td>
                <td style='text-align:right'>$".number_format($totales['entradas_money'], 2)."</td>
            </tr>
            <tr>
                <td>Salió (Salidas del período)</td>
                <td style='text-align:right'>".number_format($totales['salidas_cant'])."</td>
                <td style='text-align:right'>$".number_format($totales['salidas_money'], 2)."</td>
            </tr>
            <tr>
                <td>Disponible al cierre (Saldo final)</td>
                <td style='text-align:right'>".number_format($totales['final_cant'])."</td>
                <td style='text-align:right'>$".number_format($totales['final_money'], 2)."</td>
            </tr>
        </table>
    ";

        // Cuadro adicional: sumatorias por código
        if (!empty($sumPorCodigo)) {

            $totalSaldoFinalCodigos = 0;

            $html .= "
    <br><br>
    <table width='100%' border='1' cellspacing='0' cellpadding='4' style='border-collapse:collapse; font-size:11px'>
        <thead style='background:#f2f4f8'>
            <tr>
                <th style='width:4%'>#</th>
                <th style='width:10%'>Código</th>

                <th style='text-align:right; width:6%'>INICIAL</th>
                <th style='text-align:right; width:10%'>$ INICIAL</th>

                <th style='text-align:right; width:6%'>ENTRADAS</th>
                <th style='text-align:right; width:10%'>$ ENTRADAS</th>

                <th style='text-align:right; width:6%'>SALIDAS</th>
                <th style='text-align:right; width:10%'>$ SALIDAS</th>

                <th style='text-align:right; width:6%'>SALDO</th>
                <th style='text-align:right; width:10%'>$ SALDO</th>
            </tr>
        </thead>
        <tbody>
    ";

            $j = 1;
            foreach ($sumPorCodigo as $cod => $s) {

                $totalSaldoFinalCodigos += (float) $s['final_money'];

                $html .= "
        <tr>
            <td>{$j}</td>
            <td>".e($s['codigo'])."</td>

            <td style='text-align:right'>".number_format($s['inicial_cant'])."</td>
            <td style='text-align:right'>$".number_format($s['inicial_money'], 2)."</td>

            <td style='text-align:right'>".number_format($s['entradas_cant'])."</td>
            <td style='text-align:right'>$".number_format($s['entradas_money'], 2)."</td>

            <td style='text-align:right'>".number_format($s['salidas_cant'])."</td>
            <td style='text-align:right'>$".number_format($s['salidas_money'], 2)."</td>

            <td style='text-align:right'>".number_format($s['final_cant'])."</td>
            <td style='text-align:right'>$".number_format($s['final_money'], 2)."</td>
        </tr>
        ";
                $j++;
            }

            $html .= "
        <tr style='font-weight:bold; background:#f9fafb'>
            <td colspan='9' style='text-align:right'>TOTAL</td>
            <td style='text-align:right'>$".number_format($totalSaldoFinalCodigos, 2)."</td>
        </tr>
    ";

            $html .= "
        </tbody>
    </table>
    ";
        }

        // Línea de firma centrada
        $html .= "
        <div style='text-align:center; font-size:13px; margin-top: 65px;'>
            F._____________________________<br>
            <span style='font-weight:bold; font-size:12px;'>Unidad de Tecnologías de la Información</span>
        </div>
    ";

        $mpdf->setFooter("Página {PAGENO} de {nb}");
        $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);
        $mpdf->Output();
    }














}
