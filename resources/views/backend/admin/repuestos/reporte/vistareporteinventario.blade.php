@extends('backend.menus.superior')

@section('content-admin-css')
    <link href="{{ asset('css/adminlte.min.css') }}" type="text/css" rel="stylesheet" />
    <link href="{{ asset('css/dataTables.bootstrap4.css') }}" type="text/css" rel="stylesheet" />
    <link href="{{ asset('css/toastr.min.css') }}" type="text/css" rel="stylesheet" />
    <link href="{{ asset('css/estiloToggle.css') }}" type="text/css" rel="stylesheet" />
    <link href="{{ asset('css/main.css') }}" type="text/css" rel="stylesheet" />
    <link href="{{ asset('css/select2.min.css') }}" type="text/css" rel="stylesheet">
    <link href="{{ asset('css/select2-bootstrap-5-theme.min.css') }}" type="text/css" rel="stylesheet">
@stop


<div class="content-wrapper" id="divcc" style="display: none">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">

        </div>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="callout callout-info">
                        <h5><i class="fas fa-info"></i> Generar Inventario</h5>
                        <div class="card">
                            <form class="form-horizontal">
                                <div class="card-body">



                                    <div class="form-group">
                                        <label style="color:#191818">Tipo de Reporte</label>
                                        <br>
                                        <div>
                                            <select class="form-control" id="select-tipo" style="max-width: 15%">
                                                <option value="1">Juntos</option>
                                                <option value="2">Separado</option>
                                            </select>
                                        </div>
                                    </div>


                                    <button type="button" onclick="generarPdf()" class="btn" style="margin-left: 30px; border-color: black; border-radius: 0.1px;">
                                        <img src="{{ asset('images/logopdf.png') }}" width="55px" height="55px">
                                        Generar PDF
                                    </button>


                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>


</div>


@extends('backend.menus.footerjs')
@section('archivos-js')

    <script src="{{ asset('js/jquery.dataTables.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/dataTables.bootstrap4.js') }}" type="text/javascript"></script>

    <script src="{{ asset('js/toastr.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/axios.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/sweetalert2.all.min.js') }}"></script>
    <script src="{{ asset('js/alertaPersonalizada.js') }}"></script>
    <script src="{{ asset('js/jquery.simpleaccordion.js') }}"></script>
    <script src="{{ asset('js/select2.min.js') }}" type="text/javascript"></script>

    <script>
        $(document).ready(function() {
            document.getElementById("divcc").style.display = "block";
        });

        $('#select-equipo').select2({
            theme: "bootstrap-5",
            "language": {
                "noResults": function(){
                    return "Búsqueda no encontrada";
                }
            },
        });

    </script>

    <script>

        function generarPdf() {


            var tipo = document.getElementById('select-tipo').value;

            if(tipo === ''){
                toastr.error('Seleccionar Tipo');
                return;
            }

            window.open("{{ URL::to('admin/reporte/inventario/pdf') }}/" + tipo);
        }

    </script>


@endsection
