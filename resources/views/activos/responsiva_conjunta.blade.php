<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Responsiva Conjunta de Activos - {{ $usuario->name }}</title>
    <style>
        /* ====================================
           BASE
        ==================================== */
        * { box-sizing: border-box; }
        body {
            font-family: 'Helvetica Neue', 'Helvetica', 'Arial', sans-serif;
            font-size: 10.5px;
            color: #1a1a2e;
            line-height: 1.6;
            margin: 0;
            padding: 28px 36px 36px 36px;
            background: #ffffff;
        }

        /* ====================================
           HEADER
        ==================================== */
        .header-wrapper {
            width: 100%;
            margin-bottom: 22px;
        }

        /* Barra azul superior decorativa */
        .header-accent-bar {
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #1e3a8a 0%, #2563eb 50%, #1e3a8a 100%);
            margin-bottom: 16px;
            border-radius: 2px;
        }

        .header-content {
            display: table;
            width: 100%;
            border-collapse: collapse;
        }

        /* Columna izquierda: logos de marca */
        .header-logos-cell {
            display: table-cell;
            width: 60%;
            vertical-align: middle;
            padding: 0;
        }

        /* Columna derecha: título del documento */
        .header-doc-cell {
            display: table-cell;
            width: 40%;
            vertical-align: middle;
            text-align: right;
            padding: 0;
        }

        /* ── Logos de Aromas y Bellaroma ── */
        .brand-logos-row {
            display: table;
            border-collapse: collapse;
        }
        .brand-logo-cell {
            display: table-cell;
            vertical-align: middle;
        }
        .brand-logo-cell img {
            display: block;
        }
        .brand-divider {
            display: table-cell;
            vertical-align: middle;
            padding: 0 14px;
        }
        .brand-divider-line {
            width: 1px;
            height: 55px;
            background-color: #cbd5e1;
        }

        /* ── Logo de Gelia (pequeño, esquina) ── */
        .gelia-watermark {
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .gelia-watermark-text {
            font-size: 9px;
            font-weight: bold;
            color: #64748b;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        /* ── Título del documento ── */
        .doc-title-box {
            text-align: right;
        }
        .doc-title-label {
            font-size: 7px;
            font-weight: bold;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 4px;
        }
        .doc-title-main {
            font-size: 14px;
            font-weight: 900;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            line-height: 1.2;
        }
        .doc-title-sub {
            font-size: 8px;
            font-weight: bold;
            color: #2563eb;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-top: 3px;
        }
        .folio-badge {
            margin-top: 10px;
            display: inline-block;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 5px 10px;
            text-align: right;
        }
        .folio-badge .folio-row {
            font-size: 9px;
            color: #64748b;
        }
        .folio-badge .folio-row strong {
            color: #1e3a8a;
        }

        /* Línea divisora inferior del header */
        .header-bottom-line {
            width: 100%;
            height: 1.5px;
            background: linear-gradient(90deg, #2563eb 0%, #bfdbfe 60%, transparent 100%);
            margin-top: 16px;
        }

        /* ====================================
           SECCIONES
        ==================================== */
        .section-title {
            font-size: 9.5px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #1e3a8a;
            padding: 7px 12px;
            margin-top: 18px;
            margin-bottom: 8px;
            background-color: #eff6ff;
            border-left: 3px solid #2563eb;
            border-radius: 0 4px 4px 0;
        }

        /* ====================================
           TABLA DE DATOS
        ==================================== */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .info-table th,
        .info-table td {
            padding: 7px 10px;
            text-align: left;
            vertical-align: top;
            border: 1px solid #e2e8f0;
            font-size: 9.5px;
        }
        .info-table th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 700;
            width: 22%;
            white-space: nowrap;
        }
        .info-table td {
            color: #1e293b;
        }

        /* ====================================
           CLÁUSULAS LEGALES
        ==================================== */
        .legal-clauses {
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
            padding: 14px 16px;
            border-radius: 6px;
            margin-top: 20px;
            margin-bottom: 20px;
            text-align: justify;
            font-size: 9.5px;
            color: #475569;
            border-left: 3px solid #93c5fd;
        }
        .legal-title {
            font-weight: 900;
            color: #1e3a8a;
            margin-bottom: 8px;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 1.5px;
        }

        /* ====================================
           FIRMAS
        ==================================== */
        .signatures {
            width: 100%;
            margin-top: 45px;
            border-collapse: collapse;
        }
        .signatures td {
            width: 50%;
            text-align: center;
            vertical-align: bottom;
            padding: 0 20px;
        }
        .firma-img {
            max-height: 70px;
            max-width: 180px;
            display: block;
            margin: 0 auto;
        }
        .sig-line {
            width: 85%;
            border: none;
            border-top: 1.5px solid #94a3b8;
            margin: 8px auto 6px auto;
        }
        .sig-title {
            font-weight: 800;
            color: #0f172a;
            font-size: 10px;
            letter-spacing: 0.3px;
        }
        .sig-subtitle {
            color: #64748b;
            font-size: 8.5px;
            margin-top: 2px;
        }
        .sig-digital-label-blue {
            font-size: 7.5px;
            color: #2563eb;
            font-weight: 700;
            margin-bottom: 3px;
        }
        .sig-digital-label-green {
            font-size: 7.5px;
            color: #059669;
            font-weight: 700;
            margin-bottom: 3px;
        }
        .sig-pending {
            height: 70px;
            line-height: 70px;
            color: #94a3b8;
            font-style: italic;
            font-size: 10px;
        }

        /* ====================================
           FOOTER
        ==================================== */
        .doc-footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            display: table;
            width: 100%;
        }
        .footer-left {
            display: table-cell;
            text-align: left;
            font-size: 7.5px;
            color: #94a3b8;
            vertical-align: middle;
        }
        .footer-right {
            display: table-cell;
            text-align: right;
            font-size: 7.5px;
            color: #94a3b8;
            vertical-align: middle;
        }
    </style>
</head>
<body>

    {{-- ============================================================
         ENCABEZADO
    ============================================================ --}}
    <div class="header-wrapper">

        {{-- Barra decorativa azul --}}
        <div class="header-accent-bar"></div>

        {{-- Fila principal del header --}}
        <table style="width:100%; border-collapse:collapse;">
            <tr>
                {{-- ── COLUMNA IZQUIERDA: Logos de marca ── --}}
                <td style="width:62%; vertical-align:middle; padding:0;">

                    {{-- Logos de Aromas y Bellaroma --}}
                    <table style="border-collapse:collapse; margin:0; padding:0;">
                        <tr>
                            {{-- Aromas Exclusivos --}}
                            <td style="vertical-align:middle; padding:0;">
                                <img
                                    src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('Images/Logos/aromas_logo_negro.png'))) }}"
                                    style="height: 60px; max-width: 130px; display:block;"
                                    alt="Aromas Exclusivos"
                                />
                            </td>

                            {{-- Separador --}}
                            <td style="vertical-align:middle; padding:0 16px;">
                                <div style="width:1px; height:52px; background-color:#cbd5e1;"></div>
                            </td>

                            {{-- Bellaroma --}}
                            <td style="vertical-align:middle; padding:0;">
                                <img
                                    src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('Images/Logos/bellaroma_logo_negro.png'))) }}"
                                    style="height: 60px; max-width: 130px; display:block;"
                                    alt="Bellaroma"
                                />
                            </td>
                        </tr>
                    </table>

                    {{-- Logo Gelia pequeño --}}
                    <table style="border-collapse:collapse; margin-top:8px;">
                        <tr>
                            <td style="vertical-align:middle; padding:0 6px 0 0;">
                                <svg viewBox="0 0 100 100" style="width:18px; height:18px;" xmlns="http://www.w3.org/2000/svg">
                                    <g fill="#1e3a8a">
                                        <polygon points="30,10 70,10 30,30" opacity="1.0"/>
                                        <polygon points="10,30 30,30 10,70" opacity="1.0"/>
                                        <polygon points="30,90 30,70 70,90" opacity="1.0"/>
                                        <polygon points="90,70 70,70 90,50" opacity="1.0"/>
                                        <polygon points="70,50 70,70 50,50" opacity="1.0"/>
                                        <polygon points="70,10 70,30 30,30" opacity="0.55"/>
                                        <polygon points="30,30 30,70 10,70" opacity="0.55"/>
                                        <polygon points="30,70 70,70 70,90" opacity="0.55"/>
                                        <polygon points="70,70 70,50 90,50" opacity="0.55"/>
                                        <polygon points="70,10 90,30 70,30" opacity="0.25"/>
                                        <polygon points="30,10 30,30 10,30" opacity="0.25"/>
                                        <polygon points="10,70 30,70 30,90" opacity="0.25"/>
                                        <polygon points="70,90 70,70 90,70" opacity="0.25"/>
                                    </g>
                                </svg>
                            </td>
                            <td style="vertical-align:middle; font-size:8px; font-weight:bold; color:#64748b; letter-spacing:2.5px; text-transform:uppercase; padding:0;">
                                GELIA NV &nbsp;·&nbsp;
                            </td>
                        </tr>
                    </table>

                </td>

                {{-- ── COLUMNA DERECHA: Título del documento ── --}}
                <td style="width:38%; vertical-align:middle; text-align:right; padding:0;">
                    <div style="font-size:7px; font-weight:bold; letter-spacing:3px; text-transform:uppercase; color:#94a3b8; margin-bottom:5px;">
                        Documento Consolidado
                    </div>
                    <div style="font-size:14px; font-weight:900; color:#1e3a8a; text-transform:uppercase; letter-spacing:0.3px; line-height:1.2;">
                        Responsiva Colectiva
                    </div>
                    <div style="font-size:8px; font-weight:bold; color:#2563eb; text-transform:uppercase; letter-spacing:2px; margin-top:2px;">
                        de Activos
                    </div>
                    <div style="margin-top:12px; display:inline-block; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:5px; padding:6px 12px; text-align:right;">
                        <div style="font-size:8.5px; color:#64748b; line-height:1.7;">
                            <strong style="color:#1e3a8a;">Equipos Entregados:</strong>&nbsp;{{ count($asignaciones) }}<br>
                            <strong style="color:#1e3a8a;">Fecha de Emisión:</strong>&nbsp;{{ $fecha }}
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        {{-- Línea divisora inferior --}}
        <div style="width:100%; height:1.5px; background:linear-gradient(90deg,#2563eb 0%,#bfdbfe 60%,transparent 100%); margin-top:16px;"></div>
    </div>

    {{-- ============================================================
         DATOS DEL COLABORADOR
    ============================================================ --}}
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
            <td>{{ $asignaciones->first()->activo->departamento?->nombre ?? 'N/A' }}</td>
            <th>Área:</th>
            <td>{{ $asignaciones->first()->activo->area?->nombre ?? 'N/A' }}</td>
        </tr>
    </table>

    {{-- ============================================================
         ESPECIFICACIONES DE LOS ACTIVOS ENTREGADOS
    ============================================================ --}}
    <div class="section-title">Especificaciones de los Activos Entregados</div>
    <table class="info-table" style="width:100%; border-collapse:collapse; margin-bottom:14px;">
        <thead>
            <tr style="background-color: #f8fafc;">
                <th style="width: 15%; font-weight: bold; color: #475569; padding: 7px 10px; border: 1px solid #e2e8f0; font-size: 9px;">Folio</th>
                <th style="width: 25%; font-weight: bold; color: #475569; padding: 7px 10px; border: 1px solid #e2e8f0; font-size: 9px;">Nombre del Activo</th>
                <th style="width: 20%; font-weight: bold; color: #475569; padding: 7px 10px; border: 1px solid #e2e8f0; font-size: 9px;">Tipo de Activo</th>
                <th style="width: 25%; font-weight: bold; color: #475569; padding: 7px 10px; border: 1px solid #e2e8f0; font-size: 9px;">Detalles Técnicos</th>
                <th style="width: 15%; font-weight: bold; color: #475569; padding: 7px 10px; border: 1px solid #e2e8f0; font-size: 9px;">Condiciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($asignaciones as $asignacion)
                @php
                    $activo = $asignacion->activo;
                @endphp
                <tr>
                    <td style="padding: 7px 10px; border: 1px solid #e2e8f0; font-size: 9px; font-weight: bold; color: #1e3a8a;">{{ $activo->folio }}</td>
                    <td style="padding: 7px 10px; border: 1px solid #e2e8f0; font-size: 9px; color: #1e293b;">{{ $activo->nombre }}</td>
                    <td style="padding: 7px 10px; border: 1px solid #e2e8f0; font-size: 9px; color: #475569;">{{ $activo->tipo?->nombre ?? 'N/A' }}</td>
                    <td style="padding: 7px 10px; border: 1px solid #e2e8f0; font-size: 8.5px; color: #475569; line-height: 1.4;">
                        @if(!empty($activo->atributos))
                            @foreach($activo->atributos as $key => $value)
                                <strong>{{ ucwords(str_replace('_', ' ', $key)) }}:</strong> {{ $value }}<br>
                            @endforeach
                        @else
                            —
                        @endif
                    </td>
                    <td style="padding: 7px 10px; border: 1px solid #e2e8f0; font-size: 8.5px; color: #1e293b;">
                        {{ $asignacion->condiciones_entrega ?: 'Buen estado.' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ============================================================
         TÉRMINOS Y CONDICIONES
    ============================================================ --}}
    <div class="legal-clauses">
        <div class="legal-title">Términos y Condiciones de Responsabilidad Colectiva</div>
        {!! nl2br(e($terminos)) !!}
    </div>

    {{-- ============================================================
         FIRMAS
    ============================================================ --}}
    @php
        // Obtener la firma de la primera asignación que esté firmada
        $firmaAsignacion = $asignaciones->firstWhere('firmado', true) ?? $asignaciones->first();
    @endphp
    <table class="signatures">
        <tr>
            {{-- Entregado por --}}
            <td>
                @if($firmaAsignacion->asignadoPor?->firma_ruta && Storage::disk('public')->exists($firmaAsignacion->asignadoPor->firma_ruta))
                    <img src="data:image/png;base64,{{ base64_encode(Storage::disk('public')->get($firmaAsignacion->asignadoPor->firma_ruta)) }}" class="firma-img" />
                    <div class="sig-digital-label-blue">✔ Firmado digitalmente</div>
                @else
                    <div class="sig-pending">Firma del Responsable</div>
                @endif
                <div class="sig-line"></div>
                <div class="sig-title">{{ $firmaAsignacion->asignadoPor?->name ?: 'Representante de la Empresa' }}</div>
                <div class="sig-subtitle">Entregado por (GELIA)</div>
            </td>

            {{-- Recibido por --}}
            <td>
                @if($firmaAsignacion->firmado && $firmaAsignacion->firma_ruta && Storage::disk('public')->exists($firmaAsignacion->firma_ruta))
                    <img src="data:image/png;base64,{{ base64_encode(Storage::disk('public')->get($firmaAsignacion->firma_ruta)) }}" class="firma-img" />
                    <div class="sig-digital-label-green">✔ Firmado digitalmente el {{ $firmaAsignacion->firma_fecha->format('d/m/Y H:i:s') }}</div>
                @else
                    <div class="sig-pending">Firma Pendiente</div>
                @endif
                <div class="sig-line"></div>
                <div class="sig-title">{{ $usuario->name }} {{ $usuario->apellido_paterno }}</div>
                <div class="sig-subtitle">Acepto de conformidad (Colaborador)</div>
            </td>
        </tr>
    </table>

    {{-- ============================================================
         PIE DE PÁGINA
    ============================================================ --}}
    <table class="doc-footer">
        <tr>
            <td style="text-align:left; font-size:7.5px; color:#94a3b8; vertical-align:middle;">
                Documento de responsiva consolidada generado por GELIA NV &nbsp;·&nbsp; Uso interno / Confidencial
            </td>
            <td style="text-align:right; font-size:7.5px; color:#94a3b8; vertical-align:middle;">
                Colaborador: {{ $usuario->name }} &nbsp;·&nbsp; {{ $fecha }}
            </td>
        </tr>
    </table>

</body>
</html>
