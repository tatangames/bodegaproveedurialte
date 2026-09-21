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


    public function generarPDFExistencias($conteo = 0)
    {
        $conteo = (int) $conteo === 1;

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
                (int)$fila->cantidad_inicial
                - (int)$totalSalido;

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
                    'nombreMaterial' =>
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
            'format' => $conteo ? 'LETTER-L' : 'LETTER',
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

        // ── Anchos y padding según si se piden columnas de conteo físico ──
        if ($conteo) {
            $wObjEsp    = '12%';
            $wProducto  = '26%';
            $wUM        = '8%';
            $wCantidad  = '10%';
            $wPrecio    = '11%';
            $wTotal     = '11%';
            $wConteo    = '11%';
            $wDiferencia = '11%';
            $tdPadding  = '8px';
        } else {
            $wObjEsp    = '15%';
            $wProducto  = '35%';
            $wUM        = '10%';
            $wCantidad  = '13%';
            $wPrecio    = '12%';
            $wTotal     = '15%';
            $tdPadding  = '3px';
        }

        // ── Tabla principal ──
        $tabla .= "
<table id='tablaFor'
       style='width: 100%;
              border-collapse: collapse;
              margin-top: 35px'>

    <tbody>

        <tr>
            <th style='text-align: center; font-size:14px; width: $wObjEsp; font-weight: bold; border: 1px solid black; padding: $tdPadding;'>
                Obj. Específico
            </th>

            <th style='text-align: center; font-size:14px; width: $wProducto; font-weight: bold; border: 1px solid black; padding: $tdPadding;'>
                Producto
            </th>

            <th style='text-align: center; font-size:14px; width: $wUM; font-weight: bold; border: 1px solid black; padding: $tdPadding;'>
                U.M
            </th>

            <th style='text-align: center; font-size:14px; width: $wCantidad; font-weight: bold; border: 1px solid black; padding: $tdPadding;'>
                Cantidad
            </th>

            <th style='text-align: center; font-size:14px; width: $wPrecio; font-weight: bold; border: 1px solid black; padding: $tdPadding;'>
                Precio
            </th>

            <th style='text-align: center; font-size:14px; width: $wTotal; font-weight: bold; border: 1px solid black; padding: $tdPadding;'>
                Total
            </th>";

        if ($conteo) {
            $tabla .= "
            <th style='text-align: center; font-size:14px; width: $wConteo; font-weight: bold; border: 1px solid black; padding: $tdPadding;'>
                Conteo Físico
            </th>

            <th style='text-align: right; font-size:14px; width: $wDiferencia; font-weight: bold; border: 1px solid black; padding: $tdPadding;'>
                Diferencia
            </th>";
        }

        $tabla .= "
        </tr>
";

        foreach ($arrayDetalle as $fila) {

            $tabla .= "
    <tr>

        <td style='text-align: center; font-size:12px; border: 1px solid black; padding: $tdPadding;'>
            $fila->nombreCodigo
        </td>

        <td style='text-align: left; font-size:12px; border: 1px solid black; padding: $tdPadding;'>
            $fila->nombreMaterial
        </td>

        <td style='text-align: center; font-size:12px; border: 1px solid black; padding: $tdPadding;'>
            $fila->unidadMedida
        </td>

        <td style='text-align: center; font-size:12px; border: 1px solid black; padding: $tdPadding;'>
            $fila->cantidadActual
        </td>

        <td style='text-align: right; font-size:12px; border: 1px solid black; padding: $tdPadding;'>
            $fila->precioFormat
        </td>

        <td style='text-align: right; font-size:12px; border: 1px solid black; padding: $tdPadding;'>
            $fila->multiplicado
        </td>";

            if ($conteo) {
                $tabla .= "
        <td style='text-align: right; font-size:12px; border: 1px solid black; padding: $tdPadding;'>
            &nbsp;
        </td>

        <td style='text-align: right; font-size:12px; border: 1px solid black; padding: $tdPadding;'>
            &nbsp;
        </td>";
            }

            $tabla .= "
    </tr>";
        }

        // Fila de total:
        // Etiqueta "Total" cubre Obj.Específico + Producto + U.M + Cantidad (4 columnas)
        // Valor cubre Precio + Total (2 columnas)
        // Si hay conteo, se agregan 2 celdas vacías para Conteo Físico y Diferencia
        $tabla .= "
    <tr>
        <td colspan='4' style='text-align: right; font-size:14px; font-weight: bold; border: 1px solid black; padding: $tdPadding;'>
            Total
        </td>
        <td colspan='2' style='text-align: center; font-size:13px; font-weight: bold; border: 1px solid black; padding: $tdPadding;'>
            $totalColumnaFmt
        </td>";

        if ($conteo) {
            $tabla .= "
        <td colspan='2' style='border: 1px solid black; padding: $tdPadding;'>
            &nbsp;
        </td>";
        }

        $tabla .= "
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
                           border:1px solid black;
                           padding: $tdPadding;'>

                    Producto
                </th>

                <th style='text-align:center;
                           font-size:14px;
                           width:12%;
                           font-weight:bold;
                           border:1px solid black;
                           padding: $tdPadding;'>

                    Cant. Salida
                </th>

                <th style='text-align:center;
                           font-size:14px;
                           width:25%;
                           font-weight:bold;
                           border:1px solid black;
                           padding: $tdPadding;'>

                    Descripción Salida
                </th>

                <th style='text-align:center;
                           font-size:14px;
                           width:35%;
                           font-weight:bold;
                           border:1px solid black;
                           padding: $tdPadding;'>

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
                       padding:$tdPadding;
                       vertical-align:top;'>

                {$pend->nombreMaterial}
            </td>

            <td style='text-align:center;
                       font-size:14px;
                       border:1px solid black;
                       padding:$tdPadding;
                       vertical-align:top;'>

                {$pend->cantidad_salida}
                {$pend->unidadMedida}
            </td>

            <td style='text-align:left;
                       font-size:13px;
                       border:1px solid black;
                       padding:$tdPadding;
                       vertical-align:top;'>

                {$descripcionSalida}
            </td>

            <td style='text-align:left;
                       font-size:14px;
                       border:1px solid black;
                       padding:$tdPadding;'>

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

        $rows = DB::select("
        WITH movimientos AS (

            SELECT
                ed.id_material,
                COALESCE(NULLIF(oe.codigo, ''), 'SIN-CODIGO') AS codigo,
                m.nombre AS descripcion,
                ed.precio,
                e.fecha AS fecha_movimiento,
                ed.cantidad_inicial AS entrada,
                0 AS salida,
                (ed.cantidad_inicial * ed.precio) AS monto_entrada,
                0 AS monto_salida
            FROM entradas_detalle ed
            INNER JOIN entradas e ON e.id = ed.id_entradas
            INNER JOIN materiales m ON m.id = ed.id_material
            LEFT JOIN objeto_especifico oe ON oe.id = m.id_objespecifico

            UNION ALL

            SELECT
                ed.id_material,
                COALESCE(NULLIF(oe.codigo, ''), 'SIN-CODIGO') AS codigo,
                m.nombre AS descripcion,
                ed.precio,
                COALESCE(
                    STR_TO_DATE(sd.fecha, '%Y-%m-%d %H:%i:%s'),
                    STR_TO_DATE(sd.fecha, '%Y-%m-%d'),
                    STR_TO_DATE(sd.fecha, '%d/%m/%Y')
                ) AS fecha_movimiento,
                0 AS entrada,
                sd.cantidad_salida AS salida,
                0 AS monto_entrada,
                (sd.cantidad_salida * ed.precio) AS monto_salida
            FROM salidas_detalle sd
            INNER JOIN entradas_detalle ed ON ed.id = sd.id_entrada_detalle
            INNER JOIN materiales m ON m.id = ed.id_material
            LEFT JOIN objeto_especifico oe ON oe.id = m.id_objespecifico
        )

        SELECT
            id_material,
            codigo,
            descripcion,
            MAX(precio) AS precio,

            SUM(CASE WHEN fecha_movimiento < ? THEN entrada - salida ELSE 0 END) AS saldo_inicial_cant,

            SUM(CASE WHEN fecha_movimiento >= ? AND fecha_movimiento <= ? THEN entrada ELSE 0 END) AS entradas_mes_cant,

            SUM(CASE WHEN fecha_movimiento >= ? AND fecha_movimiento <= ? THEN salida ELSE 0 END) AS salidas_mes_cant,

            (
                SUM(CASE WHEN fecha_movimiento < ? THEN entrada - salida ELSE 0 END)
                + SUM(CASE WHEN fecha_movimiento >= ? AND fecha_movimiento <= ? THEN entrada ELSE 0 END)
                - SUM(CASE WHEN fecha_movimiento >= ? AND fecha_movimiento <= ? THEN salida ELSE 0 END)
            ) AS saldo_final_cant,

            SUM(CASE WHEN fecha_movimiento < ? THEN monto_entrada - monto_salida ELSE 0 END) AS saldo_inicial_money,

            SUM(CASE WHEN fecha_movimiento >= ? AND fecha_movimiento <= ? THEN monto_entrada ELSE 0 END) AS entradas_mes_money,

            SUM(CASE WHEN fecha_movimiento >= ? AND fecha_movimiento <= ? THEN monto_salida ELSE 0 END) AS salidas_mes_money,

            (
                SUM(CASE WHEN fecha_movimiento < ? THEN monto_entrada - monto_salida ELSE 0 END)
                + SUM(CASE WHEN fecha_movimiento >= ? AND fecha_movimiento <= ? THEN monto_entrada ELSE 0 END)
                - SUM(CASE WHEN fecha_movimiento >= ? AND fecha_movimiento <= ? THEN monto_salida ELSE 0 END)
            ) AS saldo_final_money

        FROM movimientos
        GROUP BY id_material, codigo, descripcion
        ORDER BY codigo, descripcion
    ", [
            // saldo_inicial_cant
            $start->toDateString(),

            // entradas_mes_cant
            $start->toDateString(), $end->toDateString(),

            // salidas_mes_cant
            $start->toDateString(), $end->toDateString(),

            // saldo_final_cant
            $start->toDateString(),
            $start->toDateString(), $end->toDateString(),
            $start->toDateString(), $end->toDateString(),

            // saldo_inicial_money
            $start->toDateString(),

            // entradas_mes_money
            $start->toDateString(), $end->toDateString(),

            // salidas_mes_money
            $start->toDateString(), $end->toDateString(),

            // saldo_final_money
            $start->toDateString(),
            $start->toDateString(), $end->toDateString(),
            $start->toDateString(), $end->toDateString(),
        ]);

        $rows = array_values(array_filter($rows, function ($r) {
            $inicial  = (float) ($r->saldo_inicial_cant ?? 0);
            $entradas = (float) ($r->entradas_mes_cant ?? 0);
            $salidas  = (float) ($r->salidas_mes_cant ?? 0);
            $final    = (float) ($r->saldo_final_cant ?? 0);

            return !($inicial == 0 && $entradas == 0 && $salidas == 0 && $final == 0);
        }));

        $totales = [
            'inicial_cant'   => 0,
            'entradas_cant'  => 0,
            'salidas_cant'   => 0,
            'final_cant'     => 0,
            'inicial_money'  => 0.0,
            'entradas_money' => 0.0,
            'salidas_money'  => 0.0,
            'final_money'    => 0.0,
        ];

        $sumPorCodigo = [];

        foreach ($rows as $r) {
            $totales['inicial_cant']   += (int)   ($r->saldo_inicial_cant  ?? 0);
            $totales['entradas_cant']  += (int)   ($r->entradas_mes_cant   ?? 0);
            $totales['salidas_cant']   += (int)   ($r->salidas_mes_cant    ?? 0);
            $totales['final_cant']     += (int)   ($r->saldo_final_cant    ?? 0);
            $totales['inicial_money']  += (float) ($r->saldo_inicial_money ?? 0);
            $totales['entradas_money'] += (float) ($r->entradas_mes_money  ?? 0);
            $totales['salidas_money']  += (float) ($r->salidas_mes_money   ?? 0);
            $totales['final_money']    += (float) ($r->saldo_final_money   ?? 0);

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

            $sumPorCodigo[$codigo]['inicial_cant']   += (int)   ($r->saldo_inicial_cant  ?? 0);
            $sumPorCodigo[$codigo]['entradas_cant']  += (int)   ($r->entradas_mes_cant   ?? 0);
            $sumPorCodigo[$codigo]['salidas_cant']   += (int)   ($r->salidas_mes_cant    ?? 0);
            $sumPorCodigo[$codigo]['final_cant']     += (int)   ($r->saldo_final_cant    ?? 0);
            $sumPorCodigo[$codigo]['inicial_money']  += (float) ($r->saldo_inicial_money ?? 0);
            $sumPorCodigo[$codigo]['entradas_money'] += (float) ($r->entradas_mes_money  ?? 0);
            $sumPorCodigo[$codigo]['salidas_money']  += (float) ($r->salidas_mes_money   ?? 0);
            $sumPorCodigo[$codigo]['final_money']    += (float) ($r->saldo_final_money   ?? 0);
        }

        // ── mPDF ──────────────────────────────────────────────────────────────
        $mpdf = new \Mpdf\Mpdf([
            'tempDir'     => sys_get_temp_dir(),
            'format'      => 'LETTER',
            'orientation' => 'L',
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

        // ── Tabla principal ───────────────────────────────────────────────────
        $html = $encabezado;

        $html .= "
<table width='100%' border='1' cellspacing='0' cellpadding='4' style='border-collapse:collapse; font-size:11px; margin-top:8px'>
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
        <td style='text-align:right'>$" . number_format($r->precio ?? 0, 4) . "</td>
        <td style='text-align:right'>" . number_format($r->saldo_inicial_cant ?? 0) . "</td>
        <td style='text-align:right'>$" . number_format($r->saldo_inicial_money ?? 0, 2) . "</td>
        <td style='text-align:right'>" . number_format($r->entradas_mes_cant ?? 0) . "</td>
        <td style='text-align:right'>$" . number_format($r->entradas_mes_money ?? 0, 2) . "</td>
        <td style='text-align:right'>" . number_format($r->salidas_mes_cant ?? 0) . "</td>
        <td style='text-align:right'>$" . number_format($r->salidas_mes_money ?? 0, 2) . "</td>
        <td style='text-align:right'>" . number_format($r->saldo_final_cant ?? 0) . "</td>
        <td style='text-align:right'>$" . number_format($r->saldo_final_money ?? 0, 2) . "</td>
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
            <td style='text-align:right'>$" . number_format($totales['inicial_money'], 2) . "</td>
            <td style='text-align:right'>" . number_format($totales['entradas_cant']) . "</td>
            <td style='text-align:right'>$" . number_format($totales['entradas_money'], 2) . "</td>
            <td style='text-align:right'>" . number_format($totales['salidas_cant']) . "</td>
            <td style='text-align:right'>$" . number_format($totales['salidas_money'], 2) . "</td>
            <td style='text-align:right'>" . number_format($totales['final_cant']) . "</td>
            <td style='text-align:right'>$" . number_format($totales['final_money'], 2) . "</td>
        </tr>
    </tfoot>
</table>
";

        // ── Resumen del período ───────────────────────────────────────────────
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
        <td style='text-align:right'>$" . number_format($totales['entradas_money'], 2) . "</td>
    </tr>
    <tr>
        <td>Salió (Salidas del mes)</td>
        <td style='text-align:right'>" . number_format($totales['salidas_cant']) . "</td>
        <td style='text-align:right'>$" . number_format($totales['salidas_money'], 2) . "</td>
    </tr>
    <tr>
        <td>Disponible al cierre (Saldo final)</td>
        <td style='text-align:right'>" . number_format($totales['final_cant']) . "</td>
        <td style='text-align:right'>$" . number_format($totales['final_money'], 2) . "</td>
    </tr>
</table>
";

        // ── Tabla resumen por código ──────────────────────────────────────────
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
            foreach ($sumPorCodigo as $s) {
                $totalSaldoFinalCodigos += (float) $s['final_money'];

                $html .= "
        <tr>
            <td>{$j}</td>
            <td>" . e($s['codigo']) . "</td>
            <td style='text-align:right'>" . number_format($s['inicial_cant']) . "</td>
            <td style='text-align:right'>$" . number_format($s['inicial_money'], 2) . "</td>
            <td style='text-align:right'>" . number_format($s['entradas_cant']) . "</td>
            <td style='text-align:right'>$" . number_format($s['entradas_money'], 2) . "</td>
            <td style='text-align:right'>" . number_format($s['salidas_cant']) . "</td>
            <td style='text-align:right'>$" . number_format($s['salidas_money'], 2) . "</td>
            <td style='text-align:right'>" . number_format($s['final_cant']) . "</td>
            <td style='text-align:right'>$" . number_format($s['final_money'], 2) . "</td>
        </tr>
";
                $j++;
            }

            $html .= "
        <tr style='font-weight:bold; background:#f9fafb'>
            <td colspan='9' style='text-align:right'>TOTAL</td>
            <td style='text-align:right'>$" . number_format($totalSaldoFinalCodigos, 2) . "</td>
        </tr>
    </tbody>
</table>
";
        }

        // ── Firma ─────────────────────────────────────────────────────────────
        $informacionGeneral = InformacionGeneral::where('id', 1)->first();
        $margenFirma = $informacionGeneral->px_firmas ?? '40px';

        $html .= "
<div style='text-align:center; font-size:13px; margin-top:{$margenFirma};'>
    F._____________________________<br>
    <span style='font-weight:bold; font-size:14px;'>Unidad de Proveeduría y Bodega</span>
</div>
";

        $mpdf->setFooter('Página {PAGENO} de {nb}');
        $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);
        $mpdf->Output();
    }


    public function generarPDFEntregados($desde, $hasta, $idDepartamento)
    {
        $start = Carbon::parse($desde)->startOfDay();
        $end = Carbon::parse($hasta)->endOfDay();

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
            $material = $entradaDetalle->material ?? null;

            if (!$material) {
                continue;
            }

            $precio = (float)$entradaDetalle->precio;
            $multiplicado = $fila->cantidad_salida * $precio;
            $totalGeneral += $multiplicado;

            $arrayDetalle->push((object)[
                'fechaFormat' => date("d-m-Y", strtotime($fila->fecha)),
                'nombreMaterial' => $material->nombre ?? '',
                'unidadMedida' => $material->unidadMedida->nombre ?? '',
                'cantidadSalida' => $fila->cantidad_salida,
                'precioFormat' => "$" . number_format($precio, 6, '.', ','),
                'multiplicado' => "$" . number_format((float)$multiplicado, 6, '.', ','),
                'numeroSolicitud' => $fila->numero_solicitud ?? '',
                'descripcion' => $fila->descripcion ?? '',
                'estado' => $fila->estado,
            ]);
        }

        $totalGeneralFmt = "$" . number_format((float)$totalGeneral, 2, '.', ',');

        $mpdf = new \Mpdf\Mpdf([
            'tempDir' => sys_get_temp_dir(),
            'format' => 'LETTER',
            'mode' => 'utf-8',
        ]);
        $mpdf->SetTitle('Entregado a Unidad');
        $mpdf->showImageErrors = false;

        $logoalcaldia = 'images/gobiernologo.jpg';

        $tabla = "
<table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:6px;'>
    <tr>
        <td style='width:25%; border:0.8px solid #000; padding:6px 8px;'>
            <table width='100%'>
                <tr>
                    <td style='width:35%; text-align:left;'>
                        <img src='{$logoalcaldia}' style='height:36px'>
                    </td>
                    <td style='width:65%; text-align:left; color:#003366;
                                font-size:10px; font-weight:bold; line-height:1.3;'>
                        SANTA ANA NORTE<br>EL SALVADOR
                    </td>
                </tr>
            </table>
        </td>
        <td style='width:54%; border-top:0.8px solid #000; border-bottom:0.8px solid #000;
                    padding:8px; text-align:center; vertical-align:middle;'>
            <div style='font-size:14px; font-weight:bold; color:#000000; letter-spacing:1px;'>
               FICHA DE ENTREGA POR UNIDAD
            </div>
        </td>
        <td style='width:22%; border:0.8px solid #000; padding:0; vertical-align:top;'>
            <table width='100%' style='font-size:9px; border-collapse:collapse;'>
                <tr>
                    <td style='border-right:0.8px solid #000; border-bottom:0.8px solid #000;
                                padding:3px 5px; font-weight:bold; color:#000000;'>Código:</td>
                    <td style='border-bottom:0.8px solid #000; padding:3px 5px;
                                text-align:center; color:#000000;'>
                        PROV-001-FICH
                    </td>
                </tr>
                <tr>
                    <td style='border-right:0.8px solid #000; border-bottom:0.8px solid #000;
                                padding:3px 5px; font-weight:bold; color:#000000;'>Versión:</td>
                    <td style='border-bottom:0.8px solid #000; padding:3px 5px;
                                text-align:center; color:#000000;'>
                        001
                    </td>
                </tr>
                <tr>
                    <td style='border-right:0.8px solid #000; padding:3px 5px;
                                font-weight:bold; color:#000000;'>Vigencia:</td>
                    <td style='padding:3px 5px; text-align:center; color:#000000;'>09/07/2026</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

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
    </tbody></table>


    <br><br>

<div style='text-align:left; font-size:13px;'>
    Ficha generada por:<br><br><br><br>
    <strong>Unidad de proveeduría y bodega</strong>
</div>

    ";

        $stylesheet = file_get_contents('css/cssbodega.css');
        $mpdf->WriteHTML($stylesheet, 1);
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

        $logoalcaldia = 'images/logo.png';

        // ── HEADER sin tablas anidadas ──────────────────────────────────────────
        $tabla = "
<table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:6px;'>
    <tr>
        <td style='width:25%; border:0.8px solid #000; padding:6px 8px;'>
            <table width='100%'>
                <tr>
                    <td style='width:35%; text-align:left;'>
                        <img src='{$logoalcaldia}' style='height:36px'>
                    </td>
                    <td style='width:65%; text-align:left; color:#003366;
                                font-size:10px; font-weight:bold; line-height:1.3;'>
                        SANTA ANA NORTE<br>EL SALVADOR
                    </td>
                </tr>
            </table>
        </td>
        <td style='width:54%; border-top:0.8px solid #000; border-bottom:0.8px solid #000;
                    padding:8px; text-align:center; vertical-align:middle;'>
            <div style='font-size:14px; font-weight:bold; color:#000000; letter-spacing:1px;'>
               REPORTE DE SALIDAS POR MATERIAL
            </div>
        </td>
        <td style='width:22%; border:0.8px solid #000; padding:0; vertical-align:top;'>
            <table width='100%' style='font-size:9px; border-collapse:collapse;'>
                <tr>
                    <td style='border-right:0.8px solid #000; border-bottom:0.8px solid #000;
                                padding:3px 5px; font-weight:bold; color:#000000;'>Código:</td>
                    <td style='border-bottom:0.8px solid #000; padding:3px 5px;
                                text-align:center; color:#000000;'>
                        PROV-003-REPO
                    </td>
                </tr>
                <tr>
                    <td style='border-right:0.8px solid #000; border-bottom:0.8px solid #000;
                                padding:3px 5px; font-weight:bold; color:#000000;'>Versión:</td>
                    <td style='border-bottom:0.8px solid #000; padding:3px 5px;
                                text-align:center; color:#000000;'>
                        000
                    </td>
                </tr>
                <tr>
                    <td style='border-right:0.8px solid #000; padding:3px 5px;
                                font-weight:bold; color:#000000;'>Vigencia:</td>
                    <td style='padding:3px 5px; text-align:center; color:#000000;'>09/07/2026</td>
                </tr>
            </table>
        </td>
    </tr>
</table>";

        // ── INFO MATERIAL Y PERÍODO ─────────────────────────────────────────────
        $tabla .= "
<div style='text-align: left; margin-top: 10px;'>
    <p style='font-size: 13px; margin: 2px 0; color: #000;'>
        Material: <strong>{$infoMaterial->nombre}</strong>
    </p>
    <p style='font-size: 13px; margin: 2px 0; color: #000;'>
        Período: <strong>$desdeFormat</strong> al <strong>$hastaFormat</strong>
    </p>
</div>";

        // ── TABLA DE DATOS ──────────────────────────────────────────────────────
        $tabla .= "
<table style='width:100%; border-collapse:collapse; margin-top:20px;'>
    <tbody>
        <tr>
            <th style='text-align:center; font-size:13px; width:20%; font-weight:bold;
                        border:1px solid black; padding:4px;'>
                Fecha de salida
            </th>
            <th style='text-align:center; font-size:13px; width:60%; font-weight:bold;
                        border:1px solid black; padding:4px;'>
                Unidad Solicitante
            </th>
            <th style='text-align:center; font-size:13px; width:20%; font-weight:bold;
                        border:1px solid black; padding:4px;'>
                Cantidad entregada
            </th>
        </tr>";

        if ($arrayBodegaSalidaDetalle->isEmpty()) {
            $tabla .= "
        <tr>
            <td colspan='3' style='text-align:center; font-size:12px;
                                    border:1px solid black; padding:6px; color:#888;'>
                Sin registros en el rango seleccionado.
            </td>
        </tr>";
        } else {
            foreach ($arrayBodegaSalidaDetalle as $fila) {
                $tabla .= "
        <tr>
            <td style='text-align:center; font-size:12px; border:1px solid black; padding:3px;'>
                {$fila->fechaFormat}
            </td>
            <td style='text-align:left; font-size:12px; border:1px solid black; padding:3px;'>
                {$fila->unidad}
            </td>
            <td style='text-align:center; font-size:12px; border:1px solid black; padding:3px;'>
                {$fila->cantidad_salida}
            </td>
        </tr>";
            }

            // ── FILA TOTALES ────────────────────────────────────────────────────
            $totalCantidad = $arrayBodegaSalidaDetalle->sum('cantidad_salida');

            $tabla .= "
        <tr>
            <td colspan='2' style='text-align:right; font-size:12px; font-weight:bold;
                                    border:1px solid black; padding:3px;'>
                Salidas Totales:
            </td>
            <td style='text-align:center; font-size:12px; font-weight:bold;
                        border:1px solid black; padding:3px;'>
                $totalCantidad
            </td>
        </tr>";
        }

        $tabla .= "
    </tbody>
</table>";

        // ── FIRMA ───────────────────────────────────────────────────────────────
        $infoGeneral = InformacionGeneral::where('id', 1)->first();
        $spacer = "<div style='height:{$infoGeneral->px_firmas}px;'></div>";

        $tabla .= "
    $spacer
    <div style='text-align:left; font-size:14px;'>
        F._____________________________<br>
        <span style='display:block; margin-top:2px; font-weight:bold; font-size:14px;'>
            Unidad de proveeduría y bodega
        </span>
    </div>";

        // ── RENDER PDF ──────────────────────────────────────────────────────────
        $stylesheet = file_get_contents('css/cssbodega.css');
        $mpdf->WriteHTML($stylesheet, 1);
        $mpdf->WriteHTML($tabla, 2);
        $mpdf->Output();
    }




    public function pdfQueHaEntradoProyectos($desde, $hasta, $tipo = 2)
    {
        $sinFecha     = ($desde === 'null' || $desde === '' || $hasta === 'null' || $hasta === '');
        $fechaHoy     = Carbon::now('America/El_Salvador')->format('d-m-Y');
        $logoalcaldia = 'images/logo.png';

        if (!$sinFecha) {
            $start      = date('Y-m-d 00:00:00', strtotime($desde));
            $end        = date('Y-m-d 23:59:59', strtotime($hasta));
            $fechaLabel = date('d-m-Y', strtotime($desde)) . '  —  ' . date('d-m-Y', strtotime($hasta));
        } else {
            $fechaLabel = 'Todas las fechas';
        }

        $encabezado = "
<table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif;'>
    <tr>
        <td style='width:25%; border:0.8px solid #000; padding:6px 8px;'>
            <table width='100%'><tr>
                <td style='width:30%; text-align:left;'><img src='{$logoalcaldia}' style='height:38px'></td>
                <td style='width:70%; text-align:left; color:#104e8c; font-size:13px; font-weight:bold; line-height:1.3;'>SANTA ANA NORTE<br>EL SALVADOR</td>
            </tr></table>
        </td>
        <td style='width:50%; border-top:0.8px solid #000; border-bottom:0.8px solid #000; padding:6px 8px; text-align:center; font-size:15px; font-weight:bold;'>
            REPORTE DE MATERIALES RECIBIDOS
        </td>
        <td style='width:25%; border:0.8px solid #000; padding:0; vertical-align:top;'>
            <table width='100%' style='font-size:10px;'>
                <tr>
                    <td width='40%' style='border-right:0.8px solid #000; border-bottom:0.8px solid #000; padding:4px 6px;'><strong>Código:</strong></td>
                    <td width='60%' style='border-bottom:0.8px solid #000; padding:4px 6px; text-align:center;'></td>
                </tr>
                <tr>
                    <td style='border-right:0.8px solid #000; border-bottom:0.8px solid #000; padding:4px 6px;'><strong>Versión:</strong></td>
                    <td style='border-bottom:0.8px solid #000; padding:4px 6px; text-align:center;'>000</td>
                </tr>
                <tr>
                    <td style='border-right:0.8px solid #000; padding:4px 6px;'><strong>Fecha de vigencia:</strong></td>
                    <td style='padding:4px 6px; text-align:center;'></td>
                </tr>
            </table>
        </td>
    </tr>
</table><br>
<table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:8px;'>
    <tr>
        <td style='width:15%; border:0.8px solid #ccc; padding:6px 8px; font-size:11px; font-weight:bold; background:#f5f5f5;'>PERIODO</td>
        <td style='width:50%; border:0.8px solid #ccc; padding:6px 8px; font-size:11px;'>$fechaLabel</td>
        <td style='width:15%; border:0.8px solid #ccc; padding:6px 8px; font-size:11px; font-weight:bold; background:#f5f5f5; text-align:center;'>FECHA</td>
        <td style='width:20%; border:0.8px solid #ccc; padding:6px 8px; font-size:11px; text-align:center;'>$fechaHoy</td>
    </tr>
</table>";

        $granTotal         = 0;
        $sumaTotalCantidad = 0;
        $tabla             = $encabezado;

        // ── Acumulador para el resumen de Objetos Específicos ─────────────────
        $resumenObjEsp = [];

        // ── CABECERA COLUMNAS ─────────────────────────────────────────────
        $theadEntradas = "
<table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:8px; border:0.8px solid #aaa;'>
    <thead>
        <tr style='background:#6c757d;'>
            <td style='font-weight:bold; width:11%; font-size:11px; color:#fff; padding:5px 6px; border:0.8px solid #888;'>Cod. Presu.</td>
            <td style='font-weight:bold; width:32%; font-size:11px; color:#fff; padding:5px 6px; border:0.8px solid #888;'>Material</td>
            <td style='font-weight:bold; width:12%; font-size:11px; color:#fff; padding:5px 6px; border:0.8px solid #888;'>Medida</td>
            <td style='font-weight:bold; width:10%; font-size:11px; color:#fff; padding:5px 6px; border:0.8px solid #888;'>Cantidad</td>
            <td style='font-weight:bold; width:14%; font-size:11px; color:#fff; padding:5px 6px; border:0.8px solid #888;'>Precio Unit.</td>
            <td style='font-weight:bold; width:14%; font-size:11px; color:#fff; padding:5px 6px; border:0.8px solid #888;'>Total (\$)</td>
        </tr>
    </thead>
    <tbody>";

        // ════════════════════════════════════════════════════════════════
        // TIPO 1: JUNTOS
        // ════════════════════════════════════════════════════════════════
        if ($tipo == 1) {

            $query = Entradas::with([
                'detalle.material.unidadMedida',
                'detalle.material.objetoEspecifico',
            ]);
            if (!$sinFecha) $query->whereBetween('fecha', [$start, $end]);
            $arrayEntradas = $query->orderBy('fecha', 'ASC')->get();

            $dataArray = [];

            foreach ($arrayEntradas as $entrada) {
                foreach ($entrada->detalle as $det) {
                    $idMat  = $det->id_material;
                    $precio = (float) $det->precio;
                    $clave  = $idMat . '|' . number_format($precio, 4, '.', '');

                    if (!isset($dataArray[$clave])) {
                        $dataArray[$clave] = [
                            'objespec' => $det->material->objetoEspecifico->codigo ?? '—',
                            'nombre'   => $det->material->nombre ?? '',
                            'medida'   => $det->material->unidadMedida->nombre ?? '',
                            'cantidad' => 0,
                            'total'    => 0,
                            'precio'   => $precio,
                        ];
                    }
                    $dataArray[$clave]['cantidad'] += $det->cantidad_inicial;
                    $dataArray[$clave]['total']    += ($precio * $det->cantidad_inicial);
                }
            }

            usort($dataArray, function ($a, $b) {
                $cmp = strcmp($a['objespec'], $b['objespec']);
                return $cmp !== 0 ? $cmp : strcmp($a['nombre'], $b['nombre']);
            });

            $tabla .= $theadEntradas;

            $codigoActual    = null;
            $subtotalCodigo  = 0;
            $subtotalCantCod = 0;

            foreach ($dataArray as $info) {
                if ($codigoActual !== null && $info['objespec'] !== $codigoActual) {
                    $cantFmt  = number_format($subtotalCantCod, 2);
                    $montoFmt = number_format($subtotalCodigo, 4);
                    $tabla .= "
        <tr style='background:#e9ecef;'>
            <td colspan='3' style='font-weight:bold; font-size:11px; text-align:right; padding:4px 6px; border:0.8px solid #bbb;'>SUBTOTAL [{$codigoActual}]</td>
            <td style='font-weight:bold; font-size:11px; padding:4px 6px; border:0.8px solid #bbb;'>$cantFmt</td>
            <td style='background:#e9ecef; border:0.8px solid #bbb;'></td>
            <td style='font-weight:bold; font-size:11px; padding:4px 6px; border:0.8px solid #bbb;'>\$ $montoFmt</td>
        </tr>";
                    $subtotalCodigo  = 0;
                    $subtotalCantCod = 0;
                }

                $codigoActual      = $info['objespec'];
                $subtotalCodigo   += $info['total'];
                $subtotalCantCod  += $info['cantidad'];
                $granTotal        += $info['total'];
                $sumaTotalCantidad += $info['cantidad'];

                // ── Acumular en resumen ──
                if (!isset($resumenObjEsp[$info['objespec']])) {
                    $resumenObjEsp[$info['objespec']] = ['cantidad' => 0, 'total' => 0];
                }
                $resumenObjEsp[$info['objespec']]['cantidad'] += $info['cantidad'];
                $resumenObjEsp[$info['objespec']]['total']    += $info['total'];

                $precioFmt = number_format($info['precio'], 4);
                $totalFmt  = number_format($info['total'], 4);

                $tabla .= "
        <tr>
            <td style='font-size:11px; padding:4px 6px; border:0.8px solid #ccc;'>{$info['objespec']}</td>
            <td style='font-size:11px; padding:4px 6px; border:0.8px solid #ccc;'>{$info['nombre']}</td>
            <td style='font-size:11px; padding:4px 6px; border:0.8px solid #ccc;'>{$info['medida']}</td>
            <td style='font-size:11px; padding:4px 6px; border:0.8px solid #ccc;'>{$info['cantidad']}</td>
            <td style='font-size:11px; padding:4px 6px; border:0.8px solid #ccc;'>\$ $precioFmt</td>
            <td style='font-size:11px; padding:4px 6px; border:0.8px solid #ccc;'>\$ $totalFmt</td>
        </tr>";
            }

            if ($codigoActual !== null) {
                $cantFmt  = number_format($subtotalCantCod, 2);
                $montoFmt = number_format($subtotalCodigo, 4);
                $tabla .= "
        <tr style='background:#e9ecef;'>
            <td colspan='3' style='font-weight:bold; font-size:11px; text-align:right; padding:4px 6px; border:0.8px solid #bbb;'>SUBTOTAL [{$codigoActual}]</td>
            <td style='font-weight:bold; font-size:11px; padding:4px 6px; border:0.8px solid #bbb;'>$cantFmt</td>
            <td style='background:#e9ecef; border:0.8px solid #bbb;'></td>
            <td style='font-weight:bold; font-size:11px; padding:4px 6px; border:0.8px solid #bbb;'>\$ $montoFmt</td>
        </tr>";
            }

            $tabla .= "
    </tbody>
</table>";

            // ════════════════════════════════════════════════════════════════
            // TIPO 2: SEPARADO
            // ════════════════════════════════════════════════════════════════
        } else {

            $query = Entradas::with([
                'tipoEntrada',
                'tipoCompra',
                'proveedor',
                'detalle.material.unidadMedida',
                'detalle.material.objetoEspecifico',
            ]);
            if (!$sinFecha) $query->whereBetween('fecha', [$start, $end]);
            $arrayEntradas = $query->orderBy('fecha', 'ASC')->get();

            foreach ($arrayEntradas as $entrada) {
                $fechaFmt    = date('d-m-Y', strtotime($entrada->fecha));
                $tipoEntrada = $entrada->tipoEntrada->nombre   ?? '';
                $tipoCompra  = $entrada->tipoCompra->nombre    ?? '';
                $proveedor   = $entrada->proveedor->nombre     ?? '—';
                $factura     = $entrada->factura               ?? '';
                $descripcion = $entrada->descripcion           ?? '';

                $tabla .= "
<table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:2px; border:0.8px solid #ccc;'>
    <tr>
        <td style='width:13%; border:0.8px solid #ccc; padding:5px 7px; font-size:11px; font-weight:bold; background:#f5f5f5;'>Fecha</td>
        <td style='width:20%; border:0.8px solid #ccc; padding:5px 7px; font-size:11px;'>$fechaFmt</td>
        <td style='width:15%; border:0.8px solid #ccc; padding:5px 7px; font-size:11px; font-weight:bold; background:#f5f5f5;'>Tipo Entrada</td>
        <td style='width:20%; border:0.8px solid #ccc; padding:5px 7px; font-size:11px;'>$tipoEntrada</td>
        <td style='width:12%; border:0.8px solid #ccc; padding:5px 7px; font-size:11px; font-weight:bold; background:#f5f5f5;'>Tipo Compra</td>
        <td style='width:20%; border:0.8px solid #ccc; padding:5px 7px; font-size:11px;'>$tipoCompra</td>
    </tr>
    <tr>
        <td style='border:0.8px solid #ccc; padding:5px 7px; font-size:11px; font-weight:bold; background:#f5f5f5;'>Factura</td>
        <td style='border:0.8px solid #ccc; padding:5px 7px; font-size:11px;'>$factura</td>
        <td style='border:0.8px solid #ccc; padding:5px 7px; font-size:11px; font-weight:bold; background:#f5f5f5;'>Proveedor</td>
        <td style='border:0.8px solid #ccc; padding:5px 7px; font-size:11px;'>$proveedor</td>
        <td colspan='2' style='border:0.8px solid #ccc; padding:5px 7px; font-size:11px;'>$descripcion</td>
    </tr>
</table>";

                $tabla .= $theadEntradas;

                $subtotal         = 0;
                $subtotalCantidad = 0;

                foreach ($entrada->detalle as $det) {
                    $objEsp    = $det->material->objetoEspecifico->codigo ?? '—';
                    $nombreMat = $det->material->nombre ?? '';
                    $medida    = $det->material->unidadMedida->nombre ?? '';
                    $cantidad  = $det->cantidad_inicial;
                    $precio    = (float) $det->precio;
                    $total     = $cantidad * $precio;

                    $granTotal         += $total;
                    $subtotal          += $total;
                    $sumaTotalCantidad += $cantidad;
                    $subtotalCantidad  += $cantidad;

                    // ── Acumular en resumen ──
                    if (!isset($resumenObjEsp[$objEsp])) {
                        $resumenObjEsp[$objEsp] = ['cantidad' => 0, 'total' => 0];
                    }
                    $resumenObjEsp[$objEsp]['cantidad'] += $cantidad;
                    $resumenObjEsp[$objEsp]['total']    += $total;

                    $precioFmt = number_format($precio, 4);
                    $totalFmt  = number_format($total, 4);

                    $tabla .= "
        <tr>
            <td style='font-size:11px; padding:4px 6px; border:0.8px solid #ccc;'>$objEsp</td>
            <td style='font-size:11px; padding:4px 6px; border:0.8px solid #ccc;'>$nombreMat</td>
            <td style='font-size:11px; padding:4px 6px; border:0.8px solid #ccc;'>$medida</td>
            <td style='font-size:11px; padding:4px 6px; border:0.8px solid #ccc;'>$cantidad</td>
            <td style='font-size:11px; padding:4px 6px; border:0.8px solid #ccc;'>\$ $precioFmt</td>
            <td style='font-size:11px; padding:4px 6px; border:0.8px solid #ccc;'>\$ $totalFmt</td>
        </tr>";
                }

                $subtotalFmt         = number_format($subtotal, 4);
                $subtotalCantidadFmt = number_format($subtotalCantidad, 2);

                $tabla .= "
        <tr style='background:#e9ecef;'>
            <td colspan='3' style='font-weight:bold; font-size:11px; text-align:right; padding:4px 6px; border:0.8px solid #bbb;'>Subtotal cantidad:</td>
            <td style='font-weight:bold; font-size:11px; padding:4px 6px; border:0.8px solid #bbb;'>$subtotalCantidadFmt</td>
            <td style='font-weight:bold; font-size:11px; text-align:right; padding:4px 6px; border:0.8px solid #bbb;'>Subtotal:</td>
            <td style='font-weight:bold; font-size:11px; padding:4px 6px; border:0.8px solid #bbb;'>\$ $subtotalFmt</td>
        </tr>
    </tbody>
</table><br>";
            }
        }

        // ── GRAN TOTAL ────────────────────────────────────────────────────
        $granTotalFmt         = number_format($granTotal, 4);
        $sumaTotalCantidadFmt = number_format($sumaTotalCantidad, 2);

        $tabla .= "
<table width='100%' style='margin-top:10px; border-collapse:collapse;'>
    <tr>
        <td style='font-weight:bold; font-size:13px; text-align:right; border-top:2px solid #000; padding-top:6px;'>TOTAL GENERAL:&nbsp;&nbsp;</td>
        <td style='font-weight:bold; font-size:13px; width:18%; border-top:2px solid #000; padding-top:6px;'>\$ $granTotalFmt</td>
    </tr>
</table>";

        // ════════════════════════════════════════════════════════════════
        // CUADRO RESUMEN POR OBJETO ESPECÍFICO
        // ════════════════════════════════════════════════════════════════
        ksort($resumenObjEsp);

        $tabla .= "
<div style='height:18px; line-height:18px; font-size:1px;'>&nbsp;</div>
<table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif;'>
    <thead>
        <tr style='background:#6c757d;'>
            <td colspan='2' style='color:#fff; font-weight:bold; font-size:12px; padding:6px 8px; border:0.8px solid #888; text-align:center; letter-spacing:0.5px;'>
                RESUMEN POR CODIGO PRESUPUESTARIO
            </td>
        </tr>
        <tr style='background:#6c757d;'>
            <td style='color:#fff; font-weight:bold; font-size:11px; padding:5px 8px; border:0.8px solid #888; width:40%;'>Cod. Presu.</td>
            <td style='color:#fff; font-weight:bold; font-size:11px; padding:5px 8px; border:0.8px solid #888; width:60%; text-align:right;'>Monto Total (\$)</td>
        </tr>
    </thead>
    <tbody>";

        $filaIndex = 0;
        foreach ($resumenObjEsp as $codigo => $datos) {
            $bgFila   = ($filaIndex % 2 === 0) ? '#ffffff' : '#f0f0f0';
            $montoFmt = number_format($datos['total'], 4);

            $tabla .= "
        <tr style='background:{$bgFila};'>
            <td style='font-size:11px; font-weight:bold; padding:5px 8px; border:0.8px solid #ccc;'>{$codigo}</td>
            <td style='font-size:11px; padding:5px 8px; border:0.8px solid #ccc; text-align:right;'>\$ {$montoFmt}</td>
        </tr>";
            $filaIndex++;
        }

        $tabla .= "
        <tr style='background:#e9ecef;'>
            <td style='font-weight:bold; font-size:11px; padding:5px 8px; border:0.8px solid #bbb;'>TOTAL</td>
            <td style='font-weight:bold; font-size:11px; padding:5px 8px; border:0.8px solid #bbb; text-align:right;'>\$ {$granTotalFmt}</td>
        </tr>
    </tbody>
</table>";

        $mpdf = new \Mpdf\Mpdf([
            'tempDir'       => sys_get_temp_dir(),
            'format'        => 'LETTER',
            'margin_top'    => 15,
            'margin_bottom' => 15,
            'margin_left'   => 15,
            'margin_right'  => 15,
        ]);
        $mpdf->SetTitle('Reporte de Materiales Recibidos');
        $mpdf->showImageErrors = false;
        $stylesheet = file_get_contents('css/cssregistro.css');
        $mpdf->WriteHTML($stylesheet, 1);
        $mpdf->setFooter('Página: {PAGENO}/{nb}');
        $mpdf->WriteHTML($tabla, 2);
        $mpdf->Output('entradas_' . date('Ymd_His') . '.pdf', 'I');
    }




    public function pdfQueHaSalidoProyectos($desde, $hasta, $tipo = 2)
    {
        $fechaHoy     = Carbon::now('America/El_Salvador')->format('d-m-Y');
        $logoalcaldia = 'images/logo.png';
        $sinFecha     = ($desde === 'null' || $desde === '' || $hasta === 'null' || $hasta === '');

        if (!$sinFecha) {
            $start      = date('Y-m-d 00:00:00', strtotime($desde));
            $end        = date('Y-m-d 23:59:59', strtotime($hasta));
            $fechaLabel = date('d-m-Y', strtotime($desde)) . '  —  ' . date('d-m-Y', strtotime($hasta));
        } else {
            $fechaLabel = 'Todas las fechas';
        }

        $encabezado = "
<table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif;'>
    <tr>
        <td style='width:25%; border:0.8px solid #000; padding:6px 8px;'>
            <table width='100%'><tr>
                <td style='width:30%; text-align:left;'><img src='{$logoalcaldia}' style='height:38px'></td>
                <td style='width:70%; text-align:left; color:#104e8c; font-size:13px; font-weight:bold; line-height:1.3;'>SANTA ANA NORTE<br>EL SALVADOR</td>
            </tr></table>
        </td>
        <td style='width:50%; border-top:0.8px solid #000; border-bottom:0.8px solid #000; padding:6px 8px; text-align:center; font-size:15px; font-weight:bold;'>
            REPORTE DE MATERIALES ENTREGADOS
        </td>
        <td style='width:25%; border:0.8px solid #000; padding:0; vertical-align:top;'>
            <table width='100%' style='font-size:10px;'>
                <tr>
                    <td width='40%' style='border-right:0.8px solid #000; border-bottom:0.8px solid #000; padding:4px 6px;'><strong>Código:</strong></td>
                    <td width='60%' style='border-bottom:0.8px solid #000; padding:4px 6px; text-align:center;'></td>
                </tr>
                <tr>
                    <td style='border-right:0.8px solid #000; border-bottom:0.8px solid #000; padding:4px 6px;'><strong>Versión:</strong></td>
                    <td style='border-bottom:0.8px solid #000; padding:4px 6px; text-align:center;'>000</td>
                </tr>
                <tr>
                    <td style='border-right:0.8px solid #000; padding:4px 6px;'><strong>Fecha de vigencia:</strong></td>
                    <td style='padding:4px 6px; text-align:center;'></td>
                </tr>
            </table>
        </td>
    </tr>
</table><br>
<table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:8px;'>
    <tr>
        <td style='width:15%; border:0.8px solid #ccc; padding:6px 8px; font-size:11px; font-weight:bold; background:#f5f5f5;'>PERIODO</td>
        <td style='width:50%; border:0.8px solid #ccc; padding:6px 8px; font-size:11px;'>$fechaLabel</td>
        <td style='width:15%; border:0.8px solid #ccc; padding:6px 8px; font-size:11px; font-weight:bold; background:#f5f5f5; text-align:center;'>FECHA</td>
        <td style='width:20%; border:0.8px solid #ccc; padding:6px 8px; font-size:11px; text-align:center;'>$fechaHoy</td>
    </tr>
</table>";

        $granTotal         = 0;
        $sumaTotalCantidad = 0;
        $tabla             = $encabezado;

        // ── Acumulador para el resumen de Objetos Específicos ─────────────────
        $resumenObjEsp = []; // ['codigo' => ['cantidad' => x, 'total' => y]]

        // ── CABECERA COLUMNAS ─────────────────────────────────────────────
        $theadSalidas = "
<table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:8px; border:0.8px solid #aaa;'>
    <thead>
        <tr style='background:#6c757d;'>
            <td style='font-weight:bold; width:11%; font-size:11px; color:#fff; padding:5px 6px; border:0.8px solid #888;'>Cod. Presu.</td>
            <td style='font-weight:bold; width:32%; font-size:11px; color:#fff; padding:5px 6px; border:0.8px solid #888;'>Material</td>
            <td style='font-weight:bold; width:12%; font-size:11px; color:#fff; padding:5px 6px; border:0.8px solid #888;'>Medida</td>
            <td style='font-weight:bold; width:10%; font-size:11px; color:#fff; padding:5px 6px; border:0.8px solid #888;'>Cantidad</td>
            <td style='font-weight:bold; width:14%; font-size:11px; color:#fff; padding:5px 6px; border:0.8px solid #888;'>Precio Unit.</td>
            <td style='font-weight:bold; width:14%; font-size:11px; color:#fff; padding:5px 6px; border:0.8px solid #888;'>Total (\$)</td>
        </tr>
    </thead>
    <tbody>";

        // ════════════════════════════════════════════════════════════════
        // TIPO 1: JUNTOS
        // ════════════════════════════════════════════════════════════════
        if ($tipo == 1) {

            $query = Salidas::with([
                'detalle.entradaDetalle.material.unidadMedida',
                'detalle.entradaDetalle.material.objetoEspecifico',
            ]);
            if (!$sinFecha) $query->whereBetween('fecha', [$start, $end]);
            $arraySalidas = $query->orderBy('fecha', 'ASC')->get();

            $dataArray = [];

            foreach ($arraySalidas as $salida) {
                foreach ($salida->detalle as $det) {
                    $entDet = $det->entradaDetalle;
                    if (!$entDet || !$entDet->material) continue;

                    $idMat  = $entDet->id_material;
                    $precio = (float) ($entDet->precio ?? 0);
                    $clave  = $idMat . '|' . number_format($precio, 4, '.', '');

                    if (!isset($dataArray[$clave])) {
                        $dataArray[$clave] = [
                            'objespec' => $entDet->material->objetoEspecifico->codigo ?? '—',
                            'nombre'   => $entDet->material->nombre ?? '',
                            'medida'   => $entDet->material->unidadMedida->nombre ?? '',
                            'cantidad' => 0,
                            'total'    => 0,
                            'precio'   => $precio,
                        ];
                    }
                    $dataArray[$clave]['cantidad'] += $det->cantidad_salida;
                    $dataArray[$clave]['total']    += ($precio * $det->cantidad_salida);
                }
            }

            usort($dataArray, function ($a, $b) {
                $cmp = strcmp($a['objespec'], $b['objespec']);
                return $cmp !== 0 ? $cmp : strcmp($a['nombre'], $b['nombre']);
            });

            $tabla .= $theadSalidas;

            $codigoActual    = null;
            $subtotalCodigo  = 0;
            $subtotalCantCod = 0;

            foreach ($dataArray as $info) {
                if ($codigoActual !== null && $info['objespec'] !== $codigoActual) {
                    $cantFmt  = number_format($subtotalCantCod, 2);
                    $montoFmt = number_format($subtotalCodigo, 4);
                    $tabla .= "
        <tr style='background:#e9ecef;'>
            <td colspan='3' style='font-weight:bold; font-size:11px; text-align:right; padding:4px 6px; border:0.8px solid #bbb;'>SUBTOTAL [{$codigoActual}]</td>
            <td style='font-weight:bold; font-size:11px; padding:4px 6px; border:0.8px solid #bbb;'>$cantFmt</td>
            <td style='background:#e9ecef; border:0.8px solid #bbb;'></td>
            <td style='font-weight:bold; font-size:11px; padding:4px 6px; border:0.8px solid #bbb;'>\$ $montoFmt</td>
        </tr>";
                    $subtotalCodigo  = 0;
                    $subtotalCantCod = 0;
                }

                $codigoActual       = $info['objespec'];
                $subtotalCodigo    += $info['total'];
                $subtotalCantCod   += $info['cantidad'];
                $granTotal         += $info['total'];
                $sumaTotalCantidad += $info['cantidad'];

                // ── Acumular en resumen ──
                if (!isset($resumenObjEsp[$info['objespec']])) {
                    $resumenObjEsp[$info['objespec']] = ['cantidad' => 0, 'total' => 0];
                }
                $resumenObjEsp[$info['objespec']]['cantidad'] += $info['cantidad'];
                $resumenObjEsp[$info['objespec']]['total']    += $info['total'];

                $precioFmt = number_format($info['precio'], 4);
                $totalFmt  = number_format($info['total'], 4);

                $tabla .= "
        <tr>
            <td style='font-size:11px; padding:4px 6px; border:0.8px solid #ccc;'>{$info['objespec']}</td>
            <td style='font-size:11px; padding:4px 6px; border:0.8px solid #ccc;'>{$info['nombre']}</td>
            <td style='font-size:11px; padding:4px 6px; border:0.8px solid #ccc;'>{$info['medida']}</td>
            <td style='font-size:11px; padding:4px 6px; border:0.8px solid #ccc;'>{$info['cantidad']}</td>
            <td style='font-size:11px; padding:4px 6px; border:0.8px solid #ccc;'>\$ $precioFmt</td>
            <td style='font-size:11px; padding:4px 6px; border:0.8px solid #ccc;'>\$ $totalFmt</td>
        </tr>";
            }

            // Último subtotal
            if ($codigoActual !== null) {
                $cantFmt  = number_format($subtotalCantCod, 2);
                $montoFmt = number_format($subtotalCodigo, 4);
                $tabla .= "
        <tr style='background:#e9ecef;'>
            <td colspan='3' style='font-weight:bold; font-size:11px; text-align:right; padding:4px 6px; border:0.8px solid #bbb;'>SUBTOTAL [{$codigoActual}]</td>
            <td style='font-weight:bold; font-size:11px; padding:4px 6px; border:0.8px solid #bbb;'>$cantFmt</td>
            <td style='background:#e9ecef; border:0.8px solid #bbb;'></td>
            <td style='font-weight:bold; font-size:11px; padding:4px 6px; border:0.8px solid #bbb;'>\$ $montoFmt</td>
        </tr>";
            }

            $tabla .= "
    </tbody>
</table>";

            // ════════════════════════════════════════════════════════════════
            // TIPO 2: SEPARADO
            // ════════════════════════════════════════════════════════════════
        } else {

            $query = Salidas::with([
                'equipo',
                'detalle.entradaDetalle.material.unidadMedida',
                'detalle.entradaDetalle.material.objetoEspecifico',
            ]);
            if (!$sinFecha) $query->whereBetween('fecha', [$start, $end]);
            $arraySalidas = $query->orderBy('fecha', 'ASC')->get();

            foreach ($arraySalidas as $salida) {
                $fechaFmt    = date('d-m-Y', strtotime($salida->fecha));
                $descripcion = $salida->descripcion ?? '';
                $fichaNombre = $salida->ficha_nombre    ?? '';
                $fichaTalon  = $salida->ficha_talonario ?? '';
                $equipo      = $salida->equipo->nombre  ?? '';

                $tabla .= "
<table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:2px; border:0.8px solid #ccc;'>

    <tr>
        <td style='width:15%; border:0.8px solid #ccc; padding:5px 7px; font-size:11px; font-weight:bold; background:#f5f5f5;'>
            Equipo
        </td>
        <td colspan='3' style='border:0.8px solid #ccc; padding:5px 7px; font-size:11px;'>
            $equipo
        </td>
    </tr>

    <tr>
        <td style='width:15%; border:0.8px solid #ccc; padding:5px 7px; font-size:11px; font-weight:bold; background:#f5f5f5;'>
            Ficha
        </td>
        <td style='width:35%; border:0.8px solid #ccc; padding:5px 7px; font-size:11px;'>
            $fichaNombre
        </td>

        <td style='width:15%; border:0.8px solid #ccc; padding:5px 7px; font-size:11px; font-weight:bold; background:#f5f5f5;'>
            Talonario
        </td>
        <td style='width:35%; border:0.8px solid #ccc; padding:5px 7px; font-size:11px;'>
            $fichaTalon
        </td>
    </tr>

    <tr>
        <td style='border:0.8px solid #ccc; padding:5px 7px; font-size:11px; font-weight:bold; background:#f5f5f5;'>
            Descripción
        </td>
        <td colspan='3' style='border:0.8px solid #ccc; padding:5px 7px; font-size:11px;'>
            $descripcion
        </td>
    </tr>

</table>";

                $tabla .= $theadSalidas;

                $subtotal         = 0;
                $subtotalCantidad = 0;

                foreach ($salida->detalle as $det) {
                    $entDet = $det->entradaDetalle;
                    if (!$entDet || !$entDet->material) continue;

                    $objEsp    = $entDet->material->objetoEspecifico->codigo ?? '—';
                    $nombreMat = $entDet->material->nombre ?? '';
                    $medida    = $entDet->material->unidadMedida->nombre ?? '';
                    $cantidad  = $det->cantidad_salida;
                    $precio    = (float) ($entDet->precio ?? 0);
                    $total     = $cantidad * $precio;

                    $granTotal         += $total;
                    $subtotal          += $total;
                    $sumaTotalCantidad += $cantidad;
                    $subtotalCantidad  += $cantidad;

                    // ── Acumular en resumen ──
                    if (!isset($resumenObjEsp[$objEsp])) {
                        $resumenObjEsp[$objEsp] = ['cantidad' => 0, 'total' => 0];
                    }
                    $resumenObjEsp[$objEsp]['cantidad'] += $cantidad;
                    $resumenObjEsp[$objEsp]['total']    += $total;

                    $precioFmt = number_format($precio, 4);
                    $totalFmt  = number_format($total, 4);

                    $tabla .= "
        <tr>
            <td style='font-size:11px; padding:4px 6px; border:0.8px solid #ccc;'>$objEsp</td>
            <td style='font-size:11px; padding:4px 6px; border:0.8px solid #ccc;'>$nombreMat</td>
            <td style='font-size:11px; padding:4px 6px; border:0.8px solid #ccc;'>$medida</td>
            <td style='font-size:11px; padding:4px 6px; border:0.8px solid #ccc;'>$cantidad</td>
            <td style='font-size:11px; padding:4px 6px; border:0.8px solid #ccc;'>\$ $precioFmt</td>
            <td style='font-size:11px; padding:4px 6px; border:0.8px solid #ccc;'>\$ $totalFmt</td>
        </tr>";
                }

                $subtotalFmt         = number_format($subtotal, 4);
                $subtotalCantidadFmt = number_format($subtotalCantidad, 2);

                $tabla .= "
        <tr style='background:#e9ecef;'>
            <td colspan='3' style='font-weight:bold; font-size:11px; text-align:right; padding:4px 6px; border:0.8px solid #bbb;'>Subtotal cantidad:</td>
            <td style='font-weight:bold; font-size:11px; padding:4px 6px; border:0.8px solid #bbb;'>$subtotalCantidadFmt</td>
            <td style='font-weight:bold; font-size:11px; text-align:right; padding:4px 6px; border:0.8px solid #bbb;'>Subtotal:</td>
            <td style='font-weight:bold; font-size:11px; padding:4px 6px; border:0.8px solid #bbb;'>\$ $subtotalFmt</td>
        </tr>
    </tbody>
</table><br>";
            }
        }

        // ── GRAN TOTAL ────────────────────────────────────────────────────
        $granTotalFmt         = number_format($granTotal, 4);
        $sumaTotalCantidadFmt = number_format($sumaTotalCantidad, 2);

        $tabla .= "
<table width='100%' style='margin-top:10px; border-collapse:collapse;'>
    <tr>
        <td style='font-weight:bold; font-size:13px; text-align:right; border-top:2px solid #000; padding-top:6px;'>TOTAL GENERAL:&nbsp;&nbsp;</td>
        <td style='font-weight:bold; font-size:13px; width:18%; border-top:2px solid #000; padding-top:6px;'>\$ $granTotalFmt</td>
    </tr>
</table>";

        // ════════════════════════════════════════════════════════════════
        // CUADRO RESUMEN POR OBJETO ESPECÍFICO
        // ════════════════════════════════════════════════════════════════
        ksort($resumenObjEsp); // Ordenar por código de Obj. Esp.

        $tabla .= "
<div style='height:18px; line-height:18px; font-size:1px;'>&nbsp;</div>
<table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif;'>
    <thead>
        <tr style='background:#6c757d;'>
            <td colspan='2' style='color:#fff; font-weight:bold; font-size:12px; padding:6px 8px; border:0.8px solid #888; text-align:center; letter-spacing:0.5px;'>
                RESUMEN POR CODIGO PRESUPUESTARIO
            </td>
        </tr>
        <tr style='background:#6c757d;'>
            <td style='color:#fff; font-weight:bold; font-size:11px; padding:5px 8px; border:0.8px solid #888; width:40%;'>Cod. Presu.</td>
            <td style='color:#fff; font-weight:bold; font-size:11px; padding:5px 8px; border:0.8px solid #888; width:60%; text-align:right;'>Monto Total (\$)</td>
        </tr>
    </thead>
    <tbody>";

        $filaIndex = 0;
        foreach ($resumenObjEsp as $codigo => $datos) {
            $bgFila    = ($filaIndex % 2 === 0) ? '#ffffff' : '#f0f4fa';
            $montoFmt  = number_format($datos['total'], 4);

            $tabla .= "
        <tr style='background:{$bgFila};'>
            <td style='font-size:11px; font-weight:bold; padding:5px 8px; border:0.8px solid #ccc;'>{$codigo}</td>
            <td style='font-size:11px; padding:5px 8px; border:0.8px solid #ccc; text-align:right;'>\$ {$montoFmt}</td>
        </tr>";
            $filaIndex++;
        }

        // Fila de totales del resumen
        $tabla .= "
        <tr style='background:#e9ecef;'>
            <td style='font-weight:bold; font-size:11px; padding:5px 8px; border:0.8px solid #bbb;'>TOTAL</td>
            <td style='font-weight:bold; font-size:11px; padding:5px 8px; border:0.8px solid #bbb; text-align:right;'>\$ {$granTotalFmt}</td>
        </tr>
    </tbody>
</table>";

        $mpdf = new \Mpdf\Mpdf([
            'tempDir'       => sys_get_temp_dir(),
            'format'        => 'LETTER',
            'margin_top'    => 15,
            'margin_bottom' => 15,
            'margin_left'   => 15,
            'margin_right'  => 15,
        ]);
        $mpdf->SetTitle('Reporte de Materiales Entregados');
        $mpdf->showImageErrors = false;
        $stylesheet = file_get_contents('css/cssregistro.css');
        $mpdf->WriteHTML($stylesheet, 1);
        $mpdf->setFooter('Página: {PAGENO}/{nb}');
        $mpdf->WriteHTML($tabla, 2);
        $mpdf->Output('salidas_' . date('Ymd_His') . '.pdf', 'I');
    }



}
