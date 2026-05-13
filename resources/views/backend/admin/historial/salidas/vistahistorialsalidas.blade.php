@extends('adminlte::page')

@section('title', 'Historial / Salidas')

@section('content_header')
    <h1>Historial / Salidas</h1>
@stop

@section('plugins.Datatables', true)
@section('plugins.DatatablesPlugins', true)
@section('plugins.Sweetalert2', true)

@include('backend.urlglobal')

@section('content_top_nav_right')
    <link href="{{ asset('css/toastr.min.css') }}" type="text/css" rel="stylesheet"/>

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
    <div id="divcontenedor">
        <section class="content">
            <div class="container-fluid">
                <div class="card card-blue">
                    <div class="card-header">
                        <h3 class="card-title">Listado de Salidas</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div id="tablaDatatable"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    {{-- Modal Editar Salida --}}
    <div class="modal fade" id="modalEditar" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title text-white">
                        <i class="fas fa-edit mr-2"></i>Editar Salida
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="formulario-editar">
                        <input type="hidden" id="id-editar">
                        <div class="form-group">
                            <label>Fecha <span class="text-danger">*</span></label>
                            <input type="date" id="fecha-editar" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Descripción</label>
                            <textarea id="descripcion-editar" class="form-control"
                                      rows="3" maxlength="800"
                                      placeholder="Descripción opcional"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-warning" onclick="editar()">
                        <i class="fas fa-save mr-1"></i>Guardar cambios
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Detalle Salida --}}
    <div class="modal fade" id="modalDetalle" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info">
                    <h5 class="modal-title text-white">
                        <i class="fas fa-list mr-2"></i>
                        Detalle de Salida —
                        <span id="detalle-proyecto"></span>
                        <small class="ml-2" id="detalle-fecha"></small>
                        <span id="detalle-badge-cerrado" class="badge badge-danger ml-2" style="display:none;">
                            Proyecto Cerrado
                        </span>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="detalle-loading" class="text-center py-4">
                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                    </div>
                    <div id="detalle-contenido" style="display:none;">
                        <table class="table table-bordered table-striped table-sm">
                            <thead class="thead-dark">
                            <tr>
                                <th>#</th>
                                <th>Código</th>
                                <th>Material</th>
                                <th class="text-center">Cantidad</th>
                                <th class="text-right">Precio unitario</th>
                            </tr>
                            </thead>
                            <tbody id="detalle-tbody"></tbody>
                        </table>
                    </div>
                    <div id="detalle-vacio" class="text-center text-muted py-4" style="display:none;">
                        <i class="fas fa-inbox fa-2x mb-2"></i>
                        <p>Esta salida no tiene materiales registrados.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script src="{{ asset('js/toastr.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/axios.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/alertaPersonalizada.js') }}"></script>

    <script>
        $(function () {
            const ruta = "{{ url('/admin/historial/salidas/tabla') }}";

            function initDataTable() {
                if ($.fn.DataTable.isDataTable('#tabla')) {
                    $('#tabla').DataTable().destroy();
                }
                $('#tabla').DataTable({
                    paging: true,
                    lengthChange: true,
                    searching: true,
                    ordering: true,
                    info: true,
                    autoWidth: false,
                    responsive: true,
                    pagingType: "full_numbers",
                    lengthMenu: [[50, 100, -1], [50, 100, "Todo"]],
                    language: {
                        sProcessing:   "Procesando...",
                        sLengthMenu:   "Mostrar _MENU_ registros",
                        sZeroRecords:  "No se encontraron resultados",
                        sEmptyTable:   "Ningún dato disponible en esta tabla",
                        sInfo:         "Mostrando _START_ a _END_ de _TOTAL_ registros",
                        sInfoEmpty:    "Mostrando 0 a 0 de 0 registros",
                        sInfoFiltered: "(filtrado de _MAX_ registros)",
                        sSearch:       "Buscar:",
                        oPaginate: {
                            sFirst: "Primero", sLast: "Último",
                            sNext: "Siguiente", sPrevious: "Anterior"
                        }
                    },
                    dom:
                        "<'row align-items-center'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 text-md-right'f>>" +
                        "tr" +
                        "<'row align-items-center'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>"
                });
                $('#tabla_length select').addClass('form-control form-control-sm');
                $('#tabla_filter input').addClass('form-control form-control-sm').css('display', 'inline-block');
            }

            function cargarTabla() {
                $('#tablaDatatable').load(ruta, function () {
                    initDataTable();
                });
            }

            cargarTabla();
            window.recargar = function () { cargarTabla(); };
        });
    </script>

    <script>

        // ── Editar cabecera ───────────────────────────────────────
        function modalEditar(id) {
            openLoading();
            document.getElementById('formulario-editar').reset();

            axios.post(urlAdmin + '/admin/historial/salidas/informacion', { id: id })
                .then((response) => {
                    closeLoading();
                    if (response.data.success === 1) {
                        const s = response.data.salida;
                        $('#id-editar').val(s.id);
                        $('#fecha-editar').val(s.fecha);
                        $('#descripcion-editar').val(s.descripcion ?? '');
                        $('#modalEditar').modal('show');
                    } else {
                        toastr.error('No se pudo cargar la información');
                    }
                })
                .catch(() => { closeLoading(); toastr.error('Error al obtener información'); });
        }

        function editar() {
            const id          = $('#id-editar').val();
            const fecha       = $('#fecha-editar').val().trim();
            const descripcion = $('#descripcion-editar').val().trim();

            if (fecha === '')             { toastr.error('La fecha es requerida'); return; }
            if (descripcion.length > 800) { toastr.error('Descripción máximo 800 caracteres'); return; }

            openLoading();
            const formData = new FormData();
            formData.append('id',          id);
            formData.append('fecha',       fecha);
            formData.append('descripcion', descripcion);

            axios.post(urlAdmin + '/admin/historial/salidas/editar', formData)
                .then((response) => {
                    closeLoading();
                    if (response.data.success === 1) {
                        toastr.success('Salida actualizada correctamente');
                        $('#modalEditar').modal('hide');
                        recargar();
                    } else {
                        toastr.error('Error al actualizar');
                    }
                })
                .catch(() => { closeLoading(); toastr.error('Error al actualizar'); });
        }

        // ── Eliminar ──────────────────────────────────────────────
        function eliminar(id) {
            Swal.fire({
                title: '¿Eliminar salida?',
                text: 'Se eliminarán también todos los detalles relacionados. Esta acción no se puede deshacer.',
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.value) {
                    openLoading();
                    axios.post(urlAdmin + '/admin/historial/salidas/eliminar', { id: id })
                        .then((response) => {
                            closeLoading();
                            if (response.data.success === 1) {
                                toastr.success('Salida eliminada correctamente');
                                recargar();
                            } else {
                                toastr.error('Error al eliminar');
                            }
                        })
                        .catch(() => { closeLoading(); toastr.error('Error al eliminar'); });
                }
            });
        }

        // ── Detalle salida ────────────────────────────────────────
        function verDetalle(id, proyecto, fecha, cerrado) {
            $('#detalle-proyecto').text(proyecto);
            $('#detalle-fecha').text(fecha);
            $('#detalle-tbody').html('');
            $('#detalle-contenido').hide();
            $('#detalle-vacio').hide();
            $('#detalle-loading').show();

            if (cerrado) {
                $('#detalle-badge-cerrado').show();
            } else {
                $('#detalle-badge-cerrado').hide();
            }

            $('#modalDetalle').modal('show');

            axios.post(urlAdmin + '/admin/historial/salidas/detalle', { id: id })
                .then((response) => {
                    $('#detalle-loading').hide();
                    if (response.data.success === 1 && response.data.detalle.length > 0) {
                        let html = '';
                        response.data.detalle.forEach((fila, index) => {
                            html += `
                                <tr>
                                    <td>${index + 1}</td>
                                    <td>${fila.codigo}</td>
                                    <td>${fila.material}</td>
                                    <td class="text-center">${fila.cantidad_salida}</td>
                                    <td class="text-right">$${fila.precio}</td>
                                </tr>`;
                        });
                        $('#detalle-tbody').html(html);
                        $('#detalle-contenido').show();
                    } else {
                        $('#detalle-vacio').show();
                    }
                })
                .catch(() => {
                    $('#detalle-loading').hide();
                    $('#detalle-vacio').show();
                    toastr.error('Error al cargar el detalle');
                });
        }

    </script>
@endsection
