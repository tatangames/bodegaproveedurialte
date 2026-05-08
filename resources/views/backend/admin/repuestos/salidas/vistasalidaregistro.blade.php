@extends('adminlte::page')

@section('title', 'Registro de Salidas')

@section('content_header')
    <h1>Registro de Salidas</h1>
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
</style>

<div id="divcontenedor">

    <section class="content">
        <div class="container-fluid">
            <div class="row">

                <div class="col-md-8">

                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Información</h3>
                        </div>

                        <div class="card-body">

                            <div class="card-body">
                                <div class="row">
                                    <label>Fecha: <span style="color: red">*</span></label>
                                    <input style="width: 30%; margin-left: 25px;" type="date" class="form-control" id="fecha">
                                </div>
                            </div>

                            <div style="margin-left: 15px; margin-right: 15px; margin-top: 15px;">
                                <div class="form-group">
                                    <label>Asignar Proyecto: <span style="color: red">*</span></label>
                                    <select id="select-tipoproyecto" class="form-control" onchange="borrarTabla(this)">
                                        <option value="">Seleccionar Proyecto</option>
                                        @foreach($tipoproyecto as $item)
                                            <option value="{{$item->id}}">{{ $item->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div style="margin-left: 15px; margin-right: 15px; margin-top: 25px;">
                                <div class="form-group">
                                    <label>Descripción:</label>
                                    <input type="text" class="form-control" autocomplete="off" maxlength="800" id="descripcion">
                                </div>
                            </div>

                            <div class="form-group" style="float: right">
                                <br>
                                <button type="button" id="botonaddmaterial" onclick="abrirModal()" class="btn btn-primary btn-sm float-right" style="margin-top:10px; margin-right: 15px;">
                                    <i class="fas fa-plus" title="Agregar Repuesto"></i> Agregar Material</button>
                            </div>
                        </div>
                    </div>
                </div>


            </div>
        </div>
    </section>


    <div class="modal fade" id="modalRepuesto" >
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Buscar Material</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    <form id="formulario-repuesto">
                        <div class="card-body">

                            <div class="form-group">
                                <label class="control-label">Material</label>

                                <table class="table" id="matriz-busqueda" data-toggle="table">
                                    <tbody>
                                    <tr>
                                        <td>
                                            <input id="repuesto" data-info='0' class='form-control' autocomplete="off" style='width:100%' onkeyup='buscarMaterial(this)' maxlength='400'  type='text'>
                                            <div class='droplista' style='position: absolute; z-index: 9; width: 75% !important;'></div>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- cargara vista de selección -->
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div id="tablaRepuesto">

                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </form>

                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <section class="content-header">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h2>Detalle de Salida</h2>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Información de Ingreso</h3>
                </div>

                <div style="margin: 15px;">
                    <table class="table" id="matriz" data-toggle="table">
                        <thead>
                        <tr>
                            <th style="width: 3%">#</th>
                            <th style="width: 10%">Material</th>
                            <th style="width: 6%">Inventario Disponible</th>
                            <th style="width: 6%">Salida</th>
                            <th style="width: 5%">Opciones</th>
                        </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>



            </div>
        </div>
    </section>

    <div class="modal-footer justify-content-between">
        <button type="button" class="btn btn-success" onclick="preguntaGuardar()">Guardar</button>
    </div>


    <div class="modal fade" id="modalBloque">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Cantidad de Salida</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="formulario-bloque">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12">


                                    <div class="form-group">
                                        <label>Material:</label>
                                        <input type="text" disabled class="form-control" autocomplete="off" id="nombre-modal">
                                        <input type="hidden" id="id-modal">
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Cantidad de Inventario:</label>
                                            <input type="number" disabled class="form-control" autocomplete="off" id="cantidad-inventario-modal">
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Cantidad a Retirar:</label>
                                            <input type="text" class="form-control" autocomplete="off" id="cantidad-sacar-modal" maxlength="12">
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary" onclick="agregarFila()">Agregar a Detalle</button>
                </div>
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
    <script src="{{ asset('js/bootstrap-input-spinner.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/custom-editors.js') }}" type="text/javascript"></script>



    <script type="text/javascript">
        $(document).ready(function(){
            document.getElementById("divcontenedor").style.display = "block";

            var fecha = new Date();
            document.getElementById('fecha').value = fecha.toJSON().slice(0,10);

            window.seguroBuscador = true;

            $(document).click(function(){
                $(".droplista").hide();
            });

            $(document).ready(function() {
                $('[data-toggle="popover"]').popover({
                    placement: 'top',
                    trigger: 'hover'
                });
            });

            $('#select-tipoproyecto').select2({
                theme: "bootstrap-5",
                "language": {
                    "noResults": function(){
                        return "Búsqueda no encontrada";
                    }
                },
            });

            document.querySelector('#botonaddmaterial').disabled = true;
        });
    </script>

    <script>

        function abrirModal(){
            document.getElementById('tablaRepuesto').innerHTML = "";
            document.getElementById("formulario-repuesto").reset();
            $('#select-equipo').prop('selectedIndex', 0).change();
            $('#modalRepuesto').modal('show');
        }

        function verificarSalida(){
            var divs = document.getElementsByClassName('arraycolor');
            for (var i = 0; i < divs.length; i++) {
                $(divs[i]).css("background-color", "transparent");
            }

            Swal.fire({
                title: 'Verificar?',
                text: "",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#d33',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Si'
            }).then((result) => {
                if (result.isConfirmed) {
                    agregarFila();
                }
            })
        }

        function agregarFila(){

            var idMaterial = document.getElementById('id-modal').value;
            var nomRepuesto = document.getElementById('nombre-modal').value;
            var cantidadInventario = document.getElementById('cantidad-inventario-modal').value;
            var cantidadSalida = document.getElementById('cantidad-sacar-modal').value;

            var reglaNumeroDosDecimal = /^([0-9]+\.?[0-9]{0,2})$/;


            if(idMaterial === ''){
                toastr.error('Material es requerido');
            }


            if(cantidadSalida === ''){
                toastr.error('Cantidad de Salidad es requerida');
                return;
            }

            if(!cantidadSalida.match(reglaNumeroDosDecimal)) {
                toastr.error('Cantidad de Salida debe ser número Decimal (2 decimales) y no Negativo');
                return;
            }

            if(cantidadSalida <= 0){
                toastr.error('Cantidad no debe ser negativo o cero');
                return;
            }

            if(cantidadSalida > 9000000){
                toastr.error('Cantidad de salida máximo 9 millón');
                return;
            }

            var cantiInventario = parseFloat(cantidadInventario);
            var cantiSalida = parseFloat(cantidadSalida);

            console.log('inventiario: ' + cantiInventario);
            console.log('saldran: ' + cantiSalida);

            if(cantiSalida > cantiInventario){
                toastr.error('Las unidades de Salida supera a las Disponibles');
                return;
            }


            var nFilas = $('#matriz >tbody >tr').length;
            nFilas += 1;

            var markup = "<tr>" +

                "<td>" +
                "<p id='fila" + (nFilas) + "' class='form-control' style='max-width: 65px'>" + (nFilas) + "</p>" +
                "</td>" +

                "<td>" +
                "<input name='idmaterialArray[]' type='hidden' data-idmaterialArray='" + idMaterial + "'>" +
                "<input disabled value='" + nomRepuesto + "' class='form-control' type='text'>" +
                "</td>" +

                "<td>" +
                "<input disabled value='" + cantidadInventario + "' class='form-control' type='text'>" +
                "</td>" +

                "<td>" +
                "<input name='salidaArray[]' disabled value='" + cantidadSalida + "' class='form-control' type='text'>" +
                "</td>" +

                "<td>" +
                "<button type='button' class='btn btn-block btn-danger' onclick='borrarFila(this)'>Borrar</button>" +
                "</td>" +

                "</tr>";

            $("#matriz tbody").append(markup);
            $('#modalBloque').modal('hide');

            document.getElementById('repuesto').value = '';


            Swal.fire({
                position: 'center',
                icon: 'success',
                title: 'Agregado al Detalle',
                showConfirmButton: false,
                timer: 1500
            })
        }

        function divColorRojo(pos){
            var divs = document.getElementsByClassName('arraycolor');
            $(divs[pos]).css("background-color", "red");
        }

        function preguntaGuardar(){
            colorBlancoTabla();

            Swal.fire({
                title: 'Guardar Salida?',
                text: "",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#d33',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Si'
            }).then((result) => {
                if (result.isConfirmed) {
                    guardarSalida();
                }
            })
        }

        function borrarFila(elemento){
            var tabla = elemento.parentNode.parentNode;
            tabla.parentNode.removeChild(tabla);
            setearFila()
        }

        // cambiar # de fila cada vez que se borra la fila de
        // tabla nuevo material
        function setearFila(){

            var table = document.getElementById('matriz');
            var conteo = 0;
            for (var r = 1, n = table.rows.length; r < n; r++) {
                conteo +=1;
                var element = table.rows[r].cells[0].children[0];
                document.getElementById(element.id).innerHTML = ""+conteo;
            }
        }

        function buscarMaterial(e){

            var tipoproyecto = document.getElementById('select-tipoproyecto').value;

            if(tipoproyecto == ''){
                toastr.error('Seleccionar un Proyecto');
                return;
            }

            // seguro para evitar errores de busqueda continua
            if(seguroBuscador){
                seguroBuscador = false;

                var row = $(e).closest('tr');
                txtContenedorGlobal = e;

                let texto = e.value;

                if(texto === ''){
                    // si se limpia el input, setear el atributo id
                    $(e).attr('data-info', 0);
                    document.getElementById('tablaRepuesto').innerHTML = "";
                }

                axios.post(urlAdmin+'/admin/buscar/material/porproyecto', {
                    'query' : texto,
                    'tipoproyecto' : tipoproyecto
                })
                    .then((response) => {

                        seguroBuscador = true;
                        $(row).each(function (index, element) {
                            $(this).find(".droplista").fadeIn();
                            $(this).find(".droplista").html(response.data);
                        });
                    })
                    .catch((error) => {
                        seguroBuscador = true;
                    });
            }
        }

        function guardarSalida(){

            var idProyecto = document.getElementById('select-tipoproyecto').value;

            var fecha = document.getElementById('fecha').value;
            var descripc = document.getElementById('descripcion').value; // max 800


            if(idProyecto === ''){
                toastr.error('ID proyecto es requerido');
            }

            if(fecha === ''){
                toastr.error('Fecha es requerida');
            }

            if(descripc.length > 800){
                toastr.error('Descripción máximo 800 caracteres');
                return;
            }

            var reglaNumeroDosDecimal = /^([0-9]+\.?[0-9]{0,2})$/;


            var nRegistro = $('#matriz > tbody >tr').length;
            let formData = new FormData();

            if (nRegistro <= 0){
                toastr.error('Registro Salida son requeridos');
                return;
            }

            var idmaterialDetalle = $("input[name='idmaterialArray[]']").map(function(){return $(this).attr("data-idmaterialArray");}).get();
            var salidaDetalle = $("input[name='salidaArray[]']").map(function(){return $(this).val();}).get();


            // RECORRER CADA TABLA
            for(var a = 0; a < salidaDetalle.length; a++){

                let detalleS = idmaterialDetalle[a];
                let datoCantidad = salidaDetalle[a];

                // identifica si el 0 es tipo number o texto
                if(detalleS == 0){
                    colorRojoTabla(a);
                    alertaMensaje('info', 'Error', 'En la Fila #' + (a+1) + " El identificador del repuesto no se encontró. Borrar la Fila y agregar de nuevo.");
                    return;
                }

                if (datoCantidad === '') {
                    colorRojoTabla(a);
                    toastr.error('Fila #' + (a + 1) + ' Cantidad de salida es requerida');
                    return;
                }

                if (!datoCantidad.match(reglaNumeroDosDecimal)) {
                    colorRojoTabla(a);
                    toastr.error('Fila #' + (a + 1) + ' Cantidad de salida debe ser Decimal (2 decimales) y no negativo');
                    return;
                }

                if (datoCantidad <= 0) {
                    colorRojoTabla(a);
                    toastr.error('Fila #' + (a + 1) + ' Cantidad de salida no debe ser negativo o ceros');
                    return;
                }

                if (datoCantidad > 9000000) {
                    colorRojoTabla(a);
                    toastr.error('Fila #' + (a + 1) + ' Cantidad de salida debe tener máximo 9 millones');
                    return;
                }
            }

            //*******************

            // como tienen la misma cantidad de filas, podemos recorrer
            // todas las filas de una vez
            for(var p = 0; p < salidaDetalle.length; p++){

                formData.append('salida[]', salidaDetalle[p]);
                formData.append('idmaterial[]', idmaterialDetalle[p]);
            }

            openLoading();

            formData.append('fecha', fecha);
            formData.append('descripcion', descripc);
            formData.append('idproyecto', idProyecto);

            axios.post(urlAdmin+'/admin/salida/guardar', formData, {
            })
                .then((response) => {
                    closeLoading();

                    // CANTIDAD NO ALCANZA PARA RETIRAR
                    if(response.data.success === 1){

                        let fila = response.data.fila;
                        let cantidad = response.data.cantidadactual;
                        let cantidadsalida = response.data.cantidadrestar;
                        colorRojoTabla(fila);
                        Swal.fire({
                            title: 'Cantidad no Disponible',
                            text: "Fila #" + (fila+1) + ", el repuesto cuenta con: " + cantidad + " unidades disponible, y se quiere retirar " + cantidadsalida + " Unidades",
                            icon: 'info',
                            showCancelButton: false,
                            confirmButtonColor: '#28a745',
                            confirmButtonText: 'Aceptar'
                        }).then((result) => {
                            if (result.isConfirmed) {

                            }
                        })
                    }
                    else if(response.data.success === 2){
                        // MATERIAL NO ENCONTRADO
                        let fila = response.data.fila;
                        colorRojoTabla(fila);
                        Swal.fire({
                            title: 'Repuesto no Encontrado',
                            text: "Fila #" + (fila+1) + ", el repuesto no se Encontro, por favor borrar Fila y volver a ingresarlo",
                            icon: 'info',
                            showCancelButton: false,
                            confirmButtonColor: '#28a745',
                            confirmButtonText: 'Aceptar'
                        }).then((result) => {
                            if (result.isConfirmed) {

                            }
                        })
                    } else if(response.data.success === 3){

                        // REGISTRADO CORRECTAMENTE
                        toastr.success('Salida Registrada');
                        limpiar();
                    }
                    else{
                        toastr.error('Error al guardar');
                    }
                })
                .catch((error) => {
                    toastr.error('Error al guardar');
                    closeLoading();
                });
        }

        function colorRojoTabla(index){
            $("#matriz tr:eq("+(index+1)+")").css('background', '#F1948A');
        }

        function colorBlancoTabla(){
            $("#matriz tbody tr").css('background', 'white');
        }

        function modificarValor(edrop) {

            // obtener texto del li
            let texto = $(edrop).text();
            // setear el input de la descripcion
            $(txtContenedorGlobal).val(texto);

            // OBTENER ID DEL TIPO DE PROYECTO
            var idProy = document.getElementById('select-tipoproyecto').value;


            // ABRIR MODAL SOLO PARA COLOCAR CANTIDAD A DESCARGAR

            document.getElementById("formulario-bloque").reset();

            openLoading();
            var formData = new FormData();
            formData.append('idproy', idProy);
            formData.append('idmaterial', edrop.id);

            openLoading();

            axios.post(urlAdmin+'/admin/repuesto/cantidad/bloque', formData, {
            })
                .then((response) => {
                    closeLoading();

                    if(response.data.success === 1){
                        // ABRIR MODAL

                        $('#id-modal').val(edrop.id);
                        $('#nombre-modal').val(response.data.infomaterial.nombre);
                        $('#cantidad-inventario-modal').val(response.data.cantidad);

                        $('#modalBloque').modal('show');

                    }
                    else if(response.data.success === 2){

                      // NO ENCONTRADO
                    }
                    else {
                        toastr.error('Error al buscar');
                    }
                })
                .catch((error) => {
                    toastr.error('Error al buscar');
                    closeLoading();
                });
        }

        function limpiar(){
            document.getElementById('descripcion').value = '';

            $("#matriz tbody tr").remove();
        }

        function borrarTabla(e){
            let id = $(e).val();

            if(id == ''){
                $("#matriz tbody tr").remove();
                document.querySelector('#botonaddmaterial').disabled = true;
            }else{
                document.querySelector('#botonaddmaterial').disabled = false;
            }
        }
    </script>


@endsection
