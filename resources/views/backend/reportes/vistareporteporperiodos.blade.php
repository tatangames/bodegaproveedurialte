{{-- resources/views/backend/reportes/porperiodos.blade.php --}}
@extends('adminlte::page')
@section('title', 'Reporte de Movimientos por Proyecto y Período')
@section('plugins.Sweetalert2', true)
@include('backend.urlglobal')

@section('content_top_nav_right')
    <link href="{{ asset('css/toastr.min.css') }}" type="text/css" rel="stylesheet" />
    <link href="{{ asset('css/select2.min.css') }}" type="text/css" rel="stylesheet">
    <link href="{{ asset('css/select2-bootstrap-5-theme.min.css') }}" type="text/css" rel="stylesheet">
    <li class="nav-item dropdown">
        <a href="#" class="nav-link" data-toggle="dropdown">
            <i class="fas fa-cogs"></i>
            <span class="d-none d-md-inline">{{ Auth::guard('admin')->user()->nombre }}</span>
        </a>
        <div class="dropdown-menu dropdown-menu-right">
            <a href="{{ route('admin.perfil') }}" class="dropdown-item">
                <i class="fas fa-user mr-2"></i>Editar Perfil
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
        *:focus { outline: none; }
        .reporte-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 18px rgba(0,0,0,.10);
            margin-bottom: 24px;
            overflow: hidden;
        }
        .reporte-header {
            padding: 14px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            background: linear-gradient(135deg, #0b3d2e, #1f9e6f);
        }
        .reporte-header i { font-size: 22px; color: #fff; }
        .reporte-header h5 {
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            margin: 0;
        }
        .reporte-body { padding: 22px 24px; background: #fff; }
        .field-label {
            font-size: 11px;
            font-weight: 700;
            color: #6b7a99;
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: 6px;
            display: block;
        }
        .btn-pdf {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            border: none;
            cursor: pointer;
            transition: all .2s;
            margin-top: 14px;
            background: linear-gradient(135deg, #0b3d2e, #1f9e6f);
            color: #fff;
            box-shadow: 0 4px 14px rgba(31,158,111,.35);
        }
        .btn-pdf:hover { transform: translateY(-1px); filter: brightness(1.08); color: #fff; }
        .divider { border: none; border-top: 2px dashed #e8eef8; margin: 10px 0 20px 0; }
    </style>

    {{-- Formulario oculto para POST --}}
    <form id="form-pdf"
          action="{{ URL::to('admin/reporte/proyectos/periodos/pdf') }}"
          method="POST"
          target="_blank">
        @csrf
        <input type="hidden" name="idproy" id="h-idproy">
        <input type="hidden" name="desde"  id="h-desde">
        <input type="hidden" name="hasta"  id="h-hasta">
    </form>

    <div id="divcontenedor">
        <section class="content">
            <div class="container-fluid">
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="reporte-card">
                            <div class="reporte-header">
                                <i class="fas fa-exchange-alt"></i>
                                <h5>Reporte de Movimientos por Proyecto y Período</h5>
                            </div>
                            <div class="reporte-body">

                                <hr class="divider">

                                <div class="form-group">
                                    <label class="field-label">
                                        <i class="fas fa-project-diagram mr-1"></i>Proyecto
                                    </label>
                                    <select class="form-control" id="sel-proyecto">
                                        @foreach($proyectos as $p)
                                            <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="field-label">
                                                <i class="fas fa-calendar-alt mr-1"></i>Fecha Desde
                                            </label>
                                            <input type="date" class="form-control" id="inp-desde">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="field-label">
                                                <i class="fas fa-calendar-check mr-1"></i>Fecha Hasta
                                            </label>
                                            <input type="date" class="form-control" id="inp-hasta">
                                        </div>
                                    </div>
                                </div>

                                <button type="button" onclick="generarPDF()" class="btn-pdf">
                                    <img src="{{ asset('images/logopdf.png') }}" width="22px" height="22px">
                                    Generar PDF
                                </button>


                                <div class="card mt-4">
                                    <div class="card-header bg-primary">
                                        <h5 class="mb-0">Firmas del Reporte</h5>
                                    </div>

                                    <div class="card-body">

                                        <div class="row">

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label>1 - Encargado de bodega de proyecto:</label>
                                                    <input type="text"
                                                           id="p_nombre1"
                                                           class="form-control"
                                                           maxlength="200"
                                                           value="{{ $infoGeneral->p_nombre1 ?? '' }}"
                                                           placeholder="Ingrese nombre">
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label>2 - Jefe Inmediato:</label>
                                                    <input type="text"
                                                           id="p_nombre2"
                                                           class="form-control"
                                                           maxlength="200"
                                                           value="{{ $infoGeneral->p_nombre2 ?? '' }}"
                                                           placeholder="Ingrese nombre">
                                                </div>
                                            </div>

                                        </div>

                                        <div class="text-right">
                                            <button class="btn btn-primary"
                                                    onclick="actualizarFirmasReporte()">
                                                Guardar
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
    </div>
@stop

@section('js')
    <script src="{{ asset('js/toastr.min.js') }}"></script>
    <script src="{{ asset('js/select2.min.js') }}"></script>
    <script src="{{ asset('js/axios.min.js') }}"></script>
    <script src="{{ asset('js/sweetalert2.all.min.js') }}"></script>
    <script src="{{ asset('js/alertaPersonalizada.js') }}"></script>

    <script>
        $(document).ready(function () {
            $('#sel-proyecto').select2({
                theme: "bootstrap-5",
                language: { noResults: function () { return "No encontrado"; } }
            });
        });

        function generarPDF() {
            var idproy = $('#sel-proyecto').val();
            var desde  = $('#inp-desde').val();
            var hasta  = $('#inp-hasta').val();

            if (!idproy) { toastr.error('Seleccione un proyecto'); return; }
            if (!desde)  { toastr.error('Seleccione la fecha "Desde"'); return; }
            if (!hasta)  { toastr.error('Seleccione la fecha "Hasta"'); return; }
            if (desde > hasta) {
                toastr.error('La fecha "Desde" no puede ser mayor que "Hasta"');
                return;
            }

            $('#h-idproy').val(idproy);
            $('#h-desde').val(desde);
            $('#h-hasta').val(hasta);

            $('#form-pdf').submit();
        }

        function actualizarFirmasReporte() {

            openLoading();

            axios.post(urlAdmin + '/admin/firmas/proyectos/periodos/actualizar', {
                p_nombre1: document.getElementById('p_nombre1').value.trim(),
                p_nombre2: document.getElementById('p_nombre2').value.trim(),
            })
                .then((response) => {

                    closeLoading();

                    switch (response.data.success) {

                        case 1:
                            toastr.success('Firmas actualizadas correctamente');
                            break;

                        case 0:
                            toastr.error('No se encontró la información');
                            break;

                        case 99:
                            toastr.error('Ocurrió un error al actualizar');
                            break;

                        default:
                            toastr.error('Error al actualizar');
                    }
                })
                .catch(() => {
                    closeLoading();
                    toastr.error('Error al actualizar');
                });
        }
    </script>
@endsection
