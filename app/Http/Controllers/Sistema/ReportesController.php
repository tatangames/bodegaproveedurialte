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
        $arrayDepartamento = Departamentos::orderBy('nombre')->get();
        $arrayMateriales = Materiales::orderBy('nombre')->get();

        return view('backend.reportes.vistareportegenerales', compact('arrayDepartamento', 'arrayMateriales'));
    }



    public function generarPDFExistencias()
    {
        // ── Existencias actuales: entradas_detalle con cantidad disponible > 0 ──
        $arrayInfo = EntradasDetalle::with('material.objetoEspecifico', 'material.unidadMedida', 'entrada')
            ->get();

        $totalColumna = 0;
        $arrayDetalle = collect();
        $arrayPendientes = collect();

        foreach ($arrayInfo as $fila) {

            $material = $fila->material;

            if (!$material) {
                continue;
            }

            $objEspecifico = $material->objetoEspecifico ?? null;

            $totalSalido = SalidasDetalle::where('id_entrada_detalle', $fila->id)
                ->sum('cantidad_salida');

            $cantidadActual =
                (int) $fila->cantidad_inicial
                - (int) $totalSalido;

            // ==========================================
            // SOLO PENDIENTES
            // ==========================================
            $pendientesQuery = SalidasDetalle::where(
                'id_entrada_detalle',
                $fila->id
            )
                ->where('estado', 'pendiente')
                ->orderBy('id', 'asc')
                ->get();

            foreach ($pendientesQuery as $pend) {

                $entregas =
                    SalidasDetalleEntregas::where(
                        'id_salida_detalle',
                        $pend->id
                    )
                        ->orderBy('fecha_entrega', 'asc')
                        ->get();

                $arrayPendientes->push((object)[
                    'nombreMaterial'  =>
                        $material->nombre ?? '',

                    'cantidad_salida' =>
                        $pend->cantidad_salida,

                    'unidadMedida' =>
                        $material->unidadMedida->nombre ?? '',

                    'descripcion' =>
                        $pend->descripcion ?? '',

                    'entregas' =>
                        $entregas,
                ]);
            }

            // ==========================================
            // SOLO EXISTENCIAS > 0
            // ==========================================
            if ($cantidadActual <= 0) {
                continue;
            }

            $multiplicado =
                $cantidadActual * $fila->precio;

            $totalColumna += $multiplicado;

            $arrayDetalle->push((object)[
                'lote' =>
                    $fila->entrada->lote ?? '',

                'nombreMaterial' =>
                    $material->nombre ?? '',

                'unidadMedida' =>
                    $material->unidadMedida->nombre ?? '',

                'cantidadActual' =>
                    $cantidadActual,

                'precioFormat' =>
                    "$" . number_format(
                        (float)$fila->precio,
                        6,
                        '.',
                        ','
                    ),

                'multiplicado' =>
                    "$" . number_format(
                        (float)$multiplicado,
                        6,
                        '.',
                        ','
                    ),

                'nombreCodigo' =>
                    $objEspecifico->codigo ?? '',
            ]);
        }

        $totalColumnaFmt = "$" . number_format((float)$totalColumna, 6, '.', ',');
        $arrayDetalle = $arrayDetalle->sortBy('nombreMaterial')->values();
        $arrayPendientes = $arrayPendientes->sortBy('nombreMaterial')->values();
        $fechaFormat = date("d-m-Y", strtotime(Carbon::now('America/El_Salvador')));

        $mpdf = new \Mpdf\Mpdf([
            'tempDir' => sys_get_temp_dir(),
            'format' => 'LETTER',
            'mode' => 'utf-8',
        ]);
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
            <h1 style='font-size: 16px; margin: 0; color: #003366; text-transform: uppercase;'>
                ALCALDÍA MUNICIPAL DE SANTA ANA NORTE
            </h1>
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
    <p style='font-size: 13px; margin: 0; color: #000;'>
        Fecha: $fechaFormat
    </p>
</div>
";

        // ── Tabla principal ──
        $tabla .= "
<table id='tablaFor'
       style='width: 100%;
              border-collapse: collapse;
              margin-top: 35px'>

    <tbody>

        <tr>
            <th style='text-align: center; font-size:14px; width: 40%; font-weight: bold; border: 1px solid black;'>
                Producto
            </th>

            <th style='text-align: center; font-size:14px; width: 10%; font-weight: bold; border: 1px solid black;'>
                U.M
            </th>

            <th style='text-align: center; font-size:14px; width: 13%; font-weight: bold; border: 1px solid black;'>
                Cantidad
            </th>

            <th style='text-align: center; font-size:14px; width: 12%; font-weight: bold; border: 1px solid black;'>
                Precio
            </th>

            <th style='text-align: center; font-size:14px; width: 13%; font-weight: bold; border: 1px solid black;'>
                Total
            </th>

            <th style='text-align: center; font-size:14px; width: 15%; font-weight: bold; border: 1px solid black;'>
                Obj. Específico
            </th>
        </tr>
";

        foreach ($arrayDetalle as $fila) {

            $tabla .= "
    <tr>

        <td style='text-align: left; font-size:12px; border: 1px solid black; padding: 3px;'>
            $fila->nombreMaterial
        </td>

        <td style='text-align: center; font-size:12px; border: 1px solid black;'>
            $fila->unidadMedida
        </td>

        <td style='text-align: center; font-size:12px; border: 1px solid black;'>
            $fila->cantidadActual
        </td>

        <td style='text-align: right; font-size:12px; border: 1px solid black; padding: 3px;'>
            $fila->precioFormat
        </td>

        <td style='text-align: right; font-size:12px; border: 1px solid black; padding: 3px;'>
            $fila->multiplicado
        </td>

        <td style='text-align: center; font-size:12px; border: 1px solid black;'>
            $fila->nombreCodigo
        </td>

    </tr>";
        }

        $tabla .= "
    <tr>
        <td colspan='4' style='text-align: right; font-size:14px; font-weight: bold; border: 1px solid black; padding: 3px;'>
            Total
        </td>
        <td colspan='2' style='text-align: center; font-size:13px; font-weight: bold; border: 1px solid black; padding: 3px;'>
            $totalColumnaFmt
        </td>
    </tr>
</tbody></table>";

        // ── Tabla de pendientes ──
        if ($arrayPendientes->isNotEmpty()) {

            $tabla .= "
    <div style='text-align: left; margin-top: 25px;'>
        <h1 style='font-size: 14px; margin: 0; color: #000;'>
            KITS PENDIENTES / ABIERTOS
        </h1>
    </div>
    ";

            $tabla .= "
    <table id='tablaPendientes'
           style='width: 100%;
                  border-collapse: collapse;
                  margin-top: 10px'>

        <tbody>

            <tr>

                <th style='text-align:center;
                           font-size:14px;
                           width:28%;
                           font-weight:bold;
                           border:1px solid black;'>

                    Producto
                </th>

                <th style='text-align:center;
                           font-size:14px;
                           width:12%;
                           font-weight:bold;
                           border:1px solid black;'>

                    Cant. Salida
                </th>

                <th style='text-align:center;
                           font-size:14px;
                           width:25%;
                           font-weight:bold;
                           border:1px solid black;'>

                    Descripción Salida
                </th>

                <th style='text-align:center;
                           font-size:14px;
                           width:35%;
                           font-weight:bold;
                           border:1px solid black;'>

                    Detalle de entregas
                </th>

            </tr>
    ";

            foreach ($arrayPendientes as $pend) {

                $detalleEntregas = '';

                if ($pend->entregas->isEmpty()) {

                    $detalleEntregas =
                        "<span style='font-style: italic; color:#888;'>
                    Sin entregas registradas
                </span>";

                } else {

                    $lineas = [];

                    foreach ($pend->entregas as $ent) {

                        $fechaEnt = date(
                            'd-m-Y',
                            strtotime($ent->fecha_entrega)
                        );

                        $obs = $ent->observacion ?: '';

                        $lineas[] =
                            "{$ent->cantidad} {$pend->unidadMedida} - {$obs}";
                    }

                    $detalleEntregas = implode('<br>', $lineas);
                }

                $descripcionSalida =
                    !empty($pend->descripcion)
                        ? $pend->descripcion
                        : '-';

                $tabla .= "
        <tr>

            <td style='text-align:left;
                       font-size:14px;
                       border:1px solid black;
                       padding:3px;
                       vertical-align:top;'>

                {$pend->nombreMaterial}
            </td>

            <td style='text-align:center;
                       font-size:14px;
                       border:1px solid black;
                       vertical-align:top;'>

                {$pend->cantidad_salida}
                {$pend->unidadMedida}
            </td>

            <td style='text-align:left;
                       font-size:13px;
                       border:1px solid black;
                       padding:3px;
                       vertical-align:top;'>

                {$descripcionSalida}
            </td>

            <td style='text-align:left;
                       font-size:14px;
                       border:1px solid black;
                       padding:3px;'>

                {$detalleEntregas}
            </td>

        </tr>";
            }

            $tabla .= "
        </tbody>
    </table>";
        }

        $stylesheet = file_get_contents('css/cssbodega.css');
        $mpdf->WriteHTML($stylesheet, 1);
        $mpdf->setFooter('Página: {PAGENO}/{nb}');
        $mpdf->WriteHTML($tabla, 2);
        $mpdf->Output();
    }


    public function reportePDFInicialPorPeriodos($desde, $hasta)
    {
        $start = Carbon::parse($desde)->startOfDay();
        $end   = Carbon::parse($hasta)->endOfDay();

        $desdeFormat = Carbon::parse($desde)->format('d/m/Y');
        $hastaFormat = Carbon::parse($hasta)->format('d/m/Y');

        // ==========================================================
        // REPORTE POR LOTE (entradas_detalle), calculando dinero
        // con el precio REAL de cada lote, luego agrupando por material
        // ==========================================================
        $rows = DB::select("
WITH in_before AS (
    SELECT ed.id AS id_entrada_detalle, ed.cantidad_inicial AS qty_in_before
    FROM entradas_detalle ed
    INNER JOIN entradas e ON e.id = ed.id_entradas
    WHERE e.fecha < ?
),
out_before AS (
    SELECT sd.id_entrada_detalle, SUM(sd.cantidad_salida) AS qty_out_before
    FROM salidas_detalle sd
    WHERE sd.fecha < ?
    GROUP BY sd.id_entrada_detalle
),
in_period AS (
    SELECT ed.id AS id_entrada_detalle, ed.cantidad_inicial AS qty_in_period
    FROM entradas_detalle ed
    INNER JOIN entradas e ON e.id = ed.id_entradas
    WHERE e.fecha >= ? AND e.fecha <= ?
),
out_period AS (
    SELECT sd.id_entrada_detalle, SUM(sd.cantidad_salida) AS qty_out_period
    FROM salidas_detalle sd
    WHERE sd.fecha >= ? AND sd.fecha <= ?
    GROUP BY sd.id_entrada_detalle
),
base AS (
    SELECT
        ed.id              AS id_entrada_detalle,
        ed.id_material,
        COALESCE(NULLIF(oe.codigo, ''), 'SIN-CODIGO') AS codigo,
        m.nombre           AS descripcion,
        ed.precio,

        COALESCE(ib.qty_in_before, 0) - COALESCE(ob.qty_out_before, 0) AS saldo_inicial_cant,
        COALESCE(ip.qty_in_period, 0)  AS entradas_mes_cant,
        COALESCE(op.qty_out_period, 0) AS salidas_mes_cant,

        (COALESCE(ib.qty_in_before, 0) - COALESCE(ob.qty_out_before, 0)
         + COALESCE(ip.qty_in_period, 0)
         - COALESCE(op.qty_out_period, 0)) AS saldo_final_cant,

        (COALESCE(ib.qty_in_before, 0) - COALESCE(ob.qty_out_before, 0)) * ed.precio AS saldo_inicial_money,
        COALESCE(ip.qty_in_period, 0) * ed.precio  AS entradas_mes_money,
        COALESCE(op.qty_out_period, 0) * ed.precio AS salidas_mes_money,
        ((COALESCE(ib.qty_in_before, 0) - COALESCE(ob.qty_out_before, 0)
          + COALESCE(ip.qty_in_period, 0)
          - COALESCE(op.qty_out_period, 0)) * ed.precio) AS saldo_final_money

    FROM entradas_detalle ed
    INNER JOIN materiales m ON m.id = ed.id_material
    LEFT JOIN objeto_especifico oe ON oe.id = m.id_objespecifico
    LEFT JOIN in_before  ib ON ib.id_entrada_detalle = ed.id
    LEFT JOIN out_before ob ON ob.id_entrada_detalle = ed.id
    LEFT JOIN in_period  ip ON ip.id_entrada_detalle = ed.id
    LEFT JOIN out_period op ON op.id_entrada_detalle = ed.id
)

SELECT
    id_material,
    codigo,
    descripcion,
    MAX(precio) AS precio,

    SUM(saldo_inicial_cant)  AS saldo_inicial_cant,
    SUM(entradas_mes_cant)   AS entradas_mes_cant,
    SUM(salidas_mes_cant)    AS salidas_mes_cant,
    SUM(saldo_final_cant)    AS saldo_final_cant,

    SUM(saldo_inicial_money) AS saldo_inicial_money,
    SUM(entradas_mes_money)  AS entradas_mes_money,
    SUM(salidas_mes_money)   AS salidas_mes_money,
    SUM(saldo_final_money)   AS saldo_final_money

FROM base
GROUP BY id_material, codigo, descripcion
ORDER BY codigo, descripcion
", [
            $start->toDateString(), // in_before
            $start->toDateString(), // out_before
            $start->toDateString(), $end->toDateString(), // in_period
            $start->toDateString(), $end->toDateString(), // out_period
        ]);

        // ==========================================================
        // FILTRAR FILAS:
        // - Si inicial == 0, entradas == 0, salidas == 0 y final == 0 => OCULTAR
        // - Si tuvo movimientos en el rango (entradas o salidas != 0)
        //   aunque el saldo final sea 0 => MOSTRAR
        // ==========================================================
        $rows = array_values(array_filter($rows, function ($r) {
            $inicial  = (float) ($r->saldo_inicial_cant ?? 0);
            $entradas = (float) ($r->entradas_mes_cant ?? 0);
            $salidas  = (float) ($r->salidas_mes_cant ?? 0);
            $final    = (float) ($r->saldo_final_cant ?? 0);

            if ($inicial == 0 && $entradas == 0 && $salidas == 0 && $final == 0) {
                return false;
            }

            return true;
        }));

        $totales = [
            'entradas_cant' => 0,
            'salidas_cant'  => 0,
            'final_cant'    => 0,
            'entradas_money'=> 0.0,
            'salidas_money' => 0.0,
            'final_money'   => 0.0,
            'inicial_cant'  => 0,
            'inicial_money' => 0.0,
        ];

        $sumPorCodigo = [];
        $totalSaldoFinalCodigos = 0;

        foreach ($rows as $r) {
            $totales['inicial_cant']   += (int) ($r->saldo_inicial_cant ?? 0);
            $totales['entradas_cant']  += (int) ($r->entradas_mes_cant ?? 0);
            $totales['salidas_cant']   += (int) ($r->salidas_mes_cant ?? 0);
            $totales['final_cant']     += (int) ($r->saldo_final_cant ?? 0);

            $totales['inicial_money']  += (float) ($r->saldo_inicial_money ?? 0);
            $totales['entradas_money'] += (float) ($r->entradas_mes_money ?? 0);
            $totales['salidas_money']  += (float) ($r->salidas_mes_money ?? 0);
            $totales['final_money']    += (float) ($r->saldo_final_money ?? 0);

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
            $sumPorCodigo[$codigo]['entradas_cant']  += (int) ($r->entradas_mes_cant ?? 0);
            $sumPorCodigo[$codigo]['salidas_cant']   += (int) ($r->salidas_mes_cant ?? 0);
            $sumPorCodigo[$codigo]['final_cant']     += (int) ($r->saldo_final_cant ?? 0);

            $sumPorCodigo[$codigo]['inicial_money']  += (float) ($r->saldo_inicial_money ?? 0);
            $sumPorCodigo[$codigo]['entradas_money'] += (float) ($r->entradas_mes_money ?? 0);
            $sumPorCodigo[$codigo]['salidas_money']  += (float) ($r->salidas_mes_money ?? 0);
            $sumPorCodigo[$codigo]['final_money']    += (float) ($r->saldo_final_money ?? 0);
        }

        $mpdf = new \Mpdf\Mpdf([
            'tempDir' => sys_get_temp_dir(),
            'format'  => 'LETTER',
            'orientation' => 'L'
        ]);

        $mpdf->SetTitle('Reporte Mensual de Inventario');
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
                        REPORTE DE MOVIMIENTO DE INVENTARIO
                    </td>
                </tr>
            </table>
        </td>
        <td style='width:50%; border-top:0.8px solid #000; border-bottom:0.8px solid #000; padding:6px 8px; text-align:center; font-size:15px; font-weight:bold;'>
            REPORTE DE MOVIMIENTO DE INVENTARIO
        </td>
        <td style='width:25%; border:0.8px solid #000; padding:0; vertical-align:top;'>
            <table width='100%' style='font-size:10px;'>
                <tr>
                    <td width='40%' style='border-right:0.8px solid #000; border-bottom:0.8px solid #000; padding:4px 6px;'><strong>Código:</strong></td>
                    <td width='60%' style='border-bottom:0.8px solid #000; padding:4px 6px; text-align:center;'>PROV-002-REPO</td>
                </tr>
                <tr>
                    <td style='border-right:0.8px solid #000; border-bottom:0.8px solid #000; padding:4px 6px;'><strong>Versión:</strong></td>
                    <td style='border-bottom:0.8px solid #000; padding:4px 6px; text-align:center;'>000</td>
                </tr>
                <tr>
                    <td style='border-right:0.8px solid #000; padding:4px 6px;'><strong>Fecha de vigencia:</strong></td>
                    <td style='padding:4px 6px; text-align:center;'>22/12/2025</td>
                </tr>
            </table>
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

        $html = $encabezado;

        $html .= "
<table width='100%' border='1' cellspacing='0' cellpadding='4' style='border-collapse:collapse; font-size:11px; margin-top: 8px'>
    <thead style='background:#f2f4f8'>
        <tr>
            <th>#</th>
            <th>COD.</th>
            <th>DESCRIPCION</th>
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
            $html .= "
    <tr>
        <td>{$i}</td>
        <td>" . e($r->codigo ?? '') . "</td>
        <td>" . e($r->descripcion ?? '') . "</td>
        <td style='text-align:right'>$" . number_format($r->precio ?? 0, 6) . "</td>
        <td style='text-align:right'>" . number_format($r->saldo_inicial_cant ?? 0) . "</td>
        <td style='text-align:right'>$" . number_format($r->saldo_inicial_money ?? 0, 6) . "</td>
        <td style='text-align:right'>" . number_format($r->entradas_mes_cant ?? 0) . "</td>
        <td style='text-align:right'>$" . number_format($r->entradas_mes_money ?? 0, 6) . "</td>
        <td style='text-align:right'>" . number_format($r->salidas_mes_cant ?? 0) . "</td>
        <td style='text-align:right'>$" . number_format($r->salidas_mes_money ?? 0, 6) . "</td>
        <td style='text-align:right'>" . number_format($r->saldo_final_cant ?? 0) . "</td>
        <td style='text-align:right'>$" . number_format($r->saldo_final_money ?? 0, 6) . "</td>
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
            <td style='text-align:right'>" . number_format($totales['inicial_cant']) . "</td>
            <td style='text-align:right'>$" . number_format($totales['inicial_money'], 6) . "</td>
            <td style='text-align:right'>" . number_format($totales['entradas_cant']) . "</td>
            <td style='text-align:right'>$" . number_format($totales['entradas_money'], 6) . "</td>
            <td style='text-align:right'>" . number_format($totales['salidas_cant']) . "</td>
            <td style='text-align:right'>$" . number_format($totales['salidas_money'], 6) . "</td>
            <td style='text-align:right'>" . number_format($totales['final_cant']) . "</td>
            <td style='text-align:right'>$" . number_format($totales['final_money'], 6) . "</td>
        </tr>
    </tfoot>
</table>
";

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
        <td>Ingresó (Entradas del mes)</td>
        <td style='text-align:right'>" . number_format($totales['entradas_cant']) . "</td>
        <td style='text-align:right'>$" . number_format($totales['entradas_money'], 6) . "</td>
    </tr>
    <tr>
        <td>Salió (Salidas del mes)</td>
        <td style='text-align:right'>" . number_format($totales['salidas_cant']) . "</td>
        <td style='text-align:right'>$" . number_format($totales['salidas_money'], 6) . "</td>
    </tr>
    <tr>
        <td>Disponible al cierre (Saldo final)</td>
        <td style='text-align:right'>" . number_format($totales['final_cant']) . "</td>
        <td style='text-align:right'>$" . number_format($totales['final_money'], 6) . "</td>
    </tr>
</table>
";

        if (!empty($sumPorCodigo)) {
            $totalSaldoFinalCodigos = 0;

            $html .= "
    <br><br>
    <table width='100%' border='1' cellspacing='0' cellpadding='4' style='border-collapse:collapse; font-size:11px'>
        <thead style='background:#f2f4f8'>
            <tr>
                <th style='width:4%'>#</th>
                <th style='width:10%'>COD.</th>
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
            foreach ($sumPorCodigo as $s) {
                $totalSaldoFinalCodigos += (float) $s['final_money'];

                $html .= "
        <tr>
            <td>{$j}</td>
            <td>" . e($s['codigo']) . "</td>
            <td style='text-align:right'>" . number_format($s['inicial_cant']) . "</td>
            <td style='text-align:right'>$" . number_format($s['inicial_money'], 6) . "</td>
            <td style='text-align:right'>" . number_format($s['entradas_cant']) . "</td>
            <td style='text-align:right'>$" . number_format($s['entradas_money'], 6) . "</td>
            <td style='text-align:right'>" . number_format($s['salidas_cant']) . "</td>
            <td style='text-align:right'>$" . number_format($s['salidas_money'], 6) . "</td>
            <td style='text-align:right'>" . number_format($s['final_cant']) . "</td>
            <td style='text-align:right'>$" . number_format($s['final_money'], 6) . "</td>
        </tr>
    ";
                $j++;
            }

            $html .= "
        <tr style='font-weight:bold; background:#f9fafb'>
            <td colspan='9' style='text-align:right'>TOTAL</td>
            <td style='text-align:right'>$" . number_format($totalSaldoFinalCodigos, 6) . "</td>
        </tr>
        </tbody>
    </table>
";
        }

        $infoGeneral = InformacionGeneral::where('id', 1)->first();
        $spacer = "<div style='height: " . $infoGeneral->px_firmas . "px;'></div>";

        $html .= "
" . $spacer . "
<div style='text-align:center; font-size:16px;'>
    F._____________________________<br><br>
    <span style='display:block; margin-top:8px; font-weight:bold; font-size:16px;'>
        Unidad de Proveeduría y Bodega
    </span>
</div>
";

        $mpdf->setFooter('Página {PAGENO} de {nb}');
        $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);
        $mpdf->Output();
    }


    public function generarPDFEntregados($desde, $hasta, $idDepartamento)
    {
        $start = Carbon::parse($desde)->startOfDay();
        $end   = Carbon::parse($hasta)->endOfDay();

        $desdeFormat = date("d-m-Y", strtotime($desde));
        $hastaFormat = date("d-m-Y", strtotime($hasta));

        $infoDepartamento = Departamentos::where('id', $idDepartamento)->first();

        // Todas las salidas hacia ese departamento en el rango de fechas
        $arraySalidas = SalidasDetalle::with('entradaDetalle.material.unidadMedida')
            ->where('id_departamento', $idDepartamento)
            ->whereBetween('fecha', [$start, $end])
            ->orderBy('fecha', 'asc')
            ->get();

        $totalGeneral = 0;
        $arrayDetalle = collect();

        foreach ($arraySalidas as $fila) {

            $entradaDetalle = $fila->entradaDetalle;
            $material       = $entradaDetalle->material ?? null;

            if (!$material) {
                continue;
            }

            $precio       = (float) $entradaDetalle->precio;
            $multiplicado = $fila->cantidad_salida * $precio;
            $totalGeneral += $multiplicado;

            $arrayDetalle->push((object)[
                'fechaFormat'     => date("d-m-Y", strtotime($fila->fecha)),
                'nombreMaterial'  => $material->nombre ?? '',
                'unidadMedida'    => $material->unidadMedida->nombre ?? '',
                'cantidadSalida'  => $fila->cantidad_salida,
                'precioFormat'    => "$" . number_format($precio, 6, '.', ','),
                'multiplicado'    => "$" . number_format((float)$multiplicado, 6, '.', ','),
                'numeroSolicitud' => $fila->numero_solicitud ?? '',
                'descripcion'     => $fila->descripcion ?? '',
                'estado'          => $fila->estado,
            ]);
        }

        $totalGeneralFmt = "$" . number_format((float)$totalGeneral, 2, '.', ',');

        $mpdf = new \Mpdf\Mpdf([
            'tempDir' => sys_get_temp_dir(),
            'format'  => 'LETTER',
            'mode'    => 'utf-8',
        ]);
        $mpdf->SetTitle('Entregado a Unidad');
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
                <h1 style='font-size: 16px; margin: 0; color: #003366; text-transform: uppercase;'>
                    ALCALDÍA MUNICIPAL DE SANTA ANA NORTE
                </h1>
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
        <h1 style='font-size: 15px; margin: 0; color: #000;'>
            ENTREGADO A UNIDAD: " . ($infoDepartamento->nombre ?? '') . "
        </h1>
        <p style='font-size: 13px; margin: 5px 0 0 0; color: #000;'>
            <strong>DESDE: $desdeFormat &nbsp;&nbsp; HASTA: $hastaFormat</strong>
        </p>
    </div>
    ";

        $tabla .= "
    <table id='tablaFor'
           style='width: 100%;
                  border-collapse: collapse;
                  margin-top: 25px'>

        <tbody>

            <tr>
                <th style='text-align: center; font-size:12px; width: 10%; font-weight: bold; border: 1px solid black;'>Fecha</th>
                <th style='text-align: center; font-size:12px; width: 28%; font-weight: bold; border: 1px solid black;'>Material</th>
                <th style='text-align: center; font-size:12px; width: 10%; font-weight: bold; border: 1px solid black;'>U.M</th>
                <th style='text-align: center; font-size:12px; width: 12%; font-weight: bold; border: 1px solid black;'>Cant. Entregada</th>
                <th style='text-align: center; font-size:12px; width: 12%; font-weight: bold; border: 1px solid black;'>Precio</th>
                <th style='text-align: center; font-size:12px; width: 13%; font-weight: bold; border: 1px solid black;'>Total</th>
                <th style='text-align: center; font-size:12px; width: 15%; font-weight: bold; border: 1px solid black;'>N° Solicitud</th>
            </tr>
    ";

        foreach ($arrayDetalle as $fila) {
            $tabla .= "
        <tr>
            <td style='text-align: center; font-size:11px; border: 1px solid black; padding: 3px;'>{$fila->fechaFormat}</td>
            <td style='text-align: left; font-size:11px; border: 1px solid black; padding: 3px;'>{$fila->nombreMaterial}</td>
            <td style='text-align: center; font-size:11px; border: 1px solid black;'>{$fila->unidadMedida}</td>
            <td style='text-align: center; font-size:11px; border: 1px solid black;'>{$fila->cantidadSalida}</td>
            <td style='text-align: right; font-size:11px; border: 1px solid black; padding: 3px;'>{$fila->precioFormat}</td>
            <td style='text-align: right; font-size:11px; border: 1px solid black; padding: 3px;'>{$fila->multiplicado}</td>
            <td style='text-align: center; font-size:11px; border: 1px solid black;'>{$fila->numeroSolicitud}</td>
        </tr>";
        }

        $tabla .= "
        <tr>
            <td colspan='5' style='text-align: right; font-size:13px; font-weight: bold; border: 1px solid black; padding: 3px;'>
                Total General
            </td>
            <td style='text-align: right; font-size:13px; font-weight: bold; border: 1px solid black; padding: 3px;'>
                $totalGeneralFmt
            </td>
            <td style='border: 1px solid black;'></td>
        </tr>
    </tbody></table>";

        $stylesheet = file_get_contents('css/cssbodega.css');
        $mpdf->WriteHTML($stylesheet, 1);

        $mpdf->setFooter('Página: {PAGENO}/{nb}');
        $mpdf->WriteHTML($tabla, 2);

        $mpdf->Output();
    }



    public function reporteEntregadoPorMaterial($desde, $hasta, $idmaterial)
    {
        $infoMaterial = Materiales::where('id', $idmaterial)->first();

        $desdeFormat = date('d/m/Y', strtotime($desde));
        $hastaFormat = date('d/m/Y', strtotime($hasta));

        $arrayBodegaSalidaDetalle = DB::table('salidas_detalle AS salideta')
            ->join('entradas_detalle AS entradadetalle', 'salideta.id_entrada_detalle', '=', 'entradadetalle.id')
            ->select(
                'entradadetalle.id_material',
                'salideta.cantidad_salida',
                'salideta.fecha',
                'salideta.id_departamento'
            )
            ->where('entradadetalle.id_material', $idmaterial)
            ->whereBetween('salideta.fecha', [$desde, $hasta])
            ->orderBy('salideta.fecha', 'ASC')
            ->get();

        foreach ($arrayBodegaSalidaDetalle as $filaP) {

            $filaP->fechaFormat = date('d-m-Y', strtotime($filaP->fecha));

            $unidad = "";
            if ($filaP->id_departamento != null) {
                $infoDepartamento = Departamentos::where('id', $filaP->id_departamento)->first();
                $unidad = $infoDepartamento->nombre ?? '';
            }

            $filaP->unidad = $unidad;
        }

        $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER', 'mode' => 'utf-8']);
        $mpdf->SetTitle('Salidas por Material');
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
            <h2 style='font-size: 14px; margin: 0; color: #003366; text-transform: uppercase;'>UNIDAD DE PROVEEDURÍA Y BODEGA</h2>
        </td>
        <td style='width: 10%; text-align: right;'>
            <img src='$logoalcaldia' alt='Gobierno de El Salvador' style='max-width: 60px; height: auto;'>
        </td>
    </tr>
</table>
<hr style='border: none; border-top: 2px solid #003366; margin: 0;'>
";

        $tabla .= "


<div style='text-align: left; margin-top: 10px;'>
    <p style='font-size: 13px; margin: 2px 0; color: #000;'>
        Material: <strong>$infoMaterial->nombre</strong>
    </p>
    <p style='font-size: 13px; margin: 2px 0; color: #000;'>
        Período: <strong>$desdeFormat</strong> al <strong>$hastaFormat</strong>
    </p>
</div>
";

        $tabla .= "
<table style='width: 100%; border-collapse: collapse; margin-top: 20px;'>
    <tbody>
        <tr>
            <th style='text-align: center; font-size: 13px; width: 20%; font-weight: bold; border: 1px solid black; padding: 4px;'>
                F. Salida
            </th>
            <th style='text-align: center; font-size: 13px; width: 60%; font-weight: bold; border: 1px solid black; padding: 4px;'>
                Unidad Alcaldía
            </th>
            <th style='text-align: center; font-size: 13px; width: 20%; font-weight: bold; border: 1px solid black; padding: 4px;'>
                Cantidad Entregada
            </th>
        </tr>
";

        if ($arrayBodegaSalidaDetalle->isEmpty()) {
            $tabla .= "
        <tr>
            <td colspan='3' style='text-align: center; font-size: 12px; border: 1px solid black; padding: 6px; color: #888;'>
                Sin registros en el rango seleccionado.
            </td>
        </tr>";
        } else {
            foreach ($arrayBodegaSalidaDetalle as $fila) {
                $tabla .= "
        <tr>
            <td style='text-align: center; font-size: 12px; border: 1px solid black; padding: 3px;'>
                $fila->fechaFormat
            </td>
            <td style='text-align: left; font-size: 12px; border: 1px solid black; padding: 3px;'>
                $fila->unidad
            </td>
            <td style='text-align: center; font-size: 12px; border: 1px solid black; padding: 3px;'>
                $fila->cantidad_salida
            </td>
        </tr>";
            }
        }

        $tabla .= "
    </tbody>
</table>
";

        $infoGeneral = InformacionGeneral::where('id', 1)->first();
        $spacer = "<div style='height: " . $infoGeneral->px_firmas . "px;'></div>";

        $tabla .= "
$spacer
<div style='text-align:center; font-size:16px;'>
    F._____________________________<br><br>
    <span style='display:block; margin-top:8px; font-weight:bold; font-size:16px;'>
        Unidad de Proveeduría y Bodega
    </span>
</div>
";

        $stylesheet = file_get_contents('css/cssbodega.css');
        $mpdf->WriteHTML($stylesheet, 1);
        $mpdf->setFooter('Página: {PAGENO}/{nb}');
        $mpdf->WriteHTML($tabla, 2);
        $mpdf->Output();
    }


}
