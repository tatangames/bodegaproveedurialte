@php
    $totalGeneral = $arraySalidas->sum('subtotal');
@endphp

<div class="table-responsive">
    <table class="table table-bordered table-striped table-hover table-sm" id="tabla-historial">
        <thead class="thead-dark">
        <tr>
            <th style="width: 8%">Fecha</th>
            <th>Material</th>
            <th>Tipo Salida</th>
            <th>Departamento</th>
            <th>N° Solicitud</th>
            <th class="text-center">Cantidad</th>
            <th class="text-right">Precio Unit.</th>
            <th class="text-right">Subtotal</th>
            <th class="text-center">Acciones</th>
        </tr>
        </thead>
        <tbody>
        @forelse($arraySalidas as $key => $salida)
            <tr>
                <td data-order="{{ \Carbon\Carbon::parse($salida->fecha)->format('Y-m-d') }}">
                    {{ \Carbon\Carbon::parse($salida->fecha)->format('d-m-Y') }}
                </td>
                <td>{{ $salida->material }}</td>
                <td>{{ $salida->tipo_salida ?? '—' }}</td>
                <td>{{ $salida->departamento ?? '—' }}</td>
                <td>{{ $salida->numero_solicitud ?? '—' }}</td>
                <td class="text-center">{{ $salida->cantidad_salida }}</td>
                <td class="text-right">
                    ${{ number_format($salida->precio ?? 0, 2) }}
                </td>
                <td class="text-right">
                    ${{ number_format($salida->subtotal ?? 0, 2) }}
                </td>
                <td class="text-center text-nowrap">
                    <button class="btn btn-xs btn-info" onclick="verDetalle({{ $salida->id }})" title="Ver detalle">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-xs btn-warning" onclick="modalEditar({{ $salida->id }})" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-xs btn-danger" onclick="eliminar({{ $salida->id }})" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="9" class="text-center text-muted py-3">
                    No se encontraron registros con los filtros aplicados.
                </td>
            </tr>
        @endforelse
        </tbody>

        @if($arraySalidas->count() > 0)
            <tfoot>
            <tr class="table-dark font-weight-bold">
                <td colspan="7" class="text-right">TOTAL GENERAL:</td>
                <td class="text-right">
                    ${{ number_format($totalGeneral, 2) }}
                </td>
                <td></td>
            </tr>
            </tfoot>
        @endif
    </table>
</div>
