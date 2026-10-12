<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }} · GELIA</title>
    <style>
        @page { margin: 18mm 16mm 20mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #202b38; font-size: 9.5pt; line-height: 1.5; }
        h1 { font-size: 25pt; line-height: 1.2; margin: 12px 0; }
        h2 { font-size: 16pt; line-height: 1.3; margin: 22px 0 12px; page-break-after: avoid; }
        h3 { font-size: 11pt; margin: 16px 0 8px; page-break-after: avoid; }
        p { margin: 0 0 10px; }
        ul { margin: 8px 0 14px; padding-left: 20px; }
        li { margin-bottom: 7px; }
        .marca { font-size: 11pt; font-weight: bold; color: #245a7c; border-bottom: 2px solid #245a7c; padding-bottom: 8px; }
        .meta, .nota { font-size: 8pt; color: #4b5966; }
        .aviso { border-left: 3px solid #245a7c; padding: 10px 14px; margin: 14px 0; background: #f3f6f8; }
        .paso, .capitulo { page-break-before: always; }
        .resultado { border-left: 2px solid #8a9aa8; padding-left: 12px; margin: 16px 0; }
        .visual { border: 1px solid #acb8c2; padding: 12px 16px; margin: 16px 0; page-break-inside: avoid; }
        .visual h3 { margin-top: 0; }
        .campo { margin: 0 0 6px; }
        .etiqueta { font-size: 8pt; color: #4b5966; }
        .valor { padding: 5px 8px; border: 1px solid #ccd4db; background: #f8f9fa; margin-top: 2px; }
        .accion { display: inline-block; border: 1px solid #8a9aa8; padding: 6px 10px; font-weight: bold; margin: 8px 0; }
        .caso { margin-bottom: 20px; border-bottom: 1px solid #ccd4db; padding-bottom: 12px; page-break-inside: avoid; }
        .caso h3 { page-break-after: avoid; }
        .problema, .estado, .palabra { margin-bottom: 16px; page-break-inside: avoid; }
        .problema { margin-bottom: 10px; }
        .problema h3 { margin-top: 10px; }
        .indice { margin-bottom: 22px; }
        a { color: #245a7c; text-decoration: none; }
        .pie { position: fixed; bottom: -35px; left: 0; right: 0; border-top: 1px solid #ccd4db; padding-top: 6px; font-size: 7pt; color: #4b5966; }
    </style>
</head>
<body>
    <div class="pie">GELIA · Registrar pedidos (Vendedor) · Versión {{ $contenido['version'] }} · Revisión {{ $contenido['actualizado'] }}</div>
    <div class="marca" id="sec-overview">GELIA · Manual de uso para Ventas</div>
    <h1>{{ $titulo }}</h1>
    <p class="meta">Versión {{ $contenido['version'] }} · Contenido revisado el {{ $contenido['actualizado'] }}<br>Descargado el {{ $fecha }}</p>
    <p>{{ $contenido['intro'] }}</p>
    <p>{{ $contenido['alcance'] }}</p>
    <div class="aviso"><strong>Tres acciones que conviene distinguir</strong><p>{{ $contenido['regla'] }}</p></div>
    <p class="nota">{{ $contenido['visuales_nota'] }}</p>
    <h2>Contenido</h2>
    <ul class="indice">
        @foreach ($contenido['indice'] as $item)
            <li><a href="#sec-{{ $item['id'] }}">{{ $item['label'] }}</a></li>
        @endforeach
    </ul>

    <h2 class="capitulo" id="sec-preparacion">Qué tener a la mano</h2>
    <ul>@foreach ($contenido['preparacion'] as $item)<li>{{ $item }}</li>@endforeach</ul>

    @foreach ($contenido['pasos'] as $i => $paso)
        <div class="{{ $i === 0 ? 'primer-paso' : 'paso' }}" @if ($i === 0) id="sec-pasos" @endif>
            <p class="meta">Paso a paso · {{ $i + 1 }} de {{ count($contenido['pasos']) }}</p>
            <h2>{{ $i + 1 }}. {{ $paso['titulo'] }}</h2>
            <p class="nota">{{ $paso['donde'] }}</p>
            <ul>@foreach ($paso['acciones'] as $accion)<li>{{ $accion }}</li>@endforeach</ul>
            <div class="resultado"><strong>Qué debe pasar</strong><p>{{ $paso['resultado'] }}</p></div>
            <div class="visual">
                <h3>Ejemplo visual · {{ $paso['visual']['titulo'] }}</h3>
                @foreach ($paso['visual']['campos'] as [$label, $value])
                    <div class="campo"><div class="etiqueta">{{ $label }}</div><div class="valor">{{ $value }}</div></div>
                @endforeach
                <div class="accion">{{ $paso['visual']['accion'] }}</div>
                <p class="nota">{{ $paso['visual']['nota'] }}</p>
                <p class="nota">Ilustración de lectura · datos ficticios · sin acciones.</p>
            </div>
        </div>
    @endforeach

    <div class="capitulo" id="sec-casos">
        <h2>Casos de uso: elige tu situación</h2>
        @foreach ($contenido['casos'] as $caso)
            <div class="caso">
                <h3>{{ $caso['titulo'] }}</h3>
                <p>{{ $caso['ejemplo'] }}</p>
                <p><strong>Qué haces:</strong> {{ $caso['hacer'] }}</p>
                <p><strong>Qué respuesta esperas:</strong> {{ $caso['esperar'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="capitulo" id="sec-respuestas">
        <h2>Qué esperar de las otras áreas</h2>
        <p>Cada área trabaja sobre el mismo pedido. Comparte el folio y mantén datos y archivos vigentes para que la siguiente persona pueda continuar.</p>
        <p>Estos son los puntos de conexión con los próximos manuales. Los tiempos dependen de la operación y de los pendientes de cada pedido.</p>
        @foreach ($contenido['respuestas'] as $respuesta)
            <div class="caso">
                <h3>{{ $respuesta['area'] }}</h3>
                <p><strong>Tú registras:</strong> {{ $respuesta['envias'] }}</p>
                <p><strong>El área responde:</strong> {{ $respuesta['recibes'] }}</p>
                <p><strong>Tu siguiente paso:</strong> {{ $respuesta['sigues'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="capitulo" id="sec-estatus">
        <h2>Cómo saber en qué va mi pedido</h2>
        <p>Revisa el estado y los avisos en el listado o en Ver. La Bitácora explica quién registró los cambios.</p>
        @foreach ($contenido['estatus'] as $estado)
            <div class="estado"><h3>{{ $estado['nombre'] }}</h3><p>{{ $estado['significado'] }}</p><p><strong>Qué haces:</strong> {{ $estado['accion'] }}</p></div>
        @endforeach
    </div>

    <div class="capitulo" id="sec-problemas">
        <h2>Si algo se detiene</h2>
        @foreach ($contenido['problemas'] as $problema)
            <div class="problema"><h3>{{ $problema['situacion'] }}</h3><p>{{ $problema['hacer'] }}</p></div>
        @endforeach
    </div>

    <div class="capitulo" id="sec-checklist">
        <h2>Antes de enviar: una última revisión</h2>
        <ul>@foreach ($contenido['checklist'] as $item)<li>{{ $item }}</li>@endforeach</ul>
        <h2>Recomendaciones para el día a día</h2>
        <ul>@foreach ($contenido['recomendaciones'] as $item)<li>{{ $item }}</li>@endforeach</ul>
    </div>

    <div class="capitulo" id="sec-glosario">
        <h2>Palabras que verás en pantalla</h2>
        @foreach ($contenido['glosario'] as $item)
            <div class="palabra"><strong>{{ $item['palabra'] }}</strong><p>{{ $item['significado'] }}</p></div>
        @endforeach
    </div>
</body>
</html>
