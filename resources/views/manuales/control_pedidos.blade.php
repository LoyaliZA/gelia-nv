<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }} — Manual GELIA</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10.5px;
            line-height: 1.55;
            color: #1a1a2e;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }
        .accent-bar { width: 100%; height: 5px; background: #2563eb; }
        .accent-bar-thin { height: 3px; background: #1e3a8a; }
        .cover-page { page-break-after: always; }
        .cover-body { padding: 0 48px 48px; text-align: center; }
        .cover-spacer-top { height: 100px; }
        .cover-gelia-name {
            font-size: 28px; font-weight: 900; letter-spacing: 8px;
            text-transform: uppercase; color: #1e3a8a; margin: 18px 0 6px;
        }
        .cover-gelia-sub {
            font-size: 9px; font-weight: bold; letter-spacing: 4px;
            text-transform: uppercase; color: #64748b; margin-bottom: 36px;
        }
        .cover-title-box {
            background: #eff6ff; border: 1px solid #bfdbfe; border-left: 4px solid #2563eb;
            padding: 22px 28px; margin: 0 auto 28px; max-width: 480px; text-align: center;
        }
        .cover-doc-label {
            font-size: 8px; font-weight: bold; letter-spacing: 3px;
            text-transform: uppercase; color: #94a3b8; margin-bottom: 8px;
        }
        .cover-doc-title {
            font-size: 20px; font-weight: 900; text-transform: uppercase;
            color: #1e3a8a; line-height: 1.25; margin: 0;
        }
        .cover-doc-subtitle {
            font-size: 10px; font-weight: bold; color: #2563eb;
            text-transform: uppercase; letter-spacing: 2px; margin-top: 10px;
        }
        .cover-cargos {
            font-size: 9px; color: #64748b; margin-top: 20px; text-transform: uppercase;
            letter-spacing: 1px;
        }
        .cover-footer {
            margin-top: 56px; padding-top: 16px; border-top: 1px solid #e2e8f0;
            font-size: 8px; color: #94a3b8; letter-spacing: 1px; text-transform: uppercase;
        }
        .content-page { padding: 24px 36px 32px; }
        .section-title {
            font-size: 11px; font-weight: 900; text-transform: uppercase; letter-spacing: 1.5px;
            color: #1e3a8a; padding: 8px 12px; margin: 22px 0 10px;
            background: #eff6ff; border-left: 3px solid #2563eb;
        }
        .subsection-title {
            font-size: 10px; font-weight: 900; text-transform: uppercase;
            letter-spacing: 1px; color: #334155; margin: 16px 0 8px;
        }
        p { margin: 0 0 10px; }
        ul, ol { margin: 0 0 12px; padding-left: 18px; }
        li { margin-bottom: 4px; }
        table.data { width: 100%; border-collapse: collapse; margin: 10px 0 16px; font-size: 10px; }
        table.data th, table.data td {
            padding: 7px 10px; border: 1px solid #e2e8f0; text-align: left; vertical-align: top;
        }
        table.data th { background: #f8fafc; color: #475569; font-weight: bold; }
        .flujo-step {
            border: 1px solid #e2e8f0; border-left: 3px solid #2563eb;
            padding: 8px 12px; margin-bottom: 6px; background: #f8fafc;
        }
        .flujo-fase { font-weight: 900; color: #1e3a8a; font-size: 9px; letter-spacing: 1px; text-transform: uppercase; }
        .rama {
            background: #fffbeb; border: 1px solid #fde68a; border-left: 4px solid #f59e0b;
            padding: 8px 12px; margin: 8px 0; font-size: 10px;
        }
        .ejemplo {
            background: #f0f9ff; border: 1px solid #bae6fd; border-left: 4px solid #2563eb;
            padding: 10px 14px; margin: 12px 0; font-size: 10px;
        }
        .page-break { page-break-before: always; }
        .footer-note {
            position: fixed; bottom: 18px; left: 36px; right: 36px;
            font-size: 8px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;
            border-top: 1px solid #e2e8f0; padding-top: 6px;
        }
    </style>
</head>
<body>
    <div class="cover-page">
        <div class="accent-bar"></div>
        <div class="accent-bar-thin"></div>
        <div class="cover-body">
            <div class="cover-spacer-top"></div>
            <div class="cover-gelia-name">GELIA</div>
            <div class="cover-gelia-sub">Manuales operativos</div>
            <div class="cover-title-box">
                <div class="cover-doc-label">Documento de apoyo</div>
                <h1 class="cover-doc-title">{{ $titulo }}</h1>
                <div class="cover-doc-subtitle">{{ $modulo }}</div>
            </div>
            <p class="cover-cargos">
                Capítulos incluidos:
                @if(count($cargos))
                    {{ implode(' · ', $cargos) }}
                @else
                    Solo resumen general
                @endif
            </p>
            <div class="cover-footer">
                Generado {{ $fecha }} · Contenido filtrado por permisos del usuario · Solo marca GELIA
            </div>
        </div>
    </div>

    <div class="content-page">
        <div class="footer-note">GELIA · Manuales · {{ $titulo }}</div>

        @php $c = $contenido; @endphp

        <div class="section-title">{{ $c['overview']['titulo'] ?? 'Resumen' }}</div>
        @foreach(($c['overview']['parrafos'] ?? []) as $p)
            <p>{{ $p }}</p>
        @endforeach

        <div class="subsection-title">Escritorios</div>
        <table class="data">
            <thead>
                <tr><th>Cargo</th><th>Pantalla</th><th>Ruta</th></tr>
            </thead>
            <tbody>
                @foreach(($c['overview']['escritorios'] ?? []) as $e)
                    <tr>
                        <td>{{ $e['cargo'] }}</td>
                        <td>{{ $e['nombre'] }}</td>
                        <td>{{ $e['ruta'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="section-title">{{ $c['flujo']['titulo'] ?? 'Flujo' }}</div>
        <div class="subsection-title">Camino feliz</div>
        @foreach(($c['flujo']['camino_feliz'] ?? []) as $paso)
            <div class="flujo-step">
                <div class="flujo-fase">{{ $paso['fase'] }}</div>
                <div><strong>{{ $paso['quien'] }}:</strong> {{ $paso['accion'] }}</div>
            </div>
        @endforeach

        <div class="subsection-title">Ramas</div>
        @foreach(($c['flujo']['ramas'] ?? []) as $rama)
            <div class="rama">
                <strong>{{ $rama['nombre'] }}.</strong> {{ $rama['detalle'] }}
            </div>
        @endforeach

        @foreach(($c['secciones'] ?? []) as $sec)
            <div class="page-break"></div>
            <div class="section-title">{{ $sec['cargo'] }} — {{ $sec['titulo'] }}</div>
            <p><em>{{ $sec['ruta'] ?? '' }}</em></p>
            <p>{{ $sec['resumen'] ?? '' }}</p>

            <div class="subsection-title">Pasos</div>
            <ol>
                @foreach(($sec['pasos'] ?? []) as $paso)
                    <li><strong>{{ $paso['titulo'] }}.</strong> {{ $paso['detalle'] ?? '' }}</li>
                @endforeach
            </ol>

            @if(!empty($sec['elementos']))
                <div class="subsection-title">Elementos</div>
                <table class="data">
                    <thead><tr><th>Elemento</th><th>Uso</th></tr></thead>
                    <tbody>
                        @foreach($sec['elementos'] as $el)
                            <tr>
                                <td>{{ $el['nombre'] }}</td>
                                <td>{{ $el['uso'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if(!empty($sec['errores_que_te_llegan']))
                <div class="subsection-title">Errores / señales</div>
                <ul>
                    @foreach($sec['errores_que_te_llegan'] as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            @endif
        @endforeach

        @if(!empty($c['flujo']['reaperturas']))
            <div class="section-title">Matriz de reapertura</div>
            <table class="data">
                <thead><tr><th>Desde</th><th>Hacia</th><th>Permiso</th><th>Nota</th></tr></thead>
                <tbody>
                    @foreach($c['flujo']['reaperturas'] as $r)
                        <tr>
                            <td>{{ $r['desde'] }}</td>
                            <td>{{ $r['hacia'] }}</td>
                            <td>{{ $r['permiso'] }}</td>
                            <td>{{ $r['nota'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="section-title">Catálogo de estatus</div>
        <table class="data">
            <thead><tr><th>Fase</th><th>Etiqueta</th><th>Nota</th></tr></thead>
            <tbody>
                @foreach(($c['estatus'] ?? []) as $est)
                    <tr>
                        <td>{{ $est['fase'] }}</td>
                        <td>{{ $est['etiqueta'] }}</td>
                        <td>{{ $est['nota'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if(!empty($c['errores']))
            <div class="section-title">Alertas relevantes</div>
            <table class="data">
                <thead><tr><th>Tipo</th><th>Cuándo</th></tr></thead>
                <tbody>
                    @foreach($c['errores'] as $err)
                        <tr>
                            <td>{{ $err['tipo'] }}</td>
                            <td>{{ $err['cuando'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if(!empty($c['ejemplos']))
            <div class="section-title">Ejemplos</div>
            @foreach($c['ejemplos'] as $ex)
                <div class="ejemplo">
                    <strong>{{ $ex['titulo'] }}.</strong> {{ $ex['detalle'] }}
                </div>
            @endforeach
        @endif
    </div>
</body>
</html>
