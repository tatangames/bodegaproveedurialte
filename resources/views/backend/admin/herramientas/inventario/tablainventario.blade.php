<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <table id="tabla" class="table table-bordered table-striped">
                            <thead>
                            <tr>
                                <th style="width: 12%">Código</th>
                                <th style="width: 20%">Nombre</th>
                                <th style="width: 10%">Medida</th>
                                <th style="width: 10%">Cantidad</th>
                                <th style="width: 10%">Opciones</th>
                            </tr>
                            </thead>
                            <tbody>

                            @foreach($lista as $dato)
                                <tr>
                                    <td>{{ $dato->codigo }}</td>
                                    <td>{{ $dato->nombre }}</td>
                                    <td>{{ $dato->medida }}</td>
                                    <td>{{ $dato->cantidad }}</td>
                                    <td>

                                        <button type="button" class="btn btn-primary btn-xs" onclick="informacion({{ $dato->id }})">
                                            <i class="fas fa-edit" title="Editar"></i>&nbsp; Editar
                                        </button>

                                        <button type="button" class="btn btn-danger btn-xs" onclick="infoModalDescartar({{ $dato->id }})">
                                            <i class="fas fa-edit" title="Descartar"></i>&nbsp; Descartar
                                        </button>

                                    </td>
                                </tr>
                            @endforeach

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

