@extends('adminlte::page')

@section('title', 'Cierre de Proyectos')

@section('content_header')
    <h1>Cierre de Proyectos</h1>
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
                                    <label>Fecha de Transferencia: <span style="color: red">*</span></label>
                                    <input style="width: 25%; margin-left: 25px;" type="date" class="form-control" id="fecha">
                                </div>
                            </div>

                            <div style="margin-left: 15px; margin-right: 15px; margin-top: 15px;">
                                <div class="form-group">
                                    <label>Asignar Proyecto: <span style="color: red">*</span></label>
                                    <select id="select-tipoproyecto" class="form-control">
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


                            <!-- SI LO PIDEN LO VOY HABILITAR 23/05/2026 -->
                            <div style="margin-left: 15px; margin-right: 15px; margin-top: 25px; display: none">
                                <label>Documento Acta de Cierre (opcional)</label>
                                <input type="file" id="documento" class="form-control" accept="image/jpeg, image/jpg, image/png, .pdf"/>
                            </div>

                            <div class="form-group" style="float: right">
                                <br>
                                <button type="button" onclick="guardarTransferencia()" class="btn btn-primary btn-sm float-right" style="margin-top:10px; margin-right: 15px;">
                                    <i class="fas fa-edit" title="Guardar Transferencia"></i> Guardar Transferencia</button>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>


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

            $('#select-tipoproyecto').select2({
                theme: "bootstrap-5",
                "language": {
                    "noResults": function(){
                        return "Busqueda no encontrada";
                    }
                },
            });
        });
    </script>

    <script>

           function preguntaGuardar(){
            colorBlancoTabla();

            Swal.fire({
                title: 'Guardar Transferencia?',
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


           function guardarTransferencia(){

               var fecha      = document.getElementById('fecha').value;
               var descripc   = document.getElementById('descripcion').value;
               var idproyecto = document.getElementById('select-tipoproyecto').value;
               var documento  = document.getElementById('documento');

               if(fecha === ''){
                   toastr.error('Fecha es requerida');
                   return;
               }

               if(descripc.length > 800){
                   toastr.error('Descripción máximo 800 caracteres');
                   return;
               }

               if(idproyecto === ''){
                   toastr.error('Proyecto es requerido');
                   return;
               }

               if(documento.files && documento.files[0]){
                   if (!documento.files[0].type.match('image/jpeg|image/png|application/pdf')){
                       toastr.error('Formatos permitidos: .png .jpg .jpeg .pdf');
                       return;
                   }
               }

               Swal.fire({
                   title: '¿Cerrar Proyecto?',
                   text: "El proyecto quedará marcado como cerrado.",
                   icon: 'question',
                   showCancelButton: true,
                   confirmButtonColor: '#28a745',
                   cancelButtonColor: '#d33',
                   cancelButtonText: 'Cancelar',
                   confirmButtonText: 'Sí, cerrar'
               }).then((result) => {
                   if (result.isConfirmed) {

                       openLoading();

                       let formData = new FormData();
                       formData.append('fecha',       fecha);
                       formData.append('descripcion', descripc);
                       formData.append('idproyecto',  idproyecto);
                       if(documento.files && documento.files[0]){
                           formData.append('documento', documento.files[0]);
                       }

                       axios.post(urlAdmin+'/admin/generar/salida/transferencia', formData)
                           .then((response) => {
                               closeLoading();

                               if(response.data.success === 1){
                                   Swal.fire({
                                       title: 'No Guardado',
                                       text: "Este proyecto ya fue cerrado anteriormente.",
                                       icon: 'info',
                                       confirmButtonColor: '#28a745',
                                       confirmButtonText: 'Aceptar'
                                   });
                               }
                               else if(response.data.success === 3){
                                   Swal.fire({
                                       title: 'Cierre Exitoso',
                                       text: "El proyecto ha sido cerrado correctamente.",
                                       icon: 'success',
                                       confirmButtonColor: '#28a745',
                                       confirmButtonText: 'Aceptar',
                                       allowOutsideClick: false,
                                   }).then((result) => {
                                       if (result.isConfirmed) {
                                           window.location.reload();
                                       }
                                   });
                               }
                               else{
                                   toastr.error('Error al guardar');
                               }
                           })
                           .catch(() => {
                               toastr.error('Error al guardar');
                               closeLoading();
                           });
                   }
               });
           }


    </script>


@endsection
