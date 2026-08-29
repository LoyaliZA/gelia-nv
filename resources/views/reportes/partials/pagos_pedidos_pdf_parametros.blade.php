{{-- Bloque compacto de filtros aplicados al exportar. --}}
<div class="params-block">
    <p class="params-title">Parámetros aplicados</p>
    <table class="params-table">
        <thead>
            <tr>
                <th>Parámetro</th>
                <th>Selección</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($parametros_aplicados ?? [] as $fila)
                <tr>
                    <td class="params-key">{{ $fila['parametro'] }}</td>
                    <td class="params-val">{{ $fila['seleccion'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
