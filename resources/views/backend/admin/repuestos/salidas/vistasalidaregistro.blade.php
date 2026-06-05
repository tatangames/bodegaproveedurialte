@extends('adminlte::page')

@section('title', 'Registro de Salidas')

@section('content_header')
    <h1>Registro de Salidas</h1>
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

    <style>
        #matriz { table-layout: auto; word-break: break-word; width: 100%; }
        #matriz-busqueda { table-layout: fixed; }
        .cursor-pointer { cursor: pointer; }
        .cursor-pointer:hover { color: #401fd2; font-weight: bold; }
        *:focus { outline: none; }
    </style>

    <div id="divcontenedor">

        {{-- ══ Card Información ══ --}}
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-10">
                        <div class="card card-gray-dark">
                            <div class="card-header">
                                <h3 class="card-title">Información de Salida</h3>
                            </div>
                            <div class="card-body">

                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Fecha: <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="fecha">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>N° Solicitud: <small class="text-muted">(Opcional)</small></label>
                                            <input type="text" class="form-control" autocomplete="off"
                                                   maxlength="100" id="numero_solicitud" placeholder="Ej: SOL-001">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-5">
                                        <div class="form-group">
                                            <label>Tipo de Salida: <span class="text-danger">*</span></label>
                                            <select class="form-control" id="select-tiposalida" style="width:100%">
                                                <option value="">Seleccione...</option>
                                                @foreach($arrayTipoSalida as $ts)
                                                    <option value="{{ $ts->id }}">{{ $ts->nombre }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="form-group">
                                            <label>Departamento: <small class="text-muted">(Opcional)</small></label>
                                            <select class="form-control" id="select-departamento" style="width:100%">
                                                <option value="">Sin departamento</option>
                                                @foreach($arrayDepartamentos as $dep)
                                                    <option value="{{ $dep->id }}">{{ $dep->nombre }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label>Descripción: <small class="text-muted">(Opcional)</small></label>
                                            <input type="text" class="form-control" autocomplete="off"
                                                   maxlength="800" id="descripcion">
                                        </div>
                                    </div>
                                    <div class="col-md-4 d-flex align-items-end justify-content-end">
                                        <div class="form-group">
                                            <button type="button" id="botonaddmaterial"
                                                    onclick="abrirModal()"
                                                    class="btn btn-primary btn-sm" disabled>
                                                <i class="fas fa-search mr-1"></i> Buscar Material
                                            </button>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ══ Modal Buscar Material ══ --}}
        <div class="modal fade" id="modalRepuesto">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header" style="background:#2156af">
                        <h4 class="modal-title text-white">
                            <i class="fas fa-search mr-2"></i>Buscar Material
                        </h4>
                        <button type="button" class="close text-white" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="formulario-repuesto">
                            <div class="card-body">
                                <div class="form-group">
                                    <label>Buscar material:</label>
                                    <table class="table" id="matriz-busqueda">
                                        <tbody>
                                        <tr>
                                            <td>
                                                <input id="inputBuscador" autocomplete="off"
                                                       class="form-control" style="width:100%"
                                                       onkeyup="buscarMaterial(this)"
                                                       maxlength="300" type="text"
                                                       placeholder="Escribir nombre del material...">
                                                <div class="droplista" id="midropmenu"
                                                     style="position:absolute;z-index:9;width:95%!important;"></div>
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══ Modal Cantidades ══ --}}
        <div class="modal fade" id="modalCantidad">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header" style="background:#1a3a6b">
                        <h4 class="modal-title text-white">
                            <i class="fas fa-boxes mr-2"></i>Salida de Material
                        </h4>
                        <button type="button" class="close text-white" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="formulario-material">
                            <div class="card-body">

                                <input type="hidden" id="id-material-seleccionado">

                                <div class="form-row mb-3">
                                    <div class="col-md-9">
                                        <label>Material</label>
                                        <input type="text" disabled class="form-control" id="info-material">
                                    </div>
                                    <div class="col-md-3">
                                        <label>U/M</label>
                                        <input type="text" disabled class="form-control" id="info-medida">
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm" id="matrizM">
                                        <thead class="thead-dark">
                                        <tr>
                                            <th>Fecha Ingreso</th>
                                            <th>Detalle/Código</th>
                                            <th>Precio</th>
                                            <th class="text-center">Cant. Actual</th>
                                            <th class="text-center">Cant. Salida</th>
                                        </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>

                            </div>
                        </form>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                        <button type="button" class="btn btn-success" onclick="agregarAlDetalle()">
                            <i class="fas fa-plus mr-1"></i> Agregar al Detalle
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══ Tabla Detalle ══ --}}
        <section class="content-header">
            <div class="row mb-2">
                <div class="col-sm-6"><h2>Detalle de Salida</h2></div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <div class="card card-gray-dark">
                    <div class="card-header">
                        <h3 class="card-title">Materiales a retirar</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0" id="matriz">
                                <thead>
                                <tr>
                                    <th style="width:5%">#</th>
                                    <th style="width:60%">Material</th>
                                    <th style="width:20%">Cantidad Salida</th>
                                    <th style="width:15%">Opciones</th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ══ Botón Guardar ══ --}}
        <div class="modal-footer justify-content-between" style="margin-top:25px;">
            <button type="button" class="btn btn-success" onclick="preguntaGuardar()">
                <i class="fas fa-save mr-1"></i> Guardar Salida
            </button>
        </div>

    </div>

@stop

@section('js')
    <script src="{{ asset('js/toastr.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/axios.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/sweetalert2.all.min.js') }}"></script>
    <script src="{{ asset('js/alertaPersonalizada.js') }}"></script>
    <script src="{{ asset('js/select2.min.js') }}" type="text/javascript"></script>

    <script>
        var seguroBuscador = true;

        $(function () {
            var hoy = new Date();
            document.getElementById('fecha').value = hoy.toJSON().slice(0, 10);

            $('#select-tiposalida').select2({
                theme: 'bootstrap-5',
                dropdownParent: $('body'),
                language: { noResults: function () { return 'No encontrado'; } }
            });

            $('#select-departamento').select2({
                theme: 'bootstrap-5',
                dropdownParent: $('body'),
                language: { noResults: function () { return 'No encontrado'; } }
            });

            // Habilitar botón buscar cuando se seleccione tipo salida
            $('#select-tiposalida').on('change', function () {
                var val = $(this).val();
                $('#botonaddmaterial').prop('disabled', !val || val === '');
            });

            $(document).click(function () { $('.droplista').hide(); });
        });

        // ── Modal buscador ────────────────────────────────────────────
        function abrirModal() {
            document.getElementById('formulario-repuesto').reset();
            $('.droplista').html('').hide();
            $('#modalRepuesto').modal('show');
        }

        // ── Buscar material ───────────────────────────────────────────
        function buscarMaterial(e) {
            if (!seguroBuscador) return;
            seguroBuscador = false;

            var texto = e.value;
            if (texto === '') {
                $('.droplista').hide();
                seguroBuscador = true;
                return;
            }

            axios.post(urlAdmin + '/admin/buscar/material/disponible', { query: texto })
                .then(function (response) {
                    seguroBuscador = true;
                    $('#midropmenu').fadeIn().html(response.data);
                })
                .catch(function () { seguroBuscador = true; });
        }

        // ── Seleccionar material → modal cantidades ───────────────────
        function modificarValor(edrop) {
            openLoading();
            $('#matrizM tbody').empty();

            var formData = new FormData();
            formData.append('id', edrop.id);

            axios.post(urlAdmin + '/admin/buscar/material/disponibilidad', formData)
                .then(function (response) {
                    closeLoading();
                    if (response.data.success !== 1) {
                        toastr.error('Error al cargar material'); return;
                    }
                    if (response.data.disponible === 1) {
                        toastr.info('NO HAY INVENTARIO DISPONIBLE'); return;
                    }

                    $('#id-material-seleccionado').val(edrop.id);
                    $('#info-material').val(response.data.nombreMaterial);
                    $('#info-medida').val(response.data.nombreMedida);

                    $.each(response.data.arrayIngreso, function (key, val) {
                        var fila =
                            '<tr>' +
                            '<td><input disabled value="' + val.fechaIngreso + '" class="form-control form-control-sm" type="text"></td>' +
                            '<td><input disabled value="' + (val.codigo ?? '') + '" class="form-control form-control-sm" type="text"></td>' +
                            '<td><input disabled value="' + val.precioFormat + '" class="form-control form-control-sm" type="text"></td>' +
                            '<td class="text-center"><input disabled name="arrayCantidadActual[]" ' +
                            'data-cantidadActualFila="' + val.cantidadActual + '" ' +
                            'value="' + val.cantidadActual + '" ' +
                            'class="form-control form-control-sm text-center" type="number"></td>' +
                            '<td><input class="form-control form-control-sm" ' +
                            'data-idfilaentradadetalle="' + val.id + '" ' +
                            'name="arrayCantidadSalida[]" min="0" max="' + val.cantidadActual + '" ' +
                            'type="number" placeholder="0" ' +
                            'onkeydown="return validateInput(event);" ' +
                            'oninput="validateCantidadSalida(this, ' + val.cantidadActual + ');">' +
                            '</td>' +
                            '</tr>';
                        $('#matrizM tbody').append(fila);
                    });

                    $('#modalRepuesto').modal('hide');
                    $('#modalCantidad').modal('show');
                })
                .catch(function () { closeLoading(); toastr.error('Error'); });
        }

        // ── Agregar al detalle ────────────────────────────────────────
        function agregarAlDetalle() {
            var arrayIdEntradaDetalle = $("input[name='arrayCantidadSalida[]']")
                .map(function () { return $(this).attr('data-idfilaentradadetalle'); }).get();
            var arrayCantidadSalida = $("input[name='arrayCantidadSalida[]']")
                .map(function () { return $(this).val(); }).get();
            var arrayCantidadActual = $("input[name='arrayCantidadActual[]']")
                .map(function () { return $(this).attr('data-cantidadActualFila'); }).get();

            colorBlancoMatriz();
            var habraSalida = false;

            for (var a = 0; a < arrayCantidadSalida.length; a++) {
                var fc  = arrayCantidadSalida[a];
                var max = arrayCantidadActual[a];

                if (fc !== '' && parseInt(fc) > 0) {
                    habraSalida = true;
                    if (parseInt(fc) > parseInt(max)) {
                        colorRojoMatriz(a);
                        toastr.error('Fila #' + (a + 1) + ': Supera cantidad disponible (' + max + ')');
                        return;
                    }
                }
                if (fc !== '' && parseInt(fc) <= 0 && fc !== '') {
                    colorRojoMatriz(a);
                    toastr.error('Fila #' + (a + 1) + ': No se permite cero o negativo');
                    return;
                }
            }

            if (!habraSalida) { toastr.error('Registre mínimo 1 cantidad de salida'); return; }

            var nombreTexto = document.getElementById('info-material').value;

            for (var z = 0; z < arrayCantidadSalida.length; z++) {
                var fc2 = arrayCantidadSalida[z];
                if (fc2 !== '' && parseInt(fc2) > 0) {
                    var nFilas = $('#matriz tbody tr').length + 1;
                    var fila =
                        '<tr>' +
                        '<td><span class="num-fila">' + nFilas + '</span></td>' +
                        '<td>' +
                        '<input name="idmaterialArray[]" type="hidden" ' +
                        'data-idmaterialArray="' + arrayIdEntradaDetalle[z] + '" ' +
                        'data-nombreMaterial="' + nombreTexto + '">' +
                        nombreTexto +
                        '</td>' +
                        '<td>' +
                        '<input name="salidaArray[]" type="hidden" data-cantidadSalida="' + fc2 + '">' +
                        fc2 +
                        '</td>' +
                        '<td>' +
                        '<button type="button" class="btn btn-danger btn-sm btn-block" onclick="borrarFila(this)">' +
                        '<i class="fas fa-trash"></i> Borrar</button>' +
                        '</td>' +
                        '</tr>';
                    $('#matriz tbody').append(fila);
                }
            }

            $('#modalCantidad').modal('hide');
            document.getElementById('inputBuscador').value = '';
            $('.droplista').html('').hide();
            toastr.success('Material agregado al detalle');
        }

        // ── Guardar salida ────────────────────────────────────────────
        function preguntaGuardar() {
            colorBlancoTabla();
            Swal.fire({
                title: '¿Guardar Salida?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#d33',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Sí, guardar'
            }).then(function (result) {
                if (result.isConfirmed) guardarSalida();
            });
        }

        function guardarSalida() {
            var fecha            = document.getElementById('fecha').value;
            var tiposalida       = document.getElementById('select-tiposalida').value;
            var departamento     = document.getElementById('select-departamento').value;
            var descripcion      = document.getElementById('descripcion').value;
            var numero_solicitud = document.getElementById('numero_solicitud').value;

            if (!fecha)      { toastr.error('Fecha es requerida');           return; }
            if (!tiposalida) { toastr.error('Tipo de Salida es requerido');  return; }

            if ($('#matriz tbody tr').length === 0) {
                toastr.error('Agregue al menos un material'); return;
            }

            var idEntradaDetalle = $("input[name='idmaterialArray[]']")
                .map(function () { return $(this).attr('data-idmaterialArray'); }).get();
            var salidaCantidad = $("input[name='salidaArray[]']")
                .map(function () { return $(this).attr('data-cantidadSalida'); }).get();

            var contenedorArray = [];
            for (var p = 0; p < salidaCantidad.length; p++) {
                contenedorArray.push({
                    infoIdEntradaDeta: idEntradaDetalle[p],
                    infoCantidad:      salidaCantidad[p],
                });
            }

            openLoading();
            var formData = new FormData();
            formData.append('fecha',            fecha);
            formData.append('tiposalida',       tiposalida);
            formData.append('departamento',     departamento);
            formData.append('descripcion',      descripcion);
            formData.append('numero_solicitud', numero_solicitud);
            formData.append('contenedorArray',  JSON.stringify(contenedorArray));

            axios.post(urlAdmin + '/admin/salida/guardar', formData)
                .then(function (response) {
                    closeLoading();
                    if (response.data.success === 10) {
                        Swal.fire({
                            title: 'Salida Registrada',
                            icon: 'success',
                            allowOutsideClick: false,
                            confirmButtonColor: '#28a745',
                            confirmButtonText: 'Aceptar'
                        }).then(function () { location.reload(); });
                    } else if (response.data.success === 2) {
                        Swal.fire({
                            title: 'Cantidad no disponible',
                            html: '<b>' + response.data.nombre_material + '</b><br><br>' +
                                'Solicitado: <b>' + response.data.cantidad_pedida + '</b><br>' +
                                'Disponible: <b>' + response.data.disponible + '</b>',
                            icon: 'warning',
                            confirmButtonColor: '#d33',
                            confirmButtonText: 'Entendido'
                        });
                    } else {
                        toastr.error('Error al guardar');
                    }
                })
                .catch(function () { closeLoading(); toastr.error('Error al guardar'); });
        }

        // ── Utilidades ────────────────────────────────────────────────
        function borrarFila(btn) {
            $(btn).closest('tr').remove();
            renumerarFilas();
        }

        function renumerarFilas() {
            $('#matriz tbody tr').each(function (i) {
                $(this).find('.num-fila').text(i + 1);
            });
        }

        function colorRojoTabla(index) {
            $('#matriz tbody tr:eq(' + index + ')').css('background', '#f8d7da');
        }

        function colorBlancoTabla() {
            $('#matriz tbody tr').css('background', 'white');
        }

        function colorRojoMatriz(index) {
            $('#matrizM tbody tr:eq(' + index + ')').css('background', '#f8d7da');
        }

        function colorBlancoMatriz() {
            $('#matrizM tbody tr').css('background', 'white');
        }

        function validateInput(event) {
            const key = event.key;
            if (['Backspace', 'ArrowLeft', 'ArrowRight', 'Delete', 'Tab'].includes(key)) return true;
            if (key === 'e' || key === 'E' || key === '-' || isNaN(Number(key))) return false;
            return true;
        }

        function validateCantidadSalida(input, maxCantidad) {
            input.value = input.value.replace(/[^0-9]/g, '');
            if (Number(input.value) > maxCantidad) input.value = maxCantidad;
        }
    </script>
@endsection
