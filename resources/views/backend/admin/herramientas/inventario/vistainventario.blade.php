@extends('adminlte::page')

@section('title', 'Inventario - Herramientas')

@section('content_header')
    <h1>Inventario - Herramientas</h1>
@stop


{{-- Activa plugins que necesitas --}}
@section('plugins.Datatables', true)
@section('plugins.DatatablesPlugins', true)
@section('plugins.Sweetalert2', true)

@include('backend.urlglobal')

@section('content_top_nav_right')
    <link href="{{ asset('css/toastr.min.css') }}" type="text/css" rel="stylesheet" />
    <link href="{{ asset('css/select2.min.css') }}" type="text/css" rel="stylesheet">
    <link href="{{ asset('css/select2-bootstrap-5-theme.min.css') }}" type="text/css" rel="stylesheet">

    <li class="nav-item dropdown">
        <a href="#" class="nav-link" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
            <i class="fas fa-cogs"></i>
            <span class="d-none d-md-inline">
                {{ Auth::guard('admin')->user()->nombre }}
            </span>
        </a>

        <div class="dropdown-menu dropdown-menu-right">
            <a href="{{ route('admin.perfil') }}" class="dropdown-item">
                <i class="fas fa-user mr-2"></i>
                Editar Perfil
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
    table{
        /*Ajustar tablas*/
        table-layout:fixed;
    }
    .select2-container{
        height: 30px !important;
    }


</style>

<div id="divcontenedor">

    <section class="content-header">
        <div class="row">

            <button type="button" style="margin-left: 15px" onclick="modalAgregar()" class="btn btn-dark btn-sm">
                <i class="fas fa-plus-square"></i>
                Registrar Herramienta
            </button>

        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-blue">
                <div class="card-header">
                    <h3 class="card-title">Listado Catálogo de Herramientas</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12">
                            <div id="tablaDatatable">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="modal fade" id="modalAgregar">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Nueva Herramienta</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="formulario-nuevo" onsubmit="event.preventDefault(); verificarGuardar();">
                        <div class="card-body">

                            <div class="form-group">
                                <label>Nombre:</label>
                                <input type="text" class="form-control" autocomplete="off" onpaste="contarcaracteresIngreso();" onkeyup="contarcaracteresIngreso();" maxlength="300" id="nombre-nuevo" placeholder="Nombre de la herramienta">
                                <div id="res-caracter-nuevo" style="float: right">0/300</div>
                            </div>

                            <br>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Código (Opcional):</label>
                                    <input type="text" class="form-control" autocomplete="off" id="codigo-nuevo" maxlength="100">
                                </div>
                            </div>


                            <div class="col-md-5">
                                <div class="form-group">
                                    <label>Unidad de Medida:</label>
                                    <br>
                                    <select width="70%"  class="form-control" id="select-unidad-nuevo">
                                        <option value="" selected>Seleccione una opción (Opcional)...</option>
                                        @foreach($lUnidad as $sel)
                                            <option value="{{ $sel->id }}">{{ $sel->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                        </div>
                    </form>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary" onclick="verificarGuardar()">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- modal editar -->
    <div class="modal fade" id="modalEditar">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Editar Material</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="formulario-editar" onsubmit="event.preventDefault(); editar();">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12">

                                    <div class="form-group">
                                        <input type="hidden" id="id-editar">
                                    </div>


                                    <div class="form-group">
                                        <label>Nombre: <span style="color: red">*</span></label>
                                        <input type="text" class="form-control" autocomplete="off" onpaste="contarcaracteresEditar();" onkeyup="contarcaracteresEditar();" maxlength="300" id="nombre-editar" placeholder="Nombre del material">
                                        <div id="res-caracter-editar" style="float: right">0/300</div>
                                    </div>

                                    <br>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Código:</label>
                                            <input type="text" class="form-control" autocomplete="off" id="codigo-editar" maxlength="100">
                                        </div>
                                    </div>

                                    <div class="col-md-7">
                                        <div class="form-group">
                                            <label>Unidad de Medida: <span style="color: red">*</span></label>
                                            <br>
                                            <select style="width: 70%; height: 45px"  class="form-control" id="select-unidad-editar">
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary" onclick="editar()">Actualizar</button>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="modalDescartar">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Descartar Herramienta</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="formulario-descartar">
                    <div class="card-body">

                        <div class="form-group">
                            <input type="hidden" id="id-descartar">
                        </div>

                        <div class="form-group">
                            <label>Herramienta:</label>
                            <input type="text" disabled class="form-control" autocomplete="off" id="nombre-herra">
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Cantidad Disponible:</label>
                                <input type="number" disabled class="form-control" autocomplete="off" id="cantidad-actual">
                            </div>
                        </div>

                        <hr>

                        <div class="col-md-5">
                            <div class="form-group">
                                <label>Descripción:</label>
                                <input type="text" class="form-control" autocomplete="off" id="descripcion-descartar" maxlength="800">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Cantidad a Descartar:</label>
                                <input type="number" min="1" class="form-control" autocomplete="off" id="cantidad-descartar" maxlength="10">
                            </div>
                        </div>



                    </div>
                </form>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" onclick="registrarDescarto()">Guardar</button>
            </div>
        </div>
    </div>
</div>


@stop
@section('js')



    <script src="{{ asset('js/jquery.dataTables.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/dataTables.bootstrap4.js') }}" type="text/javascript"></script>

    <script src="{{ asset('js/toastr.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/axios.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/sweetalert2.all.min.js') }}"></script>
    <script src="{{ asset('js/alertaPersonalizada.js') }}"></script>
    <script src="{{ asset('js/select2.min.js') }}" type="text/javascript"></script>


    <script>
        $(function () {
            const ruta = "{{ url('/admin/inventario/herramientas/tabla') }}";

            function initDataTable() {
                // Si ya hay instancia, destrúyela antes de re-crear
                if ($.fn.DataTable.isDataTable('#tabla')) {
                    $('#tabla').DataTable().destroy();
                }

                // Inicializa
                $('#tabla').DataTable({
                    paging: true,
                    lengthChange: true,
                    searching: true,
                    ordering: true,
                    info: true,
                    autoWidth: false,
                    responsive: true,
                    pagingType: "full_numbers",
                    lengthMenu: [[100, 150, -1], [100, 150, "Todo"]],
                    language: {
                        sProcessing: "Procesando...",
                        sLengthMenu: "Mostrar _MENU_ registros",
                        sZeroRecords: "No se encontraron resultados",
                        sEmptyTable: "Ningún dato disponible en esta tabla",
                        sInfo: "Mostrando _START_ a _END_ de _TOTAL_ registros",
                        sInfoEmpty: "Mostrando 0 a 0 de 0 registros",
                        sInfoFiltered: "(filtrado de _MAX_ registros)",
                        sSearch: "Buscar:",
                        oPaginate: {sFirst: "Primero", sLast: "Último", sNext: "Siguiente", sPrevious: "Anterior"},
                        oAria: {sSortAscending: ": Orden ascendente", sSortDescending: ": Orden descendente"}
                    },
                    dom:
                        "<'row align-items-center'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 text-md-right'f>>" +
                        "tr" +
                        "<'row align-items-center'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>"
                });

                $('#select-unidad-nuevo').select2({
                    theme: "bootstrap-5",
                    "language": {
                        "noResults": function(){
                            return "Busqueda no encontrada";
                        }
                    },
                });

                $('#select-unidad-editar').select2({
                    theme: "bootstrap-5",
                    "language": {
                        "noResults": function(){
                            return "Busqueda no encontrada";
                        }
                    },
                });

                // Estilitos
                $('#tabla_length select').addClass('form-control form-control-sm');
                $('#tabla_filter input').addClass('form-control form-control-sm').css('display', 'inline-block');
            }

            function cargarTabla() {
                $('#tablaDatatable').load(ruta, function () {
                    // AQUI debe existir exactamente un <table id="tabla"> en la parcial
                    initDataTable();
                });
            }

            // Primera carga
            cargarTabla();

            // Exponer recarga para tus flujos (crear/editar)
            window.recargar = function () {
                cargarTabla();
            };
        });
    </script>



    <script>

        function recargar(){
            var ruta = "{{ url('/admin/inventario/herramientas/tabla') }}";
            $('#tablaDatatable').load(ruta);
        }

        function modalAgregar(){
            document.getElementById("formulario-nuevo").reset();
            document.getElementById('res-caracter-nuevo').innerHTML = '0/300 ';

            $('#select-codigo-nuevo').prop('selectedIndex', 0).change();
            $('#select-unidad-nuevo').prop('selectedIndex', 0).change();
            $('#select-clasi-nuevo').prop('selectedIndex', 0).change();

            $('#modalAgregar').modal({backdrop: 'static', keyboard: false})
        }

        function verificarGuardar(){
            Swal.fire({
                title: 'Guardar Herramienta?',
                text: "",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#d33',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Si'
            }).then((result) => {
                if (result.isConfirmed) {
                    nuevo();
                }
            })
        }

        function nuevo(){

            var nombre = document.getElementById('nombre-nuevo').value;
            var codigo = document.getElementById('codigo-nuevo').value;
            var unidad = document.getElementById('select-unidad-nuevo').value; // nullable

            if(nombre === ''){
                toastr.error('Nombre es requerido');
                return;
            }

            openLoading();
            var formData = new FormData();
            formData.append('nombre', nombre);
            formData.append('codigo', codigo);
            formData.append('unidad', unidad);

            axios.post(urlAdmin+'/admin/inventario/herramientas/nuevo', formData, {
            })
                .then((response) => {
                    closeLoading();
                    if(response.data.success === 1){
                        toastr.success('Registrado correctamente');
                        $('#modalAgregar').modal('hide');
                        recargar();
                    }

                    else {
                        toastr.error('Error al registrar');
                    }
                })
                .catch((error) => {
                    toastr.error('Error al registrar');
                    closeLoading();
                });
        }

        function informacion(id){
            openLoading();
            document.getElementById("formulario-editar").reset();

            axios.post(urlAdmin+'/admin/inventario/herramientas/informacion',{
                'id': id
            })
                .then((response) => {
                    closeLoading();
                    if(response.data.success === 1){
                        $('#modalEditar').modal({backdrop: 'static', keyboard: false})

                        $('#id-editar').val(id);
                        $('#nombre-editar').val(response.data.herramienta.nombre);
                        $('#codigo-editar').val(response.data.herramienta.codigo);

                        contarcaracteresEditar();

                        document.getElementById("select-unidad-editar").options.length = 0;
                        $('#select-unidad-editar').append('<option value="" selected="selected">Seleccione una opción...</option>');

                        // unidad de medida
                        $.each(response.data.unidad, function( key, val ){
                            if(response.data.herramienta.id_medida == val.id){
                                $('#select-unidad-editar').append('<option value="' +val.id +'" selected="selected">'+ val.nombre +'</option>');
                            }else{
                                $('#select-unidad-editar').append('<option value="' +val.id +'">'+ val.nombre +'</option>');
                            }
                        });

                    }else{
                        toastr.error('Información no encontrada');
                    }
                })
                .catch((error) => {
                    closeLoading();
                    toastr.error('Información no encontrada');
                });
        }

        function editar(){

            var id = document.getElementById('id-editar').value;
            var nombre = document.getElementById('nombre-editar').value;
            var codigo = document.getElementById('codigo-editar').value;
            var unidad = document.getElementById('select-unidad-editar').value; // nullable

            if(nombre === ''){
                toastr.error('Nombre es requerido');
                return;
            }

            openLoading();
            var formData = new FormData();
            formData.append('id', id);
            formData.append('nombre', nombre);
            formData.append('codigo', codigo);
            formData.append('unidad', unidad);

            axios.post(urlAdmin+'/admin/inventario/herramienta/editar', formData, {
            })
                .then((response) => {
                    closeLoading();
                    if(response.data.success === 1){
                        toastr.success('Actualizado correctamente');
                        $('#modalEditar').modal('hide');
                        recargar();
                    }

                    else {
                        toastr.error('Error al registrar');
                    }
                })
                .catch((error) => {
                    toastr.error('Error al registrar');
                    closeLoading();
                });
        }

        function contarcaracteresIngreso(){
            setTimeout(function(){
                var valor = document.getElementById('nombre-nuevo');
                var cantidad = valor.value.length;
                document.getElementById('res-caracter-nuevo').innerHTML = cantidad + '/300 ';
            },10);
        }

        function contarcaracteresEditar(){
            setTimeout(function(){
                var valor = document.getElementById('nombre-editar');
                var cantidad = valor.value.length;
                document.getElementById('res-caracter-editar').innerHTML = cantidad + '/300 ';
            },10);
        }

        // mostrara que materiales quedan aun
        function infoDetalle(id){
            window.location.href="{{ url('/admin/detalle/material/cantidad') }}/" + id;
        }


        //************************************************

        function infoModalDescartar(id){

            openLoading();
            document.getElementById("formulario-descartar").reset();

            axios.post(urlAdmin+'/admin/informacion/herramienta/descartar',{
                'id': id
            })
                .then((response) => {
                    closeLoading();
                    if(response.data.success === 1){
                        $('#modalDescartar').modal({backdrop: 'static', keyboard: false})

                        $('#id-descartar').val(id);
                        $('#nombre-herra').val(response.data.info.nombre);
                        $('#cantidad-actual').val(response.data.info.cantidad);

                    }else{
                        toastr.error('Información no encontrada');
                    }
                })
                .catch((error) => {
                    closeLoading();
                    toastr.error('Información no encontrada');
                });
        }



        function registrarDescarto(){

            var id = document.getElementById('id-descartar').value;
            var cantidadRe = document.getElementById('cantidad-descartar').value;
            var descripcion = document.getElementById('descripcion-descartar').value;

            var reglaNumeroEntero = /^[0-9]\d*$/;

            if(cantidadRe === ''){
                toastr.error('Cantidad a Descartar es requerido');
                return;
            }

            if(!cantidadRe.match(reglaNumeroEntero)) {
                toastr.error('Cantidad a Descartar debe ser número Entero y no Negativo');
                return;
            }

            if(cantidadRe <= 0){
                toastr.error('Cantidad a Descartar no debe ser negativo o cero');
                return;
            }

            if(cantidadRe > 9000000){
                toastr.error('Cantidad a Descartar no debe ser mayor 9 millones');
                return;
            }


            if(descripcion === ''){
                toastr.error('Descripción es requerido');
                return;
            }


            openLoading();

            var formData = new FormData();
            formData.append('id', id);
            formData.append('cantidad', cantidadRe);
            formData.append('descripcion', descripcion);

            axios.post(urlAdmin+'/admin/descartar/herramienta/inventario', formData, {
            })
                .then((response) => {
                    closeLoading();
                    if(response.data.success === 1){

                        Swal.fire({
                            title: 'Error',
                            text: "La cantidad a Descartar es Mayor a la disponible",
                            icon: 'info',
                            showCancelButton: false,
                            confirmButtonColor: '#28a745',
                            cancelButtonColor: '#d33',
                            cancelButtonText: 'Cancelar',
                            confirmButtonText: 'Aceptar'
                        }).then((result) => {
                            if (result.isConfirmed) {

                            }
                        })
                    }

                    else if(response.data.success === 2){

                        toastr.success('Herramienta Descartada Correctamente');
                        $('#modalDescartar').modal('hide');
                        recargar();
                    }
                    else {
                        toastr.error('Error al registrar');
                    }
                })
                .catch((error) => {
                    toastr.error('Error al registrar');
                    closeLoading();
                });
        }


    </script>


@endsection
