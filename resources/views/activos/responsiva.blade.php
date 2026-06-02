<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Responsiva de Activo - {{ $activo->folio }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11px;
            color: #333333;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }
        .header {
            width: 100%;
            margin-bottom: 20px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 10px;
        }
        .header table {
            width: 100%;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
            font-style: italic;
        }
        .doc-title {
            text-align: right;
            font-size: 16px;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
        }
        .folio-info {
            text-align: right;
            font-size: 10px;
            color: #555555;
            margin-top: 5px;
        }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            background-color: #f3f4f6;
            color: #1e3a8a;
            padding: 6px 10px;
            margin-top: 15px;
            margin-bottom: 8px;
            border-left: 3px solid #2563eb;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .info-table th, .info-table td {
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
            border: 1px solid #e5e7eb;
        }
        .info-table th {
            background-color: #f9fafb;
            color: #4b5563;
            font-weight: bold;
            width: 25%;
        }
        .legal-clauses {
            border: 1px solid #d1d5db;
            background-color: #fafafa;
            padding: 12px;
            border-radius: 4px;
            margin-top: 20px;
            margin-bottom: 20px;
            text-align: justify;
            font-size: 10px;
            color: #4b5563;
        }
        .legal-title {
            font-weight: bold;
            color: #1e3a8a;
            margin-bottom: 6px;
            text-transform: uppercase;
            font-size: 10px;
        }
        .signatures {
            width: 100%;
            margin-top: 40px;
        }
        .signatures td {
            width: 50%;
            text-align: center;
            vertical-align: bottom;
        }
        .sig-line {
            width: 80%;
            border-top: 1px solid #9ca3af;
            margin: 10px auto 5px auto;
        }
        .sig-title {
            font-weight: bold;
            color: #111827;
            font-size: 10px;
        }
        .sig-subtitle {
            color: #6b7280;
            font-size: 9px;
        }
        .firma-img {
            max-height: 70px;
            max-width: 180px;
            display: block;
            margin: 0 auto;
        }
    </style>
</head>
<body>

    <div class="header">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <!-- Logos alineados en la esquina superior izquierda -->
                <td style="width: 65%; text-align: left; vertical-align: middle; padding: 0;">
                    <table style="border: 0; padding: 0; margin: 0; border-collapse: collapse;">
                        <tr>
                            <!-- Logo de GELIA (SVG) -->
                            <td style="vertical-align: middle; padding: 0 10px 0 0;">
                                <svg viewBox="0 0 100 100" style="width: 32px; height: 32px;" xmlns="http://www.w3.org/2000/svg">
                                    <g fill="#1e3a8a">
                                        <polygon points="30,10 70,10 30,30" opacity="1.0" />
                                        <polygon points="10,30 30,30 10,70" opacity="1.0" />
                                        <polygon points="30,90 30,70 70,90" opacity="1.0" />
                                        <polygon points="90,70 70,70 90,50" opacity="1.0" />
                                        <polygon points="70,50 70,70 50,50" opacity="1.0" />
                                        <polygon points="70,10 70,30 30,30" opacity="0.6" />
                                        <polygon points="30,30 30,70 10,70" opacity="0.6" />
                                        <polygon points="30,70 70,70 70,90" opacity="0.6" />
                                        <polygon points="70,70 70,50 90,50" opacity="0.6" />
                                        <polygon points="70,10 90,30 70,30" opacity="0.3" />
                                        <polygon points="30,10 30,30 10,30" opacity="0.3" />
                                        <polygon points="10,70 30,70 30,90" opacity="0.3" />
                                        <polygon points="70,90 70,70 90,70" opacity="0.3" />
                                    </g>
                                </svg>
                            </td>
                            <!-- Nombre de GELIA -->
                            <td style="vertical-align: middle; padding: 0 15px 0 0; font-family: sans-serif; font-size: 13px; font-weight: bold; color: #1e3a8a; letter-spacing: 1px;">
                                GELIA
                            </td>
                            <!-- Línea divisora -->
                            <td style="vertical-align: middle; padding: 0; width: 1px;">
                                <div style="width: 1px; height: 25px; background-color: #d1d5db; margin: 0 12px;"></div>
                            </td>
                            <!-- Logo de Aromas (PNG) -->
                            <td style="vertical-align: middle; padding: 0 12px 0 12px;">
                                <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('Images/Logos/aromas_logo_negro.png'))) }}" style="height: 25px; max-width: 90px;" />
                            </td>
                            <!-- Logo de Bellaroma (PNG) -->
                            <td style="vertical-align: middle; padding: 0 0 0 12px;">
                                <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('Images/Logos/bellaroma_logo_negro.png'))) }}" style="height: 25px; max-width: 90px;" />
                            </td>
                        </tr>
                    </table>
                </td>
                <!-- Título y folio del documento a la derecha -->
                <td style="width: 35%; text-align: right; vertical-align: middle; padding: 0;">
                    <div class="doc-title" style="margin-bottom: 2px;">Carta Responsiva de Activo</div>
                    <div class="folio-info">
                        <strong>Folio Activo:</strong> {{ $activo->folio }}<br>
                        <strong>Fecha de Emisión:</strong> {{ $fecha }}
                    </td>
            </tr>
        </table>
    </div>

    <!-- DATOS DEL COLABORADOR -->
    <div class="section-title">Datos del Colaborador (Responsable)</div>
    <table class="info-table">
        <tr>
            <th>Nombre Completo:</th>
            <td>{{ $usuario->name }} {{ $usuario->apellido_paterno }} {{ $usuario->apellido_materno }}</td>
            <th>Correo Electrónico:</th>
            <td>{{ $usuario->email }}</td>
        </tr>
        <tr>
            <th>Departamento:</th>
            <td>{{ $activo->departamento?->nombre ?? 'N/A' }}</td>
            <th>Área:</th>
            <td>{{ $activo->area?->nombre ?? 'N/A' }}</td>
        </tr>
    </table>

    <!-- DATOS DEL ACTIVO -->
    <div class="section-title">Especificaciones del Activo</div>
    <table class="info-table">
        <tr>
            <th>Nombre del Activo:</th>
            <td>{{ $activo->nombre }}</td>
            <th>Tipo de Activo:</th>
            <td>{{ $activo->tipo?->nombre ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th>Descripción:</th>
            <td colspan="3">{{ $activo->descripcion ?: 'Sin descripción detallada.' }}</td>
        </tr>
        @if(!empty($activo->atributos))
            <tr>
                <th>Detalles Técnicos:</th>
                <td colspan="3">
                    @foreach($activo->atributos as $key => $value)
                        <strong>{{ ucwords(str_replace('_', ' ', $key)) }}:</strong> {{ $value }} &nbsp;&nbsp;|&nbsp;&nbsp;
                    @endforeach
                </td>
            </tr>
        @endif
        <tr>
            <th>Condiciones de Entrega:</th>
            <td colspan="3"><strong>{{ $asignacion->condiciones_entrega ?: 'Buen estado / Sin observaciones específicas.' }}</strong></td>
        </tr>
        @if($asignacion->condiciones_devolucion)
            <tr>
                <th>Condiciones de Devolución:</th>
                <td colspan="3"><strong>{{ $asignacion->condiciones_devolucion }}</strong></td>
            </tr>
        @endif
    </table>

    <!-- CLÁUSULAS LEGALES -->
    <div class="legal-clauses">
        <div class="legal-title">Términos y Condiciones de Responsabilidad</div>
        {!! nl2br(e($terminos)) !!}
    </div>

    <!-- SECCIÓN DE FIRMAS -->
    <table class="signatures">
        <tr>
            <td>
                <!-- Entregado por -->
                @if($asignacion->asignadoPor?->firma_ruta && Storage::disk('public')->exists($asignacion->asignadoPor->firma_ruta))
                    <img src="data:image/png;base64,{{ base64_encode(Storage::disk('public')->get($asignacion->asignadoPor->firma_ruta)) }}" class="firma-img" />
                    <div style="font-size: 8px; color: #3b82f6; font-weight: bold; margin-bottom: 2px;">Firmado digitalmente</div>
                @else
                    <div style="height: 70px; line-height: 70px; color: #9ca3af; font-style: italic; font-size: 10px;">Firma del Responsable</div>
                @endif
                <div class="sig-line"></div>
                <div class="sig-title">{{ $asignacion->asignadoPor?->name ?: 'Representante de la Empresa' }}</div>
                <div class="sig-subtitle">Entregado por (GELIA)</div>
            </td>
            <td>
                <!-- Recibido por -->
                @if($asignacion->firmado && $asignacion->firma_ruta && Storage::disk('public')->exists($asignacion->firma_ruta))
                    <img src="data:image/png;base64,{{ base64_encode(Storage::disk('public')->get($asignacion->firma_ruta)) }}" class="firma-img" />
                    <div style="font-size: 8px; color: #10b981; font-weight: bold; margin-bottom: 2px;">Firmado digitalmente el {{ $asignacion->firma_fecha->format('d/m/Y H:i:s') }}</div>
                @else
                    <div style="height: 70px; line-height: 70px; color: #9ca3af; font-style: italic; font-size: 10px;">Firma Pendiente</div>
                @endif
                <div class="sig-line"></div>
                <div class="sig-title">{{ $usuario->name }} {{ $usuario->apellido_paterno }}</div>
                <div class="sig-subtitle">Acepto de conformidad (Colaborador)</div>
            </td>
        </tr>
    </table>

</body>
</html>
