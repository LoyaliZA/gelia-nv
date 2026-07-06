import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Plus, Eye, Printer } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { formatoMoneda, nombreCompletoColaborador } from '../../../utils/formatoMoneda';
import RhSubNav from '../Partials/RhSubNav';
import RhPageHeader from '../Partials/RhPageHeader';
import ModalVistaPreviaRecibo from '../Partials/ModalVistaPreviaRecibo';

export default function Index({ auth, registros, colaboradores, filtros, puedeCrear, puedeRecibos }) {
    const [previewRecibo, setPreviewRecibo] = useState(null);

    const irAPagina = (pagina) => {
        if (pagina < 1 || pagina > registros.last_page) return;
        router.get(route('rh.incidencias_gerente.index'), { ...filtros, page: pagina }, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Mis Incidencias | RH" />
            <GeliaPageShell className="space-y-6">
                <RhPageHeader
                    title="Registro de"
                    titleHighlight="Incidencias"
                    description="Colaboradores asignados a su equipo"
                    icon={AlertTriangle}
                    aside={
                        puedeCrear ? (
                            <Link href={route('rh.incidencias_gerente.create')} className="px-5 py-3 rounded-2xl text-[10px] font-black uppercase text-white flex items-center gap-2" style={{ backgroundColor: 'var(--color-primario)' }}>
                                <Plus className="w-4 h-4" /> Nueva incidencia
                            </Link>
                        ) : null
                    }
                />
                <RhSubNav />

                <div className={geliaCardClass('overflow-hidden')}>
                    {registros.data.length === 0 ? (
                        <div className="py-16 text-center theme-text-muted text-sm">No hay incidencias registradas.</div>
                    ) : (
                        <>
                            <div className="divide-y theme-border">
                                {registros.data.map((reg) => (
                                    <div key={reg.id} className="p-4 flex flex-wrap justify-between gap-3">
                                        <div>
                                            <p className="text-xs font-mono font-bold m-0">{reg.folio}</p>
                                            <p className="text-sm font-bold m-0 mt-1">{nombreCompletoColaborador(reg.colaborador)}</p>
                                            <p className="text-[10px] theme-text-muted m-0">{reg.regla_nombre_snapshot} · {reg.fecha_ocurrencia?.slice?.(0, 10)}</p>
                                            <p className="text-sm font-bold m-0 mt-1">{formatoMoneda(reg.monto_total_final || reg.total_deduccion)}</p>
                                        </div>
                                        <div className="flex flex-wrap gap-2 items-start">
                                            <Link href={route('rh.incidencias_gerente.deducciones.show', reg.id)} className="text-[10px] font-black uppercase inline-flex items-center gap-1" style={{ color: 'var(--color-primario)' }}>
                                                <Eye className="w-3.5 h-3.5" /> Ver
                                            </Link>
                                            {puedeRecibos && (
                                                <button type="button" onClick={() => setPreviewRecibo({
                                                    previewUrl: route('rh.incidencias_gerente.deducciones.recibo.vista_previa', reg.id),
                                                    downloadUrl: route('rh.incidencias_gerente.deducciones.recibo', reg.id),
                                                    titulo: `Recibo — ${reg.folio}`,
                                                })} className="text-[10px] font-black uppercase inline-flex items-center gap-1 theme-text-muted">
                                                    <Printer className="w-3.5 h-3.5" /> Recibo
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                            <GeliaPaginacion paginator={registros} onIrAPagina={irAPagina} embedded />
                        </>
                    )}
                </div>
            </GeliaPageShell>

            <ModalVistaPreviaRecibo
                abierto={!!previewRecibo}
                onCerrar={() => setPreviewRecibo(null)}
                previewUrl={previewRecibo?.previewUrl}
                downloadUrl={previewRecibo?.downloadUrl}
                titulo={previewRecibo?.titulo}
            />
        </AppLayout>
    );
}
