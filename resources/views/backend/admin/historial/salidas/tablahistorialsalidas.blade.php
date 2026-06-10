<table id="tabla-historial" class="table table-bordered table-striped table-hover mb-0">
    <thead class="thead-dark">
    <tr>
        <th style="width:4%">ID</th>
        <th style="width:10%">Fecha</th>
        <th style="width:13%">Tipo Salida</th>
        <th style="width:13%">Departamento</th>
        <th style="width:12%">N° Solicitud</th>
        <th style="width:28%">Material</th>
        <th style="width:6%" class="text-center">Cant.</th>
        <th style="width:14%">Acciones</th>
    </tr>
    </thead>
    <tbody>
    @forelse($arraySalidas as $dato)
        <tr>
            <td>{{ $dato->id }}</td>
            <td>{{ $dato->fecha ? date('d-m-Y', strtotime($dato->fecha)) : '—' }}</td>
            <td>{{ $dato->tipo_salida ?? '—' }}</td>
            <td>{{ $dato->departamento ?? '—' }}</td>
            <td>{{ $dato->numero_solicitud ?? '—' }}</td>
            <td>{{ $dato->material }}</td>
            <td class="text-center">{{ $dato->cantidad_salida }}</td>
            <td class="text-center">
                <button type="button"
                        class="btn btn-info btn-xs"
                        style="margin:2px"
                        onclick="verDetalle({{ $dato->id }})">
                    <i class="fas fa-list"></i> Ver
                </button>
                <button type="button"
                        class="btn btn-warning btn-xs"
                        style="margin:2px"
                        onclick="modalEditar({{ $dato->id }})">
                    <i class="fas fa-edit"></i> Editar
                </button>
                <button type="button"
                        class="btn btn-danger btn-xs"
                        style="margin:2px"
                        onclick="eliminar({{ $dato->id }})">
                    <i class="fas fa-trash"></i> Borrar
                </button>
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="8" class="text-center text-muted py-4">
                No se encontraron registros con los filtros aplicados
            </td>
        </tr>
    @endforelse
    </tbody>
</table>
