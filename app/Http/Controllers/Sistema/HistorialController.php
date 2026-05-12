<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Entradas;
use App\Models\EntradasDetalle;
use App\Models\Materiales;
use App\Models\Salidas;
use App\Models\SalidasDetalle;
use App\Models\TipoProyecto;
use App\Models\Transferencia;
use App\Models\TransferenciaDetalle;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HistorialController extends Controller
{

    public function indexHistorialEntradas()
    {
        return view('backend.admin.historial.entradas.vistahistorialentradas');
    }

    public function tablaHistorialEntradas()
    {
        $arrayEntradas = Entradas::with([
            'tipoproyecto',
            'tipoproyectoTransferencia'
        ])
            ->orderBy('fecha', 'desc')
            ->get()
            ->map(function ($item) {
                // Formatear fecha con AM/PM
                $item->fecha_fmt = date('d/m/Y h:i A', strtotime($item->fecha));
                return $item;
            });

        return view('backend.admin.historial.entradas.tablahistorialentradas',
            compact('arrayEntradas'));
    }


    public function informacionEntrada(Request $request)
    {
        $entrada = Entradas::find($request->id);

        if (!$entrada) {
            return response()->json(['success' => 0]);
        }

        return response()->json([
            'success' => 1,
            'entrada' => [
                'id'          => $entrada->id,
                'fecha'       => $entrada->fecha,   // YYYY-MM-DD directo para el input type="date"
                'factura'     => $entrada->factura,
                'descripcion' => $entrada->descripcion,
            ]
        ]);
    }

    public function editarEntrada(Request $request)
    {
        $entrada = Entradas::find($request->id);

        if (!$entrada) {
            return response()->json(['success' => 0]);
        }

        $entrada->fecha       = $request->fecha;
        $entrada->factura     = $request->factura     ?: null;
        $entrada->descripcion = $request->descripcion ?: null;
        $entrada->save();

        return response()->json(['success' => 1]);
    }


    public function eliminarEntrada(Request $request)
    {
        $entrada = Entradas::find($request->id);

        if (!$entrada) {
            return response()->json(['success' => 0]);
        }

        $idsDetalle = $entrada->detalle()->pluck('id');

        if ($idsDetalle->isNotEmpty()) {

            // 1. IDs de transferencias afectadas
            $idsTransferencia = TransferenciaDetalle::whereIn('id_entrada_detalle', $idsDetalle)
                ->pluck('id_transferencia')
                ->unique();

            // 2. Borrar transferencia_detalle
            TransferenciaDetalle::whereIn('id_entrada_detalle', $idsDetalle)->delete();

            // 3. Borrar transferencias huérfanas
            if ($idsTransferencia->isNotEmpty()) {
                Transferencia::whereIn('id', $idsTransferencia)->delete();
            }

            // 4. IDs de salidas afectadas ANTES de borrar sus detalles
            $idsSalidas = SalidasDetalle::whereIn('id_entrada_detalle', $idsDetalle)
                ->pluck('id_salida')
                ->unique();

            // 5. Borrar salidas_detalle que apuntan a estos entradas_detalle
            SalidasDetalle::whereIn('id_entrada_detalle', $idsDetalle)->delete();

            // 6. Borrar salidas que quedaron sin ningún detalle
            if ($idsSalidas->isNotEmpty()) {
                $salidasHuerfanas = Salidas::whereIn('id', $idsSalidas)
                    ->whereDoesntHave('detalle')
                    ->pluck('id');

                if ($salidasHuerfanas->isNotEmpty()) {
                    Salidas::whereIn('id', $salidasHuerfanas)->delete();
                }
            }

            // 7. Borrar entradas_detalle
            $entrada->detalle()->delete();
        }

        // 8. Borrar la entrada
        $entrada->delete();

        return response()->json(['success' => 1]);
    }

    public function detalleEntrada(Request $request)
    {
        $entrada = Entradas::find($request->id);

        if (!$entrada) {
            return response()->json(['success' => 0]);
        }

        $detalle = $entrada->detalle()
            ->with('material')
            ->get()
            ->map(function ($item) {
                return [
                    'id'             => $item->id,
                    'codigo'         => $item->codigo ?? '—',
                    'material'       => $item->material->nombre ?? '—',
                    'cantidad_inicial'=> $item->cantidad_inicial,
                    'precio'         => number_format($item->precio, 4),
                    'precio_raw'     => $item->precio,  // sin formato para el input
                ];
            });

        return response()->json([
            'success' => 1,
            'detalle' => $detalle,
        ]);
    }

    public function editarDetalleEntrada(Request $request)
    {
        $detalle = \App\Models\EntradasDetalle::find($request->id);

        if (!$detalle) {
            return response()->json(['success' => 0]);
        }

        $detalle->codigo = $request->codigo ?: null;
        $detalle->precio = $request->precio;
        $detalle->save();

        return response()->json(['success' => 1]);
    }



    //***** ========================================================================================= **********


    public function indexHistorialSalidas()
    {
        return view('backend.admin.historial.salidas.vistahistorialsalidas');
    }

    public function tablaHistorialSalidas()
    {
        $arraySalidas = Salidas::with('tipoproyecto')
            ->orderBy('fecha', 'desc')
            ->get()
            ->map(function ($item) {
                $item->fecha_fmt = date('d/m/Y h:i A', strtotime($item->fecha));
                return $item;
            });

        return view('backend.admin.historial.salidas.tablahistorialsalidas',
            compact('arraySalidas'));
    }


    public function informacionSalida(Request $request)
    {
        $salida = Salidas::find($request->id);

        if (!$salida) {
            return response()->json(['success' => 0]);
        }

        return response()->json([
            'success' => 1,
            'salida'  => [
                'id'          => $salida->id,
                'fecha'       => $salida->fecha,
                'descripcion' => $salida->descripcion,
            ]
        ]);
    }

    public function editarSalida(Request $request)
    {
        $salida = Salidas::find($request->id);

        if (!$salida) {
            return response()->json(['success' => 0]);
        }

        $salida->fecha       = $request->fecha;
        $salida->descripcion = $request->descripcion ?: null;
        $salida->save();

        return response()->json(['success' => 1]);
    }

    public function eliminarSalida(Request $request)
    {
        $salida = Salidas::find($request->id);

        if (!$salida) {
            return response()->json(['success' => 0]);
        }

        // salidas_detalle apunta a salidas, hay que borrarla primero
        $salida->detalle()->delete();
        $salida->delete();

        return response()->json(['success' => 1]);
    }

    public function detalleSalida(Request $request)
    {
        $salida = Salidas::find($request->id);

        if (!$salida) {
            return response()->json(['success' => 0]);
        }

        $detalle = $salida->detalle()
            ->with('entradaDetalle.material')
            ->get()
            ->map(function ($item) {
                return [
                    'codigo'         => $item->entradaDetalle->id_material ?? '—',
                    'material'       => $item->entradaDetalle->material->nombre ?? '—',
                    'cantidad_salida'=> $item->cantidad_salida,
                    'precio'         => number_format($item->entradaDetalle->precio, 4),
                ];
            });

        return response()->json([
            'success' => 1,
            'detalle' => $detalle,
        ]);
    }



    /*public function indexHistorialRepuestosSalida(){

        return view('backend.admin.historial.salidarepuesto.vistasalidarepuesto');
    }


    public function tablaHistorialRepuestosSalida()
    {
        $lista = Salidas::with('tipoproyecto')
            ->orderBy('fecha', 'DESC')
            ->get()
            ->map(function ($dato) {
                $dato->fechaFormato = Carbon::parse($dato->fecha)->format('d-m-Y');
                $dato->nomproy      = optional($dato->tipoproyecto)->nombre ?? '-';
                return $dato;
            });

        return view('backend.admin.historial.salidarepuesto.tablasalidarepuesto', compact('lista'));
    }

    public function detalleHistorialSalida($id)
    {
        $salida = Salidas::with('tipoproyecto')->findOrFail($id);

        return view('backend.admin.historial.salidarepuesto.detalle', compact('salida'));
    }

    public function tablaDetalleHistorialSalida($id)
    {
        $lista = DB::table('salidas_detalle as sd')
            ->join('entradas_detalle as ed', 'ed.id', '=', 'sd.id_entrada_detalle')
            ->join('materiales as m', 'm.id', '=', 'ed.id_material')
            ->leftJoin('unidadmedida as um', 'um.id', '=', 'm.id_medida')
            ->where('sd.id_salida', $id)
            ->select(
                'm.nombre as nommaterial',
                'um.nombre as medida',
                'sd.cantidad_salida',
                'ed.precio'
            )
            ->get()
            ->map(function ($fila) {
                $fila->precioFormat = '$' . number_format($fila->precio, 2, '.', ',');
                return $fila;
            });

        return view('backend.admin.historial.salidarepuesto.tabladetalle', compact('lista'));
    }
*/




}
