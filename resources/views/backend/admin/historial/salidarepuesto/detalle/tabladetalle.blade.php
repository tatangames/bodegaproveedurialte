<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <table id="tabla" class="table table-bordered table-striped">
                            <thead>
                            <tr>
                                <th style="width: 8%">Código</th>
                                <th style="width: 20%">Repuesto</th>
                                <th style="width: 4%">Cantidad</th>
                            </tr>
                            </thead>
                            <tbody>

                            @foreach($lista as $dato)
                                <tr>
                                    <td>{{ $dato->codmaterial }}</td>
                                    <td>{{ $dato->nommaterial }}</td>
                                    <td>{{ $dato->cantidad }}</td>

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

