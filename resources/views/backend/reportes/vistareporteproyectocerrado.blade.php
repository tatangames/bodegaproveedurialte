@extends('adminlte::page')
@section('title', 'Informe de Inventario Físico de Materiales Sobrantes')
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
            background: linear-gradient(135deg, #3d1f6b, #6f42c1);
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
            background: linear-gradient(135deg, #3d1f6b, #6f42c1);
            color: #fff;
            box-shadow: 0 4px 14px rgba(111,66,193,.35);
        }
        .btn-pdf:hover { transform: translateY(-1px); filter: brightness(1.08); color: #fff; }
        .divider { border: none; border-top: 2px dashed #e8eef8; margin: 10px 0 20px 0; }
    </style>

    <div id="divcontenedor" style="display:none">
        <section class="content">
            <div class="container-fluid">
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="reporte-card">
                            <div class="reporte-header">
                                <i class="fas fa-flag-checkered"></i>
                                <h5>Informe de Inventario Físico de Materiales Sobrantes — GEAD-001-INFO</h5>
                            </div>
                            <div class="reporte-body">

                                <hr class="divider">

                                {{-- No. de Proyecto --}}
                                <div class="form-group">
                                    <label class="field-label">
                                        <i class="fas fa-hashtag mr-1"></i>No. de Proyecto
                                    </label>
                                    <input type="text" class="form-control" id="inp-noproyecto"
                                           placeholder="Ej: 001-2025">
                                </div>

                                {{-- Proyecto cerrado --}}
                                <div class="form-group">
                                    <label class="field-label">
                                        <i class="fas fa-lock mr-1"></i>Nombre del Proyecto
                                    </label>
                                    <select class="form-control" id="sel-proyecto">
                                        @foreach($proyectosCerrados as $p)
                                            <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Acuerdo de aprobación --}}
                                <div class="form-group">
                                    <label class="field-label">
                                        <i class="fas fa-file-alt mr-1"></i>Acuerdo de Aprobación del Proyecto
                                    </label>
                                    <input type="text" class="form-control" id="inp-acuerdo"
                                           placeholder="Ej: Acuerdo No. 123-2025">
                                </div>

                                {{-- Unidad solicitante (Departamento) --}}
                                <div class="form-group">
                                    <label class="field-label">
                                        <i class="fas fa-building mr-1"></i>Unidad Solicitante
                                    </label>
                                    <select class="form-control" id="sel-departamento">
                                        @foreach($departamentos as $d)
                                            <option value="{{ $d->id }}">{{ $d->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Jefe o encargado --}}
                                <div class="form-group">
                                    <label class="field-label">
                                        <i class="fas fa-user-tie mr-1"></i>Jefe o Encargado de Unidad Solicitante
                                    </label>
                                    <input type="text" class="form-control" id="inp-jefe"
                                           placeholder="Nombre completo">
                                </div>

                                {{-- Justificación --}}
                                <div class="form-group">
                                    <label class="field-label">
                                        <i class="fas fa-comment-alt mr-1"></i>Justificación del Sobrante
                                    </label>
                                    <textarea class="form-control" id="inp-justificacion"
                                              rows="3"
                                              placeholder="Describa el motivo por el que quedaron materiales sobrantes..."></textarea>
                                </div>

                                {{-- Observaciones --}}
                                <div class="form-group">
                                    <label class="field-label">
                                        <i class="fas fa-sticky-note mr-1"></i>Observaciones
                                    </label>
                                    <textarea class="form-control" id="inp-observaciones"
                                              rows="2"
                                              placeholder="Observaciones adicionales (opcional)"></textarea>
                                </div>

                                <button type="button" onclick="generarPDF()" class="btn-pdf">
                                    <img src="{{ asset('images/logopdf.png') }}" width="22px" height="22px">
                                    Generar PDF — GEAD-001-INFO
                                </button>

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
    <script>
        $(document).ready(function () {
            document.getElementById("divcontenedor").style.display = "block";

            $('#sel-proyecto').select2({
                theme: "bootstrap-5",
                language: { noResults: function () { return "No encontrado"; } }
            });
            $('#sel-departamento').select2({
                theme: "bootstrap-5",
                language: { noResults: function () { return "No encontrado"; } }
            });
        });

        function generarPDF() {
            var idproy        = $('#sel-proyecto').val();
            var noproyecto    = $('#inp-noproyecto').val().trim();
            var acuerdo       = $('#inp-acuerdo').val().trim();
            var idDepto       = $('#sel-departamento').val();
            var jefe          = $('#inp-jefe').val().trim();
            var justificacion = $('#inp-justificacion').val().trim();
            var observaciones = $('#inp-observaciones').val().trim();

            if (!idproy) { toastr.error('Seleccione un proyecto'); return; }

            var url = "{{ URL::to('admin/reporte/proyectos/cerrado/pdf') }}"
                + '/' + idproy
                + '/' + encodeURIComponent(noproyecto    || ' ')
                + '/' + encodeURIComponent(acuerdo       || ' ')
                + '/' + (idDepto || '0')
                + '/' + encodeURIComponent(jefe          || ' ')
                + '/' + encodeURIComponent(justificacion || ' ')
                + '/' + encodeURIComponent(observaciones || ' ');

            window.open(url);
        }
    </script>
@endsection
