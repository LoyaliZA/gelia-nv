<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Manual del Modulo de Recursos Humanos</title>
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

        .accent-bar {
            width: 100%;
            height: 5px;
            background: #2563eb;
        }

        .accent-bar-thin {
            height: 3px;
            background: #1e3a8a;
        }

        /* --- PORTADA --- */
        .cover-page {
            padding: 0;
            page-break-after: always;
        }

        .cover-body {
            padding: 0 48px 48px;
            text-align: center;
        }

        .cover-spacer-top {
            height: 110px;
        }

        .cover-gelia-name {
            font-size: 28px;
            font-weight: 900;
            letter-spacing: 8px;
            text-transform: uppercase;
            color: #1e3a8a;
            margin: 18px 0 6px;
        }

        .cover-gelia-sub {
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 36px;
        }

        .cover-title-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-left: 4px solid #2563eb;
            padding: 22px 28px;
            margin: 0 auto 40px;
            max-width: 480px;
            text-align: center;
        }

        .cover-doc-label {
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 8px;
        }

        .cover-doc-title {
            font-size: 20px;
            font-weight: 900;
            text-transform: uppercase;
            color: #1e3a8a;
            line-height: 1.25;
            margin: 0;
        }

        .cover-doc-subtitle {
            font-size: 10px;
            font-weight: bold;
            color: #2563eb;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-top: 10px;
        }

        .cover-brands {
            margin: 0 auto;
            display: table;
            border-collapse: collapse;
        }

        .cover-brand-cell {
            display: table-cell;
            vertical-align: middle;
            padding: 0 20px;
        }

        .cover-brand-cell img {
            height: 58px;
            max-width: 150px;
            display: block;
        }

        .cover-brand-divider {
            display: table-cell;
            vertical-align: middle;
            width: 1px;
        }

        .cover-brand-divider-line {
            width: 1px;
            height: 52px;
            background: #cbd5e1;
        }

        .cover-footer {
            margin-top: 56px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
            font-size: 8px;
            color: #94a3b8;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* --- CONTENIDO --- */
        .content-page {
            padding: 24px 36px 32px;
        }

        .content-header {
            margin-bottom: 22px;
        }

        .content-header-line {
            width: 100%;
            height: 1.5px;
            background: #bfdbfe;
            margin-top: 14px;
            margin-bottom: 4px;
        }

        .content-intro {
            font-size: 11px;
            color: #475569;
            margin-bottom: 20px;
            padding: 12px 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .section-title {
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #1e3a8a;
            padding: 8px 12px;
            margin: 22px 0 10px;
            background: #eff6ff;
            border-left: 3px solid #2563eb;
        }

        .subsection-title {
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #334155;
            margin: 16px 0 8px;
        }

        p { margin: 0 0 10px; }
        ul, ol { margin: 0 0 12px; padding-left: 18px; }
        li { margin-bottom: 4px; }

        table.data {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0 16px;
            font-size: 10px;
        }

        table.data th,
        table.data td {
            padding: 7px 10px;
            border: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: top;
        }

        table.data th {
            background: #f8fafc;
            color: #475569;
            font-weight: bold;
            width: 30%;
        }

        .ejemplo {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-left: 4px solid #2563eb;
            padding: 10px 14px;
            margin: 12px 0;
            font-size: 10px;
        }

        .nota {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-left: 4px solid #f59e0b;
            padding: 10px 14px;
            margin: 12px 0;
            font-size: 10px;
        }

        code {
            font-family: Courier, monospace;
            background: #f1f5f9;
            padding: 1px 4px;
            font-size: 9.5px;
            color: #1e40af;
        }

        .page-break { page-break-after: always; }

        .doc-footer {
            margin-top: 28px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            font-size: 7.5px;
            color: #94a3b8;
            text-align: center;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>

    {{-- ===================== PORTADA ===================== --}}
    <div class="cover-page">
        <div class="accent-bar"></div>
        <div class="cover-body">
            <div class="cover-spacer-top"></div>

            @include('rh.partials.gelia_isotipo_svg', ['size' => 120, 'color' => '#1e3a8a'])

            <div class="cover-gelia-name">GELIA</div>
            <div class="cover-gelia-sub">Sistema ERP · NV</div>

            <div class="cover-title-box">
                <div class="cover-doc-label">Documentacion interna</div>
                <h1 class="cover-doc-title">Manual del Modulo de<br>Recursos Humanos</h1>
                <div class="cover-doc-subtitle">Operacion, configuracion y calculos</div>
            </div>

            <table class="cover-brands" align="center" style="margin:0 auto;">
                <tr>
                    <td class="cover-brand-cell">
                        @if(!empty($logos['aromas']['base64']))
                            <img src="data:image/png;base64,{{ $logos['aromas']['base64'] }}" alt="{{ $logos['aromas']['alt'] }}" />
                        @else
                            <span style="font-size:14px; font-weight:900; color:#1e3a8a;">{{ $logos['aromas']['alt'] }}</span>
                        @endif
                    </td>
                    <td class="cover-brand-divider">
                        <div class="cover-brand-divider-line"></div>
                    </td>
                    <td class="cover-brand-cell">
                        @if(!empty($logos['bellaroma']['base64']))
                            <img src="data:image/png;base64,{{ $logos['bellaroma']['base64'] }}" alt="{{ $logos['bellaroma']['alt'] }}" />
                        @else
                            <span style="font-size:14px; font-weight:900; color:#1e3a8a;">{{ $logos['bellaroma']['alt'] }}</span>
                        @endif
                    </td>
                </tr>
            </table>

            <div class="cover-footer">
                Uso interno / Confidencial &nbsp;|&nbsp; Generado {{ $fechaGeneracion }}
            </div>
        </div>
        <div class="accent-bar accent-bar-thin"></div>
    </div>

    {{-- ===================== CONTENIDO ===================== --}}
    <div class="content-page">
        <div class="content-header">
            <table style="width:100%; border-collapse:collapse;">
                <tr>
                    <td style="width:55%; vertical-align:middle;">
                        <table style="border-collapse:collapse;">
                            <tr>
                                <td style="vertical-align:middle; padding-right:10px;">
                                    @include('rh.partials.gelia_isotipo_svg', ['size' => 22, 'color' => '#1e3a8a'])
                                </td>
                                <td style="vertical-align:middle;">
                                    <div style="font-size:7px; font-weight:bold; letter-spacing:2px; text-transform:uppercase; color:#94a3b8;">GELIA NV</div>
                                    <div style="font-size:12px; font-weight:900; color:#1e3a8a; text-transform:uppercase;">Manual RH</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td style="width:45%; vertical-align:middle; text-align:right;">
                        <div style="font-size:7px; font-weight:bold; letter-spacing:2px; text-transform:uppercase; color:#94a3b8;">Recursos Humanos</div>
                        <div style="font-size:9px; font-weight:bold; color:#2563eb;">Guia completa de operacion</div>
                    </td>
                </tr>
            </table>
            <div class="content-header-line"></div>
        </div>

        <div class="content-intro">
            Este documento explica el funcionamiento del modulo RH: colaboradores, configuracion, catalogo de turnos, horas extra, deducciones, salidas personales, prestamos y cierre de periodo. Incluye guias de uso con los <strong>campos actuales del sistema</strong> y ejemplos practicos.
        </div>

        <div class="section-title">1. Colaboradores</div>
        <p>El nucleo del modulo son los colaboradores. Cada perfil concentra datos personales, laborales y de calculo.</p>
        <div class="subsection-title">Campos principales</div>
        <ul>
            <li><strong>Informacion general:</strong> nombre, apellidos, departamento, area, puesto (<code>catalogo_puesto_id</code>).</li>
            <li><strong>Turno asignado:</strong> <code>catalogo_turno_id</code> (obligatorio). Define la salida oficial por dia para horas extra.</li>
            <li><strong>Salarios y bonos:</strong> <code>salario_base</code>, <code>bono_productividad</code>, <code>bono_puntualidad</code> (monto diario en formulario; el sistema calcula el periodo).</li>
            <li><strong>Horas laboradas oficiales:</strong> <code>horas_laboradas_oficiales</code> (default 8). Respaldo en HE cuando el dia es descanso.</li>
            <li><strong>Campos calculados:</strong> <code>salario_diario</code>, <code>salario_por_hora</code>, <code>salario_por_minuto</code>, bonos diarios.</li>
            <li><strong>Gerentes asignados:</strong> usuarios con permiso de incidencias gerente vinculados al colaborador.</li>
        </ul>
        <div class="nota">
            <strong>Importante:</strong> El salario por minuto y por hora se calcula con <strong>8 horas fijas</strong> (<code>salario_diario / 8</code> y <code>salario_diario / 480</code>). El turno <em>no</em> modifica deducciones ni salidas personales.
        </div>

        <div class="section-title">2. Configuracion RH</div>
        <p>Ruta: <strong>RH &gt; Configuracion</strong>. Valores en <code>rh_configuraciones</code>.</p>

        <div class="subsection-title">2.1 Periodo de pago y salarios</div>
        <table class="data">
            <tr><th>Campo</th><th>Uso</th></tr>
            <tr><td><code>dias_periodo_pago</code></td><td><code>salario_diario = salario_base / dias_periodo_pago</code>. Default: 30.</td></tr>
            <tr><td><code>decimales_salario_minuto</code></td><td>Precision de <code>salario_por_minuto</code>. Default: 8.</td></tr>
            <tr><td><code>recalcular_salarios</code></td><td>Al guardar, recalcula todos los colaboradores activos.</td></tr>
        </table>

        <div class="subsection-title">2.2 Horas extra (configuracion global)</div>
        <table class="data">
            <tr><th>Campo</th><th>Uso</th></tr>
            <tr><td><code>he_multiplicador_pago</code></td><td>Multiplicador del pago (ej. 2 = doble). Default: 2.00.</td></tr>
            <tr><td><code>he_minutos_minimos</code></td><td>Bloque pagable en minutos. Default: 30.</td></tr>
            <tr><td><code>he_gracia_minutos_despues_salida</code></td><td>Minutos despues de salida oficial antes de contar extra. Default: 30.</td></tr>
            <tr><td><code>he_tarifa_hora_fija</code></td><td>Tarifa fija por hora. Default: $39.00.</td></tr>
            <tr><td><code>he_usar_tarifa_fija</code></td><td>Activo: usa tarifa fija; inactivo: <code>salario_por_hora</code> del colaborador.</td></tr>
            <tr><td><code>he_folio_prefijo</code> / <code>he_folio_padding</code></td><td>Formato de folios HE.</td></tr>
        </table>

        <div class="ejemplo">
            <strong>Ejemplo tipico:</strong> Multiplicador 2, bloque 30 min, gracia 30 min, tarifa $39.<br>
            Salida oficial 18:00, salida real 19:15: 45 min extra desde 18:30; <strong>2 horas a pagar</strong>; <strong>2 x 2 x $39 = $156</strong>.
        </div>

        <div class="section-title">3. Catalogo de Turnos</div>
        <p>Ruta: <strong>RH &gt; Configuracion &gt; Catalogo de Turnos</strong>. Tabla <code>catalogo_turnos</code>.</p>
        <p>Claves por dia: <code>lunes</code> ... <code>domingo</code>. Campos: <code>entrada</code>, <code>salida</code>, <code>horas</code>, <code>descanso</code>.</p>

        <div class="subsection-title">3.1 Turno estandar</div>
        <table class="data">
            <tr><th>Dia</th><th>Entrada</th><th>Salida</th><th>Descanso</th></tr>
            <tr><td>Lunes - Viernes</td><td>09:00</td><td>18:00</td><td>No</td></tr>
            <tr><td>Sabado</td><td>09:00</td><td>14:00</td><td>No</td></tr>
            <tr><td>Domingo</td><td>N/A</td><td>N/A</td><td>Si</td></tr>
        </table>

        <div class="subsection-title">3.2 Que calculos usa el turno?</div>
        <table class="data">
            <tr><th>Modulo</th><th>Usa turno?</th><th>Detalle</th></tr>
            <tr><td><strong>Horas extra</strong></td><td>Si</td><td><code>salida</code> del dia en <code>matriz_horario</code> define salida oficial.</td></tr>
            <tr><td>Salario diario / minuto</td><td>No</td><td>Divisor fijo de 8 horas.</td></tr>
            <tr><td>Salidas personales</td><td>No</td><td><code>salario_diario / 480</code>.</td></tr>
            <tr><td>Deducciones</td><td>No</td><td>Reglas del catalogo y bonos del colaborador.</td></tr>
            <tr><td>Prestamos / periodo</td><td>No</td><td>Agregan montos registrados.</td></tr>
        </table>

        <div class="subsection-title">3.3 Prioridad salida oficial (HE)</div>
        <ol>
            <li>Turno: <code>matriz_horario[dia].salida</code> si <code>descanso = false</code>.</li>
            <li>Dia descanso con trabajo: entrada + <code>horas_laboradas_oficiales</code>.</li>
            <li>Sin turno: <code>hora_salida_oficial</code> del colaborador.</li>
            <li>Respaldo: <code>hora_entrada_oficial + horas_laboradas_oficiales</code> o entrada + 8 h.</li>
        </ol>

        <div class="ejemplo">
            <strong>Martes estandar:</strong> Salida oficial 18:00, salida real 19:00, gracia 30 min.<br>
            Extra desde 18:30 (90 min) = <strong>3 horas a pagar</strong>; con tarifa $39 y x2: <strong>3 x 2 x $39 = $234</strong>.
        </div>

        <div class="page-break"></div>

        <div class="section-title">4. Horas Extra (registro)</div>
        <p>Ruta: <strong>RH &gt; Horas Extra</strong>. Campos: <code>fecha_turno</code>, <code>hora_entrada</code>, <code>hora_salida</code>, <code>motivo</code>, <code>supervisor_user_id</code>.</p>
        <ol>
            <li><strong>Minutos extra</strong> = tiempo despues de (salida oficial + gracia).</li>
            <li><strong>Horas a pagar</strong> = bloques segun <code>he_minutos_minimos</code>.</li>
            <li><strong>Monto</strong> = horas x multiplicador x tarifa.</li>
        </ol>

        <div class="section-title">5. Reglas de Incidencia</div>
        <p>Catalogo en Configuracion: tipos de sancion, factores sobre salario/bonos, restricciones por area.</p>

        <div class="section-title">6. Deducciones Operativas</div>
        <ul>
            <li><strong>Incidencias:</strong> retardos, faltas, reglas operativas.</li>
            <li><strong>Pagos y pendientes:</strong> prestamos, cuotas, arrastres.</li>
        </ul>
        <p>Recibos PDF individuales y consolidados por periodo.</p>

        <div class="ejemplo">
            <strong>Salida personal:</strong> <code>salario_diario = $315.04</code>; minuto = <code>315.04 / 480</code>.<br>
            45 min ausente: <strong>45 x 0.6563 = $29.53</strong>.
        </div>

        <div class="section-title">7. Salidas Personales</div>
        <p>Campos: <code>fecha_evento</code>, <code>hora_salida</code>, <code>hora_regreso</code>. Descuento por minuto ausente.</p>

        <div class="section-title">8. Prestamos y Pagos Fijos</div>
        <p>Cuotas automaticas por periodo hasta liquidar.</p>

        <div class="section-title">9. Consolidado y Periodo de Pago</div>
        <ul>
            <li><strong>Consolidado:</strong> pre-nomina con todos los descuentos.</li>
            <li><strong>Sellar periodo:</strong> aplica deducciones y arrastres.</li>
            <li><strong>Periodo de pago:</strong> percepciones menos deducciones del rango.</li>
        </ul>

        <div class="section-title">10. Incidencias de Gerente</div>
        <p>Permiso <code>rh.incidencias.gerente.crear</code>. Recibo impreso con firma fisica.</p>

        <div class="section-title">11. Resumen configuracion y calculo</div>
        <table class="data">
            <tr><th>Origen</th><th>Campo</th><th>Afecta a</th></tr>
            <tr><td>Config RH</td><td><code>dias_periodo_pago</code></td><td>Salario y bonos diarios</td></tr>
            <tr><td>Config RH</td><td><code>he_*</code></td><td>Horas extra</td></tr>
            <tr><td>Turnos</td><td><code>matriz_horario[salida]</code></td><td>Salida oficial HE</td></tr>
            <tr><td>Colaborador</td><td><code>catalogo_turno_id</code></td><td>Turno para HE</td></tr>
            <tr><td>Colaborador</td><td><code>salario_base</code></td><td>Deducciones y salidas</td></tr>
            <tr><td>Reglas</td><td>factores</td><td>Deducciones operativas</td></tr>
        </table>

        <div class="section-title">12. Glosario</div>
        <ul>
            <li><strong>Incidencia / Deduccion:</strong> evento vs. cargo en nomina.</li>
            <li><strong>Factor de penalizacion:</strong> multiplicador de dias de bono o salario.</li>
            <li><strong>Arrastre:</strong> deuda al siguiente periodo.</li>
            <li><strong>Gracia HE:</strong> tolerancia despues de salida oficial.</li>
            <li><strong>Bloque HE:</strong> unidad minima pagable (<code>he_minutos_minimos</code>).</li>
        </ul>

        <div class="doc-footer">
            Documento generado por GELIA NV &nbsp;|&nbsp; Uso interno / Confidencial &nbsp;|&nbsp; {{ $fechaGeneracion }}
        </div>
    </div>

</body>
</html>
