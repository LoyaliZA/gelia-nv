import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Plus, Eye, Pencil, Printer, PenLine } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { formatoDeduccionDecimal, formatoMoneda, nombreCompletoColaborador } from '../../../utils/formatoMoneda';
import RhSubNav from '../Partials/RhSubNav';
import RhPageHeader from '../Partials/RhPageHeader';
import FiltrosDeducciones from './Partials/FiltrosDeducciones';
import ModalFormDeduccion from './Partials/ModalFormDeduccion';
import ModalVistaPreviaRecibo from '../Partials/ModalVistaPreviaRecibo';
import ModalFirmarReciboDeduccion from '../Partials/ModalFirmarReciboDeduccion';
import { ESTADO_DEDUCCION_BADGE, ESTADO_DEDUCCION_LABELS, ORIGEN_DEDUCCION_LABELS } from './Partials/deduccionesStyles';

function tabFromFiltros(filtros) {
    if (filtros.estado_deduccion === 'pendiente_nomina' || filtros.estado_deduccion === 'pendiente') return 'PENDIENTE_NOMINA';
    if (filtros.estado_deduccion === 'pendiente_comision') return 'PENDIENTE_COMISION';
    if (filtros.estado_deduccion === 'aplicado') return 'APLICADAS';
    if (filtros.estado_deduccion === 'programado') return 'PENDIENTE_NOMINA';
    return 'TODAS';
}

function rutaListado(rama) {
    return rama === 'pagos_pendientes'
        ? route('rh.deducciones.pagos_pendientes.index')
        : route('rh.deducciones.incidencias.index');
}

function FilaAcciones({ reg, puedeEditar, puedeRecibos, onEditar, onRecibo, reciboFirmado }) {
    return (
        <div className="flex flex-wrap gap-3 mt-3">
            <Link href={route('rh.deducciones.show', reg.id)} className="inline-flex items-center gap-1 text-[10px] font-black uppercase" style={{ color: 'var(--color-primario)' }}>
                <Eye className="w-3.5 h-3.5" /> Ver expediente
            </Link>
            {puedeRecibos && reg.catalogo_regla_incidencia_id && (
                <button type="button" onClick={() => onRecibo(reg)} className={`inline-flex items-center gap-1 text-[10px] font-black uppercase ${reciboFirmado(reg) ? 'theme-text-muted' : 'text-amber-600'}`}>
                    {reciboFirmado(reg) ? (
                        <><Printer className="w-3.5 h-3.5" /> Recibo</>
                    ) : (
                        <><PenLine className="w-3.5 h-3.5" /> Firmar</>
                    )}
                </button>
            )}
            {puedeEditar && reg.estado_deduccion !== 'aplicado' && (
                <button type="button" onClick={() => onEditar(reg)} className="inline-flex items-center gap-1 text-[10px] font-black uppercase theme-text-muted">
                    <Pencil className="w-3.5 h-3.5" /> Editar
                </button>
            )}
        </div>
    );
}

export default function Index({
    auth,
    rama = 'incidencias',
    tituloHighlight = 'Incidencias',
    descripcion = 'Registro de incidencias y deducciones operativas',
    registros,
    metricas,
    colaboradores,
    reglasIncidencia,
    departamentos,
    filtros,
    usuarioActual,
    puedeCrear,
    puedeEditar,
    puedeRecibos = false,
}) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [registroEditando, setRegistroEditando] = useState(null);
    const [tabActiva, setTabActiva] = useState(() => tabFromFiltros(filtros));
    const [previewRecibo, setPreviewRecibo] = useState(null);
    const [modalFirmarRecibo, setModalFirmarRecibo] = useState(null);

    const reciboFirmado = (registro) => !!registro?.firma_colaborador_path;

    const urlsRecibo = (registro) => {
        const nombre = nombreCompletoColaborador(registro.colaborador || {});
        return {
            previewUrl: route('rh.deducciones.recibo.vista_previa', registro.id),
            downloadUrl: route('rh.deducciones.recibo', registro.id),
            titulo: `Recibo — ${nombre}`,
            nombreArchivo: `Recibo_${nombre.replace(/\s+/g, '_')}_${registro.folio}.pdf`,
        };
    };

    const abrirNuevo = () => {
        setRegistroEditando(null);
        setModalAbierto(true);
    };

    const abrirEditar = (registro) => {
        if (registro.estado_deduccion === 'aplicado') return;
        setRegistroEditando(registro);
        setModalAbierto(true);
    };

    const abrirRecibo = (registro) => {
        if (!reciboFirmado(registro)) {
            setModalFirmarRecibo(registro);
            return;
        }
        setPreviewRecibo(urlsRecibo(registro));
    };

    const irAPagina = (pagina) => {
        if (pagina < 1 || pagina > registros.last_page) return;
        router.get(rutaListado(rama), { ...filtros, page: pagina }, { preserveState: true, preserveScroll: true });
    };

    const kpis = [
        { label: 'Deducciones hoy', value: metricas.registros_hoy, accent: true },
        { label: 'Pendientes', value: metricas.pendientes_deduccion, color: '#f59e0b' },
        { label: 'Total periodo', value: formatoDeduccionDecimal(metricas.total_deduccion_periodo), color: '#ef4444' },
        { label: 'Monto pendiente', value: formatoDeduccionDecimal(metricas.monto_pendiente), color: '#3b82f6' },
    ];

    return (
        <AppLayout auth={auth}>
            <Head title={`${tituloHighlight} | RH`} />
            <GeliaPageShell className="space-y-8 relative">
                <RhPageHeader
                    title="Reporte integral de"
                    titleHighlight={tituloHighlight}
                    description={descripcion}
                    icon={AlertTriangle}
                    aside={
                        puedeCrear ? (
                            <button type="button" onClick={abrirNuevo} className="px-5 py-3 rounded-2xl text-[10px] font-black uppercase text-white flex items-center justify-center gap-2" style={{ backgroundColor: 'var(--color-primario)' }}>
                                <Plus className="w-4 h-4" /> Nueva incidencia
                            </button>
                        ) : null
                    }
                />

                <RhSubNav />

                <div className="flex flex-wrap gap-2">
                    <Link
                        href={route('rh.deducciones.incidencias.index')}
                        className={`px-4 py-2 rounded-xl text-[10px] font-black uppercase ${rama === 'incidencias' ? 'text-white' : 'theme-text-muted theme-element border theme-border'}`}
                        style={rama === 'incidencias' ? { backgroundColor: 'var(--color-primario)' } : {}}
                    >
                        Incidencias
                    </Link>
                    <Link
                        href={route('rh.deducciones.pagos_pendientes.index')}
                        className={`px-4 py-2 rounded-xl text-[10px] font-black uppercase ${rama === 'pagos_pendientes' ? 'text-white' : 'theme-text-muted theme-element border theme-border'}`}
                        style={rama === 'pagos_pendientes' ? { backgroundColor: 'var(--color-primario)' } : {}}
                    >
                        Pagos y pendientes
                    </Link>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {kpis.map(({ label, value, color, accent }) => (
                        <div key={label} className={geliaCardClass('p-5')}>
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1">{label}</p>
                            <p className="text-xl font-black italic m-0" style={accent ? { color: 'var(--color-primario)' } : { color }}>{value}</p>
                        </div>
                    ))}
                </div>

                <div className={geliaCardClass('overflow-hidden')}>
                    <FiltrosDeducciones
                        filtros={filtros}
                        colaboradores={colaboradores}
                        reglasIncidencia={reglasIncidencia}
                        departamentos={departamentos}
                        tabActiva={tabActiva}
                        onCambiarTab={setTabActiva}
                        rama={rama}
                    />

                    {registros.data.length === 0 ? (
                        <div className="py-16 text-center">
                            <AlertTriangle className="w-10 h-10 mx-auto mb-3 opacity-40 theme-text-muted" />
                            <p className="theme-text-muted italic text-sm m-0">No hay registros con estos filtros.</p>
                        </div>
                    ) : (
                        <>
                            <div className="lg:hidden divide-y theme-border">
                                {registros.data.map((reg) => (
                                    <div key={reg.id} className="p-4 space-y-2">
                                        <div className="flex justify-between items-start gap-2">
                                            <div>
                                                <p className="text-xs font-mono font-bold theme-text-main m-0">{reg.folio}</p>
                                                <p className="text-sm font-bold theme-text-main m-0 mt-1">{nombreCompletoColaborador(reg.colaborador)}</p>
                                                <p className="text-[10px] theme-text-muted m-0">{reg.regla_nombre_snapshot || reg.regla_incidencia?.nombre}</p>
                                            </div>
                                            <span className={`px-2 py-1 rounded-lg text-[9px] font-black uppercase border shrink-0 ${ESTADO_DEDUCCION_BADGE[reg.estado_deduccion]}`}>
                                                {ESTADO_DEDUCCION_LABELS[reg.estado_deduccion]}
                                            </span>
                                        </div>
                                        <div className="grid grid-cols-2 gap-2 text-[10px]">
                                            <div><span className="theme-text-muted uppercase font-black">Fecha:</span> {reg.fecha_ocurrencia?.slice?.(0, 10)}</div>
                                            <div><span className="theme-text-muted uppercase font-black">Total:</span> {formatoMoneda(reg.monto_total_final || reg.total_deduccion)}</div>
                                            <div><span className="theme-text-muted uppercase font-black">Origen:</span> {ORIGEN_DEDUCCION_LABELS[reg.origen_deduccion] || reg.origen_deduccion}</div>
                                            <div><span className="theme-text-muted uppercase font-black">Auditor:</span> {reg.registrado_por?.name || '—'}</div>
                                        </div>
                                        <FilaAcciones reg={reg} puedeEditar={puedeEditar} puedeRecibos={puedeRecibos} onEditar={abrirEditar} onRecibo={abrirRecibo} reciboFirmado={reciboFirmado} />
                                    </div>
                                ))}
                            </div>

                            <div className="hidden lg:block overflow-x-auto">
                                <table className="w-full border-collapse">
                                    <thead>
                                        <tr className="border-b theme-border">
                                            {['Folio', 'Fecha', 'Colaborador', 'Concepto', 'Total', 'Origen', 'Estado', 'Auditor', ''].map((h) => (
                                                <th key={h} className="px-4 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">{h}</th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {registros.data.map((reg) => (
                                            <tr key={reg.id} className="border-b theme-border last:border-0 hover:bg-black/[0.02] dark:hover:bg-white/[0.02]">
                                                <td className="px-4 py-4 text-xs font-mono font-bold">{reg.folio}</td>
                                                <td className="px-4 py-4 text-xs">{reg.fecha_ocurrencia?.slice?.(0, 10)}</td>
                                                <td className="px-4 py-4 text-sm font-bold">{nombreCompletoColaborador(reg.colaborador)}</td>
                                                <td className="px-4 py-4 text-xs">{reg.regla_nombre_snapshot || reg.regla_incidencia?.nombre}</td>
                                                <td className="px-4 py-4 text-sm font-bold">{formatoMoneda(reg.monto_total_final || reg.total_deduccion)}</td>
                                                <td className="px-4 py-4 text-xs">{ORIGEN_DEDUCCION_LABELS[reg.origen_deduccion] || reg.origen_deduccion}</td>
                                                <td className="px-4 py-4">
                                                    <span className={`px-2 py-1 rounded-lg text-[9px] font-black uppercase border ${ESTADO_DEDUCCION_BADGE[reg.estado_deduccion]}`}>
                                                        {ESTADO_DEDUCCION_LABELS[reg.estado_deduccion]}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-4 text-xs">{reg.registrado_por?.name || '—'}</td>
                                                <td className="px-4 py-4 text-right whitespace-nowrap">
                                                    <FilaAcciones reg={reg} puedeEditar={puedeEditar} puedeRecibos={puedeRecibos} onEditar={abrirEditar} onRecibo={abrirRecibo} reciboFirmado={reciboFirmado} />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}

                    {registros.data.length > 0 && (
                        <GeliaPaginacion paginator={registros} onIrAPagina={irAPagina} embedded />
                    )}
                </div>
            </GeliaPageShell>

            <ModalFormDeduccion
                abierto={modalAbierto}
                onCerrar={() => { setModalAbierto(false); setRegistroEditando(null); }}
                registro={registroEditando}
                colaboradores={colaboradores}
                reglasIncidencia={reglasIncidencia}
                usuarioActual={usuarioActual}
            />

            <ModalVistaPreviaRecibo
                abierto={!!previewRecibo}
                onCerrar={() => setPreviewRecibo(null)}
                previewUrl={previewRecibo?.previewUrl}
                downloadUrl={previewRecibo?.downloadUrl}
                titulo={previewRecibo?.titulo}
                nombreArchivo={previewRecibo?.nombreArchivo}
            />
            <ModalFirmarReciboDeduccion
                abierto={!!modalFirmarRecibo}
                onCerrar={() => setModalFirmarRecibo(null)}
                registro={modalFirmarRecibo}
                firmarUrl={modalFirmarRecibo ? route('rh.deducciones.recibo.firmar', modalFirmarRecibo.id) : null}
                requiereFirmaGerente={!!modalFirmarRecibo?.catalogo_regla_incidencia_id}
                onFirmado={(registro) => setPreviewRecibo(urlsRecibo({ ...registro, firma_colaborador_path: registro.firma_colaborador_path || 'ok' }))}
            />
        </AppLayout>
    );
}
