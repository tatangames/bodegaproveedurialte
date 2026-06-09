@extends('adminlte::page')

@section('title', 'Distribución Pendiente')

@section('content_header')
    <h1>Ítems Pendientes de Distribución</h1>
@stop

@section('plugins.Sweetalert2', true)

@include('backend.urlglobal')

@section('content_top_nav_right')
    <link href="{{ asset('css/toastr.min.css') }}" type="text/css" rel="stylesheet"/>
    <link href="{{ asset('css/select2.min.css') }}" type="text/css" rel="stylesheet">
    <link href="{{ asset('css/select2-bootstrap-5-theme.min.css') }}" type="text/css" rel="stylesheet">

    <li class="nav-item dropdown">
        <a href="#" class="nav-link" data-toggle="dropdown">
            <i class="fas fa-cogs"></i>
            <span class="d-none d-md-inline">{{ Auth::guard('admin')->user()->nombre }}</span>
        </a>
        <div class="dropdown-menu dropdown-menu-right">
            <a href="{{ route('admin.perfil') }}" class="dropdown-item">
                <i class="fas fa-user mr-2"></i> Editar Perfil
            </a>
        </div>
    </li>
    <li class="nav-item">
        <form action="{{ route('admin.logout') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="nav-link btn btn-link border-0 bg-transparent">
                <i class="fas fa-sign-out-alt"></i>
                <span class="d-none d-md-inline">Cerrar Sesión</span>
            </button>
        </form>
    </li>
@endsection

@section('content')

    {{-- ══ Modal Agregar Entrega ══ --}}
    <div class="modal fade" id="modalEntrega">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background:#1a3a6b">
                    <h4 class="modal-title text-white">
                        <i class="fas fa-plus-circle mr-2"></i>Agregar Entrega
                    </h4>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    <input type="hidden" id="modal-id-salida-detalle">

                    <div class="form-group">
                        <label>Material</label>
                        <input type="text" class="form-control" id="modal-material" disabled>
                    </div>

                    <div class="form-row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Departamento: <small class="text-muted">(Opcional)</small></label>
                                <select class="form-control" id="modal-departamento" style="width:100%">
                                    <option value="">Sin departamento</option>
                                    @foreach($arrayDepartamentos as $dep)
                                        <option value="{{ $dep->id }}">{{ $dep->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Cantidad: <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="modal-cantidad"
                                       min="1" placeholder="0"
                                       onkeydown="return validateInput(event);">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Fecha Entrega: <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="modal-fecha">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Observación: <small class="text-muted">(Opcional)</small></label>
                        <textarea class="form-control" id="modal-observacion" rows="2"
                                  maxlength="500" placeholder="Ej: Recibido por Juan Pérez..."></textarea>
                    </div>

                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarEntrega()">
                        <i class="fas fa-save mr-1"></i> Guardar Entrega
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Modal Detalle Entregas ══ --}}
    <div class="modal fade" id="modalDetalle">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background:#495057">
                    <h4 class="modal-title text-white">
                        <i class="fas fa-list mr-2"></i>Detalle de Entregas
                    </h4>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Material</label>
                        <input type="text" class="form-control" id="detalle-material" disabled>
                    </div>
                    <div class="table-responsive mt-2">
                        <table class="table table-bordered table-sm" id="tabla-detalle-entregas">
                            <thead class="thead-dark">
                            <tr>
                                <th style="width:4%">#</th>
                                <th style="width:15%">Fecha Entrega</th>
                                <th style="width:22%">Departamento</th>
                                <th style="width:8%">Cantidad</th>
                                <th style="width:33%">Observación</th>
                                <th style="width:18%">Acciones</th>
                            </tr>
                            </thead>
                            <tbody></tbody>
                            <tfoot>
                            <tr class="table-secondary">
                                <td colspan="3" class="text-right font-weight-bold">Total entregado:</td>
                                <td class="text-center font-weight-bold" id="detalle-total">0</td>
                                <td colspan="2"></td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Modal Editar Entrega ══ --}}
    <div class="modal fade" id="modalEditar">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background:#856404">
                    <h4 class="modal-title text-white">
                        <i class="fas fa-edit mr-2"></i>Editar Entrega
                    </h4>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    <input type="hidden" id="editar-id-entrega">

                    <div class="form-row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Departamento: <small class="text-muted">(Opcional)</small></label>
                                <select class="form-control" id="editar-departamento" style="width:100%">
                                    <option value="">Sin departamento</option>
                                    @foreach($arrayDepartamentos as $dep)
                                        <option value="{{ $dep->id }}">{{ $dep->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Cantidad: <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="editar-cantidad"
                                       min="1" onkeydown="return validateInput(event);">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Fecha Entrega: <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="editar-fecha">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Observación: <small class="text-muted">(Opcional)</small></label>
                        <textarea class="form-control" id="editar-observacion" rows="2" maxlength="500"></textarea>
                    </div>

                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-warning" onclick="guardarEdicion()">
                        <i class="fas fa-save mr-1"></i> Guardar Cambios
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Tabla Pendientes ══ --}}
    <section class="content">
        <div class="container-fluid">
            <div class="card card-gray-dark">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-clock mr-2 text-warning"></i>
                        Pendientes de distribución
                    </h3>
                    <div class="card-tools">
                        <span class="badge badge-warning" id="badge-total">
                            {{ $pendientes->count() }} pendientes
                        </span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0" id="tabla-pendientes">
                            <thead class="thead-dark">
                            <tr>
                                <th style="width:4%">#</th>
                                <th style="width:10%">Fecha</th>
                                <th style="width:13%">N° Solicitud</th>
                                <th style="width:38%">Material</th>
                                <th style="width:8%">Cantidad</th>
                                <th style="width:27%">Acciones</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($pendientes as $index => $item)
                                <tr id="fila-{{ $item->id_salida_detalle }}">
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ date('d-m-Y', strtotime($item->fecha)) }}</td>
                                    <td>{{ $item->numero_solicitud ?? '—' }}</td>
                                    <td>{{ $item->material }}</td>
                                    <td class="text-center">{{ $item->cantidad_salida }}</td>
                                    <td>
                                        <div class="btn-group btn-group-sm w-100">
                                            <button type="button"
                                                    class="btn btn-info"
                                                    onclick="verDetalle(
                                                        {{ $item->id_salida_detalle }},
                                                        '{{ addslashes($item->material) }}'
                                                    )">
                                                <i class="fas fa-list mr-1"></i> Detalle
                                            </button>
                                            <button type="button"
                                                    class="btn btn-primary"
                                                    onclick="abrirModalEntrega(
                                                        {{ $item->id_salida_detalle }},
                                                        '{{ addslashes($item->material) }}'
                                                    )">
                                                <i class="fas fa-plus mr-1"></i> Agregar
                                            </button>
                                            <button type="button"
                                                    class="btn btn-success"
                                                    onclick="marcarFinalizado({{ $item->id_salida_detalle }})">
                                                <i class="fas fa-check mr-1"></i> Finalizar
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr id="fila-vacia">
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fas fa-check-circle fa-2x mb-2 text-success d-block"></i>
                                        No hay ítems pendientes
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

@stop

@section('js')
    <script src="{{ asset('js/toastr.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/axios.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/sweetalert2.all.min.js') }}"></script>
    <script src="{{ asset('js/alertaPersonalizada.js') }}"></script>
    <script src="{{ asset('js/select2.min.js') }}" type="text/javascript"></script>

    <script>

        var idSalidaDetalleActual = null;

        $(function () {
            var hoy = new Date();
            var fechaHoy = hoy.toJSON().slice(0, 10);
            document.getElementById('modal-fecha').value = fechaHoy;

            $('#modal-departamento').select2({
                theme: 'bootstrap-5',
                dropdownParent: $('#modalEntrega'),
                language: { noResults: function () { return 'No encontrado'; } }
            });

            $('#editar-departamento').select2({
                theme: 'bootstrap-5',
                dropdownParent: $('#modalEditar'),
                language: { noResults: function () { return 'No encontrado'; } }
            });
        });

        // ── Abrir modal agregar entrega ───────────────────────────────
        function abrirModalEntrega(idSalidaDetalle, material) {
            var hoy = new Date();
            document.getElementById('modal-id-salida-detalle').value = idSalidaDetalle;
            document.getElementById('modal-material').value          = material;
            document.getElementById('modal-cantidad').value          = '';
            document.getElementById('modal-observacion').value       = '';
            document.getElementById('modal-fecha').value             = hoy.toJSON().slice(0, 10);
            $('#modal-departamento').val('').trigger('change');
            $('#modalEntrega').modal('show');
        }

        // ── Guardar entrega ───────────────────────────────────────────
        function guardarEntrega() {
            var idSalidaDetalle = document.getElementById('modal-id-salida-detalle').value;
            var idDepartamento  = document.getElementById('modal-departamento').value;
            var cantidad        = document.getElementById('modal-cantidad').value;
            var fecha           = document.getElementById('modal-fecha').value;
            var observacion     = document.getElementById('modal-observacion').value;

            if (!cantidad || parseInt(cantidad) <= 0) { toastr.error('Ingrese una cantidad válida'); return; }
            if (!fecha) { toastr.error('La fecha es requerida'); return; }

            var formData = new FormData();
            formData.append('id_salida_detalle', idSalidaDetalle);
            formData.append('id_departamento',   idDepartamento);
            formData.append('cantidad',          cantidad);
            formData.append('fecha_entrega',     fecha);
            formData.append('observacion',       observacion);

            openLoading();

            axios.post(urlAdmin + '/admin/pendientes/salida-parcial', formData)
                .then(function (response) {
                    closeLoading();
                    if (response.data.success === 10) {
                        $('#modalEntrega').modal('hide');
                        toastr.success('Entrega registrada correctamente');
                    } else {
                        toastr.error('Error al registrar la entrega');
                    }
                })
                .catch(function () {
                    closeLoading();
                    toastr.error('Error al procesar');
                });
        }

        // ── Marcar finalizado ─────────────────────────────────────────
        function marcarFinalizado(idSalidaDetalle) {
            Swal.fire({
                title: '¿Finalizar este ítem?',
                text: 'Ya no aparecerá en la lista de pendientes.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Sí, finalizar'
            }).then(function (result) {
                if (!result.isConfirmed) return;

                var formData = new FormData();
                formData.append('id_salida_detalle', idSalidaDetalle);

                openLoading();

                axios.post(urlAdmin + '/admin/pendientes/finalizar', formData)
                    .then(function (response) {
                        closeLoading();
                        if (response.data.success === 10) {
                            $('#fila-' + idSalidaDetalle).fadeOut(400, function () {
                                $(this).remove();
                                actualizarContador();
                            });
                            toastr.success('Ítem finalizado');
                        } else {
                            toastr.error('Error al finalizar');
                        }
                    })
                    .catch(function () {
                        closeLoading();
                        toastr.error('Error al procesar');
                    });
            });
        }

        // ── Ver detalle de entregas ───────────────────────────────────
        function verDetalle(idSalidaDetalle, material) {
            idSalidaDetalleActual = idSalidaDetalle;
            document.getElementById('detalle-material').value = material;
            $('#tabla-detalle-entregas tbody').html(
                '<tr><td colspan="6" class="text-center"><i class="fas fa-spinner fa-spin"></i> Cargando...</td></tr>'
            );
            $('#detalle-total').text('0');
            $('#modalDetalle').modal('show');
            cargarDetalleEntregas(idSalidaDetalle);
        }

        function cargarDetalleEntregas(idSalidaDetalle) {
            $('#detalle-total').text('0'); // 👈 resetear antes de cargar
            $('#tabla-detalle-entregas tbody').html(
                '<tr><td colspan="6" class="text-center"><i class="fas fa-spinner fa-spin"></i> Cargando...</td></tr>'
            );

            var formData = new FormData();
            formData.append('id_salida_detalle', idSalidaDetalle);

            axios.post(urlAdmin + '/admin/pendientes/detalle-entregas', formData)
                .then(function (response) {
                    if (response.data.success !== 10) {
                        $('#tabla-detalle-entregas tbody').html(
                            '<tr><td colspan="6" class="text-center text-danger">Error al cargar</td></tr>'
                        );
                        return;
                    }

                    var entregas = response.data.entregas;
                    $('#tabla-detalle-entregas tbody').empty();

                    if (entregas.length === 0) {
                        $('#tabla-detalle-entregas tbody').html(
                            '<tr><td colspan="6" class="text-center text-muted">Sin entregas registradas</td></tr>'
                        );
                        $('#detalle-total').text('0'); // 👈 asegurar que quede en 0
                        return;
                    }

                    var total = 0;
                    $.each(entregas, function (index, e) {
                        total += e.cantidad;
                        var fila =
                            '<tr id="entrega-fila-' + e.id + '">' +
                            '<td>' + (index + 1) + '</td>' +
                            '<td>' + formatearFecha(e.fecha_entrega) + '</td>' +
                            '<td>' + (e.departamento ? e.departamento : '<span class="text-muted">Sin departamento</span>') + '</td>' +
                            '<td class="text-center">' + e.cantidad + '</td>' +
                            '<td>' + (e.observacion ? e.observacion : '<span class="text-muted">—</span>') + '</td>' +
                            '<td>' +
                            '<div class="btn-group btn-group-sm">' +
                            '<button type="button" class="btn btn-warning" onclick="abrirEditar(' + e.id + ')">' +
                            '<i class="fas fa-edit"></i>' +
                            '</button>' +
                            '<button type="button" class="btn btn-danger" onclick="eliminarEntrega(' + e.id + ')">' +
                            '<i class="fas fa-trash"></i>' +
                            '</button>' +
                            '</div>' +
                            '</td>' +
                            '</tr>';
                        $('#tabla-detalle-entregas tbody').append(fila);
                    });

                    $('#detalle-total').text(total); // 👈 actualizar con el nuevo total
                })
                .catch(function () {
                    $('#tabla-detalle-entregas tbody').html(
                        '<tr><td colspan="6" class="text-center text-danger">Error al cargar</td></tr>'
                    );
                });
        }

        // ── Abrir modal editar ────────────────────────────────────────
        function abrirEditar(idEntrega) {
            var formData = new FormData();
            formData.append('id', idEntrega);

            openLoading();

            axios.post(urlAdmin + '/admin/pendientes/entrega/editar', formData)
                .then(function (response) {
                    closeLoading();
                    if (response.data.success !== 10) {
                        toastr.error('Error al cargar la entrega'); return;
                    }

                    var e = response.data.entrega;
                    document.getElementById('editar-id-entrega').value  = e.id;
                    document.getElementById('editar-cantidad').value    = e.cantidad;
                    document.getElementById('editar-fecha').value       = e.fecha_entrega;
                    document.getElementById('editar-observacion').value = e.observacion ?? '';
                    $('#editar-departamento').val(e.id_departamento ?? '').trigger('change');

                    $('#modalEditar').modal('show');
                })
                .catch(function () {
                    closeLoading();
                    toastr.error('Error al cargar');
                });
        }

        // ── Guardar edición ───────────────────────────────────────────
        function guardarEdicion() {
            var id             = document.getElementById('editar-id-entrega').value;
            var idDepartamento = document.getElementById('editar-departamento').value;
            var cantidad       = document.getElementById('editar-cantidad').value;
            var fecha          = document.getElementById('editar-fecha').value;
            var observacion    = document.getElementById('editar-observacion').value;

            if (!cantidad || parseInt(cantidad) <= 0) { toastr.error('Ingrese una cantidad válida'); return; }
            if (!fecha) { toastr.error('La fecha es requerida'); return; }

            var formData = new FormData();
            formData.append('id',              id);
            formData.append('id_departamento', idDepartamento);
            formData.append('cantidad',        cantidad);
            formData.append('fecha_entrega',   fecha);
            formData.append('observacion',     observacion);

            openLoading();

            axios.post(urlAdmin + '/admin/pendientes/entrega/actualizar', formData)
                .then(function (response) {
                    closeLoading();
                    if (response.data.success === 10) {
                        $('#modalEditar').modal('hide');
                        toastr.success('Entrega actualizada');
                        cargarDetalleEntregas(idSalidaDetalleActual);
                    } else {
                        toastr.error('Error al actualizar');
                    }
                })
                .catch(function () {
                    closeLoading();
                    toastr.error('Error al procesar');
                });
        }

        // ── Eliminar entrega ──────────────────────────────────────────
        function eliminarEntrega(idEntrega) {
            Swal.fire({
                title: '¿Eliminar esta entrega?',
                text: 'Esta acción no se puede deshacer.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Sí, eliminar'
            }).then(function (result) {
                if (!result.isConfirmed) return;

                var formData = new FormData();
                formData.append('id', idEntrega);

                openLoading();

                axios.post(urlAdmin + '/admin/pendientes/entrega/eliminar', formData)
                    .then(function (response) {
                        closeLoading();
                        if (response.data.success === 10) {
                            toastr.success('Entrega eliminada');
                            cargarDetalleEntregas(idSalidaDetalleActual);
                        } else {
                            toastr.error('Error al eliminar');
                        }
                    })
                    .catch(function () {
                        closeLoading();
                        toastr.error('Error al procesar');
                    });
            });
        }

        // ── Actualizar badge contador ─────────────────────────────────
        function actualizarContador() {
            var filas = $('#tabla-pendientes tbody tr:visible').length;
            $('#badge-total').text(filas + ' pendientes');

            if (filas === 0) {
                $('#tabla-pendientes tbody').html(
                    '<tr id="fila-vacia"><td colspan="6" class="text-center text-muted py-4">' +
                    '<i class="fas fa-check-circle fa-2x mb-2 text-success d-block"></i>' +
                    'No hay ítems pendientes</td></tr>'
                );
            }
        }

        function formatearFecha(fecha) {
            if (!fecha) return '—';
            var partes = fecha.split('-');
            return partes[2] + '-' + partes[1] + '-' + partes[0];
        }

        function validateInput(event) {
            const key = event.key;
            if (['Backspace', 'ArrowLeft', 'ArrowRight', 'Delete', 'Tab'].includes(key)) return true;
            if (key === 'e' || key === 'E' || key === '-' || isNaN(Number(key))) return false;
            return true;
        }

    </script>
@endsection
