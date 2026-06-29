<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; line-height: 1.5; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        h2 { font-size: 14px; margin-top: 20px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
        h3 { font-size: 12px; margin-top: 14px; }
        code, pre { background: #f4f4f4; padding: 2px 4px; font-size: 10px; }
        pre { padding: 10px; white-space: pre-wrap; word-wrap: break-word; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; font-size: 10px; }
        th { background: #f0f0f0; }
        .muted { color: #666; font-size: 10px; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; background: #eee; font-size: 9px; }
    </style>
</head>
<body>
    <h1>{{ $titulo }}</h1>
    <p class="muted">Generado: {{ $fecha }}</p>
    <p><strong>URL base:</strong> <code>{{ $base_url }}</code></p>

    <h2>1. Autenticación</h2>
    <p>Obtenga un token Bearer enviando sus credenciales. El <code>client_secret</code> se guarda cifrado (hash); use el valor en texto plano mostrado al crear o regenerar la aplicación.</p>
    <p>Deje <strong>IPs permitidas</strong> vacías en el panel para permitir cualquier IP (acceso abierto por IP).</p>
    <pre>POST {{ $base_url }}/auth/token
Content-Type: application/json
Accept: application/json

{
  "client_id": "SU_CLIENT_ID",
  "client_secret": "SU_CLIENT_SECRET"
}</pre>
    <p>Respuesta:</p>
    <pre>{
  "access_token": "...",
  "token_type": "Bearer",
  "expires_in": 86400
}</pre>
    <p>Use el token en todas las peticiones protegidas:</p>
    <pre>Authorization: Bearer {access_token}
Accept: application/json</pre>

    <h2>2. Verificación (sin autenticación)</h2>
    <pre>GET {{ $base_url }}/health</pre>
    <p>Respuesta esperada: <code>{"status":"ok","version":"v1",...}</code></p>

    <h2>3. Probar con Postman o Thunder Client</h2>
    @if(!empty($guias_cliente_http))
        @foreach($guias_cliente_http as $guia)
            <h3>{{ $guia['nombre'] }}</h3>
            <ol>
                @foreach($guia['pasos'] as $paso)
                    <li>
                        @if(str_starts_with($paso, '{'))
                            <pre>{{ $paso }}</pre>
                        @else
                            {{ $paso }}
                        @endif
                    </li>
                @endforeach
            </ol>
        @endforeach
    @endif

    <h2>4. Endpoints disponibles</h2>
    @if(!empty($endpoints_generales))
        <table>
            <thead><tr><th>Método</th><th>Ruta</th><th>Auth</th><th>Descripción</th></tr></thead>
            <tbody>
                @foreach($endpoints_generales as $endpoint)
                    <tr>
                        <td><strong>{{ $endpoint['metodo'] }}</strong></td>
                        <td><code>{{ $endpoint['ruta'] }}</code></td>
                        <td>{{ ($endpoint['auth'] ?? true) ? 'Sí' : 'No' }}</td>
                        <td>{{ $endpoint['descripcion'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    @foreach($recursos as $recurso)
        <h3>{{ $recurso['nombre'] }} ({{ $recurso['slug'] }})</h3>
        <p>
            Lectura: {{ $recurso['lectura_habilitada'] ? 'Habilitada' : 'Deshabilitada' }} |
            Escritura: {{ $recurso['escritura_habilitada'] ? 'Habilitada' : 'Deshabilitada' }}
        </p>
        @if(count($recurso['endpoints']))
            <table>
                <thead>
                    <tr><th>Método</th><th>Ruta</th><th>Descripción</th></tr>
                </thead>
                <tbody>
                    @foreach($recurso['endpoints'] as $endpoint)
                        <tr>
                            <td><strong>{{ $endpoint['metodo'] }}</strong></td>
                            <td><code>{{ $endpoint['ruta'] }}</code></td>
                            <td>{{ $endpoint['descripcion'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        @if(count($recurso['campos']))
            <p><strong>Campos expuestos:</strong></p>
            <table>
                <thead>
                    <tr><th>Campo</th><th>Etiqueta</th><th>Sensible</th></tr>
                </thead>
                <tbody>
                    @foreach($recurso['campos'] as $campo)
                        <tr>
                            <td><code>{{ $campo['slug'] }}</code></td>
                            <td>{{ $campo['etiqueta'] }}</td>
                            <td>{{ $campo['es_sensible'] ? 'Sí' : 'No' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach

    <h2>5. Ejemplo: listar clientes</h2>
    <pre>GET {{ $base_url }}/clientes?page=1&amp;per_page=25&amp;q=busqueda
Authorization: Bearer {access_token}
Accept: application/json</pre>

    <h2>6. Códigos de respuesta</h2>
    <table>
        <thead><tr><th>Código</th><th>Descripción</th></tr></thead>
        <tbody>
            @foreach($codigos_error as $error)
                <tr><td>{{ $error['codigo'] }}</td><td>{{ $error['descripcion'] }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>7. Buenas prácticas e integración</h2>
    <ul>
        <li>Use siempre la URL <code>/api/v1</code>. La ruta <code>/api</code> (sin v1) es la API interna del sistema y requiere sesión web.</li>
        <li>Envíe <code>Accept: application/json</code> en <strong>todas</strong> las peticiones; sin él puede recibir redirección HTML al login.</li>
        <li>Guarde el <code>client_secret</code> de forma segura; no lo exponga en frontend.</li>
        <li>Respete el límite de peticiones por minuto configurado para su aplicación.</li>
        <li>Revise periódicamente la auditoría de uso desde el panel de administración.</li>
        <li>Use siempre HTTPS en producción.</li>
    </ul>
</body>
</html>
