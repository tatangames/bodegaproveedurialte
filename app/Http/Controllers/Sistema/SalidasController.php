<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Departamentos;
use App\Models\Entradas;
use App\Models\EntradasDetalle;
use App\Models\Equipos;
use App\Models\InformacionGeneral;
use App\Models\Materiales;
use App\Models\Reserva;
use App\Models\Salidas;
use App\Models\SalidasDetalle;
use App\Models\TipoProyecto;
use App\Models\TipoSalida;
use App\Models\Transferencia;
use App\Models\TransferenciaDetalle;
use App\Models\UnidadMedida;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SalidasController extends Controller
{

    public function indexRegistroSalida()
    {
        $arrayTipoSalida    = TipoSalida::orderBy('nombre')->get();
        $arrayDepartamentos = Departamentos::orderBy('nombre')->get();

        return view('backend.admin.repuestos.salidas.vistasalidaregistro',
            compact('arrayTipoSalida', 'arrayDepartamentos'));
    }

    public function buscadorMaterialDisponible(Request $request)
    {
        if (!$request->get('query')) return '';

        $query = $request->get('query');

        $materiales = Materiales::where('nombre', 'LIKE', "%{$query}%")->pluck('id');

        if ($materiales->isEmpty()) return '';

        $listado = DB::table('entradas_detalle as ed')
            ->leftJoin(
                DB::raw('(
                SELECT id_entrada_detalle, SUM(cantidad_salida) as total_salido
                FROM salidas_detalle
                GROUP BY id_entrada_detalle
            ) as sd'),
                'sd.id_entrada_detalle', '=', 'ed.id'
            )
            ->select(
                'ed.id_material',
                DB::raw('SUM(ed.cantidad_inicial) as total_inicial'),
                DB::raw('COALESCE(SUM(sd.total_salido), 0) as total_salido'),
                DB::raw('(SUM(ed.cantidad_inicial) - COALESCE(SUM(sd.total_salido), 0)) as disponible')
            )
            ->whereIn('ed.id_material', $materiales)
            ->groupBy('ed.id_material')
            ->havingRaw('disponible > 0')
            ->orderBy('ed.id_material')
            ->get();

        if ($listado->isEmpty()) return '';

        $output = '<ul class="dropdown-menu" style="display:block;position:relative;overflow:auto;max-height:300px;width:800px">';

        foreach ($listado as $row) {
            $infoMaterial = Materiales::with('unidadMedida')->find($row->id_material);
            if (!$infoMaterial) continue;

            $nombreCompleto = $infoMaterial->nombre .
                ' (' . optional($infoMaterial->unidadMedida)->nombre . ')';

            $output .= '
            <li class="cursor-pointer" onclick="modificarValor(this)"
                id="' . $row->id_material . '"
                data-tipo="material">
                ' . $nombreCompleto . ' — Disponible: ' . $row->disponible . '
            </li>
            <hr>';
        }

        $output .= '</ul>';
        return $output;
    }



    public function infoBodegaMaterialDetalleFila(Request $request)
    {
        $validar = Validator::make($request->all(), ['id' => 'required']);
        if ($validar->fails()) return ['success' => 0];

        $infoMaterial = Materiales::find($request->id);
        if (!$infoMaterial) return ['success' => 0];

        $infoMedida = UnidadMedida::find($infoMaterial->id_medida);

        $listado = DB::table('entradas_detalle as ed')
            ->leftJoin(
                DB::raw('(
                SELECT id_entrada_detalle, SUM(cantidad_salida) as total_salido
                FROM salidas_detalle
                GROUP BY id_entrada_detalle
            ) as sd'),
                'sd.id_entrada_detalle', '=', 'ed.id'
            )
            ->join('entradas as e', 'e.id', '=', 'ed.id_entradas')
            ->select(
                'ed.id',
                'ed.id_entradas',
                'ed.cantidad_inicial',
                'ed.precio',
                'e.fecha',
                'ed.codigo',
                DB::raw('COALESCE(sd.total_salido, 0) as total_salido'),
                DB::raw('(ed.cantidad_inicial - COALESCE(sd.total_salido, 0)) as cantidadActual')
            )
            ->where('ed.id_material', $request->id)
            ->havingRaw('cantidadActual > 0')
            ->orderBy('ed.id')
            ->get();

        foreach ($listado as $fila) {
            $fila->fechaIngreso = date('d-m-Y', strtotime($fila->fecha));
            $fila->precioFormat = '$' . number_format($fila->precio, 2, '.', ',');
        }

        return [
            'success'        => 1,
            'nombreMaterial' => $infoMaterial->nombre ?? '',
            'nombreMedida'   => $infoMedida->nombre   ?? '',
            'arrayIngreso'   => $listado,
            'disponible'     => $listado->isEmpty() ? 1 : 0,
        ];
    }


    public function guardarSalida(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha'      => 'required|date',
            'tiposalida' => 'required',
        ]);

        if ($validator->fails()) return ['success' => 0];

        $contenedor = json_decode($request->contenedorArray, true);
        if (empty($contenedor)) return ['success' => 0];

        DB::beginTransaction();

        try {

            // ── Acumular cantidades por id_entrada_detalle ────────────────
            $acumulado = [];
            foreach ($contenedor as $item) {
                $id = $item['infoIdEntradaDeta'];
                if (!isset($acumulado[$id])) $acumulado[$id] = 0;
                $acumulado[$id] += (int) $item['infoCantidad'];
            }

            // ── Validar stock acumulado antes de guardar ──────────────────
            foreach ($acumulado as $idEntradaDetalle => $cantidadSalida) {

                $cantidadInicial = DB::table('entradas_detalle')
                    ->where('id', $idEntradaDetalle)
                    ->value('cantidad_inicial');

                if (is_null($cantidadInicial)) {
                    DB::rollback();
                    return ['success' => 0];
                }

                $totalSalido = DB::table('salidas_detalle')
                    ->where('id_entrada_detalle', $idEntradaDetalle)
                    ->sum('cantidad_salida');

                $disponible = (int) $cantidadInicial - (int) $totalSalido;

                if ($cantidadSalida > $disponible) {
                    DB::rollback();

                    $nombreMaterial = DB::table('entradas_detalle as ed')
                        ->join('materiales as m', 'm.id', '=', 'ed.id_material')
                        ->where('ed.id', $idEntradaDetalle)
                        ->value('m.nombre');

                    return [
                        'success'         => 2,
                        'nombre_material' => $nombreMaterial ?? 'Material desconocido',
                        'cantidad_pedida' => $cantidadSalida,
                        'disponible'      => $disponible,
                    ];
                }
            }

            // ── Guardar cabecera ──────────────────────────────────────────
            $salida                   = new Salidas();
            $salida->id_tiposalida    = $request->tiposalida;
            $salida->fecha            = $request->fecha;
            $salida->descripcion      = $request->descripcion      ?: null;
            $salida->numero_solicitud = $request->numero_solicitud ?: null;
            $salida->save();

            // ── Guardar cada ítem con su departamento y estado ────────────
            foreach ($contenedor as $item) {
                $detalle                     = new SalidasDetalle();
                $detalle->id_salida          = $salida->id;
                $detalle->id_entrada_detalle = (int) $item['infoIdEntradaDeta'];
                $detalle->cantidad_salida    = (int) $item['infoCantidad'];
                $detalle->estado             = in_array($item['infoEstado'], ['pendiente', 'finalizado'])
                    ? $item['infoEstado']
                    : 'pendiente';
                $detalle->save();

                DB::table('salidas_detalle_entregas')->insert([
                    'id_salida_detalle' => $detalle->id,
                    'id_departamento'   => !empty($item['infoDepartamento']) ? (int) $item['infoDepartamento'] : null,
                    'cantidad'          => (int) $item['infoCantidad'],
                    'fecha_entrega'     => $request->fecha,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }

            DB::commit();
            return ['success' => 10];

        } catch (\Throwable $e) {
            DB::rollback();
            Log::error('guardarSalida: ' . $e);
            return ['success' => 99];
        }
    }




    public function indexPendienteEntrega()
    {
        $pendientes = DB::table('salidas_detalle as sd')
            ->join('salidas as s', 's.id', '=', 'sd.id_salida')
            ->join('entradas_detalle as ed', 'ed.id', '=', 'sd.id_entrada_detalle')
            ->join('materiales as m', 'm.id', '=', 'ed.id_material')
            ->select(
                'sd.id as id_salida_detalle',
                's.fecha',
                's.numero_solicitud',
                'm.nombre as material',
                'sd.cantidad_salida'
            )
            ->where('sd.estado', 'pendiente')
            ->orderBy('s.fecha', 'asc')
            ->orderBy('sd.id', 'asc')
            ->get();

        $arrayDepartamentos = Departamentos::orderBy('nombre')->get();


        DB::table('salidas_detalle')
            ->join('salidas', 'salidas_detalle.id_salida', '=', 'salidas.id')
            ->update([
                'salidas_detalle.fecha'            => DB::raw('salidas.fecha'),
                'salidas_detalle.descripcion'      => DB::raw('salidas.descripcion'),
                'salidas_detalle.numero_solicitud' => DB::raw('salidas.numero_solicitud'),
            ]);


        return view('backend.admin.repuestos.pendiente.vistapendiente',
            compact('pendientes', 'arrayDepartamentos'));
    }

    // Agregar nueva entrega a un ítem pendiente
    public function registrarSalidaParcial(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_salida_detalle' => 'required|exists:salidas_detalle,id',
            'id_departamento'   => 'nullable|exists:departamentos,id',
            'cantidad'          => 'required|integer|min:1',
            'observacion'       => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) return ['success' => 0];

        try {
            DB::table('salidas_detalle_entregas')->insert([
                'id_salida_detalle' => $request->id_salida_detalle,
                'id_departamento'   => $request->id_departamento ?: null,
                'cantidad'          => $request->cantidad,
                'fecha_entrega'     => now()->toDateString(),
                'observacion'       => $request->observacion ?: null,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            return ['success' => 10];

        } catch (\Throwable $e) {
            Log::error('registrarSalidaParcial: ' . $e);
            return ['success' => 99];
        }
    }

// Marcar ítem como finalizado manualmente
    public function finalizarDetalle(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_salida_detalle' => 'required|exists:salidas_detalle,id',
        ]);

        if ($validator->fails()) return ['success' => 0];

        try {
            DB::table('salidas_detalle')
                ->where('id', $request->id_salida_detalle)
                ->update(['estado' => 'finalizado']);

            return ['success' => 10];

        } catch (\Throwable $e) {
            Log::error('finalizarDetalle: ' . $e);
            return ['success' => 99];
        }
    }


    public function detalleEntregas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_salida_detalle' => 'required|exists:salidas_detalle,id',
        ]);

        if ($validator->fails()) return ['success' => 0];

        $entregas = DB::table('salidas_detalle_entregas as sde')
            ->leftJoin('departamentos as dep', 'dep.id', '=', 'sde.id_departamento')
            ->select(
                'sde.id',
                'sde.cantidad',
                'sde.fecha_entrega',
                'sde.observacion',
                'dep.nombre as departamento'
            )
            ->where('sde.id_salida_detalle', $request->id_salida_detalle)
            ->orderBy('sde.created_at', 'asc')
            ->get();

        return ['success' => 10, 'entregas' => $entregas];
    }


    public function editarEntrega(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:salidas_detalle_entregas,id',
        ]);

        if ($validator->fails()) return ['success' => 0];

        $entrega = DB::table('salidas_detalle_entregas')
            ->where('id', $request->id)
            ->first();

        return ['success' => 10, 'entrega' => $entrega];
    }

    public function actualizarEntrega(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'              => 'required|exists:salidas_detalle_entregas,id',
            'id_departamento' => 'nullable|exists:departamentos,id',
            'cantidad'        => 'required|integer|min:1',
            'fecha_entrega'   => 'required|date',
            'observacion'     => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) return ['success' => 0];

        try {
            DB::table('salidas_detalle_entregas')
                ->where('id', $request->id)
                ->update([
                    'id_departamento' => $request->id_departamento ?: null,
                    'cantidad'        => $request->cantidad,
                    'fecha_entrega'   => $request->fecha_entrega,
                    'observacion'     => $request->observacion ?: null,
                    'updated_at'      => now(),
                ]);

            return ['success' => 10];

        } catch (\Throwable $e) {
            Log::error('actualizarEntrega: ' . $e);
            return ['success' => 99];
        }
    }

    public function eliminarEntrega(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:salidas_detalle_entregas,id',
        ]);

        if ($validator->fails()) return ['success' => 0];

        try {
            DB::table('salidas_detalle_entregas')->where('id', $request->id)->delete();
            return ['success' => 10];
        } catch (\Throwable $e) {
            Log::error('eliminarEntrega: ' . $e);
            return ['success' => 99];
        }
    }



}
