import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Package, Download } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass } from '../../../utils/geliaTheme';

export default function Index({ auth, traspasos = [], fecha }) {
    const [fechaLocal, setFechaLocal] = useState(fecha);

    const aplicar = () => {
        router.get(route('reportes.traspasos_dia.index'), { fecha: fechaLocal }, {
            preserveState: true,
            replace: true,
        });
    };

    const exportar = (formato) => {
        window.location.href = route('reportes.traspasos_dia.exportar', { fecha: fechaLocal, formato });
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Traspasos planificados | GELIA" />
            <GeliaPageShell>
                <header className={geliaCardClass('p-6 md:p-10 flex flex-col lg:flex-row justify-between gap-6')}>
                    <div className="space-y-3">
                        <div className="flex items-center gap-3">
                            <div className="w-8 h-1.5 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }} />
                            <span className="text-[10px] font-black tracking-[0.2em] uppercase theme-text-muted">Reportes_</span>
                        </div>
                        <h1 className="text-3xl md:text-4xl font-black italic tracking-tighter uppercase theme-text-main leading-none m-0">
                            Traspasos <span style={{ color: 'var(--color-primario)' }}>planificados</span>
                        </h1>
                        <p className="text-xs font-bold theme-text-muted m-0">Por fecha de entrega estimada (por defecto: mañana).</p>
                    </div>
                    <div className="flex flex-wrap items-end gap-3">
                        <label className="text-[9px] font-black uppercase theme-text-muted">
                            Fecha entrega
                            <input
                                type="date"
                                value={fechaLocal}
                                onChange={(e) => setFechaLocal(e.target.value)}
                                className="theme-input block mt-1 px-4 py-2.5 font-bold"
                            />
                        </label>
                        <button type="button" onClick={aplicar} className="theme-btn-primary theme-btn-primary--compact">Aplicar</button>
                        <button type="button" onClick={() => exportar('xlsx')} className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-[9px] font-black uppercase theme-element border theme-border">
                            <Download className="w-3.5 h-3.5" /> Excel
                        </button>
                        <button type="button" onClick={() => exportar('csv')} className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-[9px] font-black uppercase theme-element border theme-border">
                            <Download className="w-3.5 h-3.5" /> CSV
                        </button>
                    </div>
                </header>

                <div className={geliaCardClass('overflow-hidden')}>
                    <div className="p-4 border-b theme-border flex items-center gap-2">
                        <Package className="w-4 h-4" style={{ color: 'var(--color-primario)' }} />
                        <span className="text-xs font-black uppercase theme-text-main">{traspasos.length} registro(s)</span>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left">
                            <thead>
                                <tr className="text-[9px] font-black uppercase theme-text-muted border-b theme-border">
                                    <th className="px-4 py-3">Folio</th>
                                    <th className="px-4 py-3">Cliente</th>
                                    <th className="px-4 py-3">Vendedor</th>
                                    <th className="px-4 py-3">Almacén</th>
                                    <th className="px-4 py-3 text-right">Pzas</th>
                                    <th className="px-4 py-3">Estado</th>
                                    <th className="px-4 py-3">Horario</th>
                                </tr>
                            </thead>
                            <tbody>
                                {traspasos.length === 0 && (
                                    <tr>
                                        <td colSpan={7} className="px-4 py-10 text-center text-sm theme-text-muted">Sin traspasos para esta fecha.</td>
                                    </tr>
                                )}
                                {traspasos.map((t) => (
                                    <tr key={t.id} className="border-b theme-border">
                                        <td className="px-4 py-3 font-mono text-xs font-black theme-text-main">{t.folio}</td>
                                        <td className="px-4 py-3 text-xs font-bold theme-text-main">
                                            {t.cliente?.numero_cliente} — {t.cliente?.nombre}
                                        </td>
                                        <td className="px-4 py-3 text-xs font-bold theme-text-main">{t.vendedor?.name}</td>
                                        <td className="px-4 py-3 text-xs font-bold theme-text-main">{t.almacen_origen?.nombre}</td>
                                        <td className="px-4 py-3 text-xs font-black text-right theme-text-main">{t.total_piezas}</td>
                                        <td className="px-4 py-3 text-xs font-bold theme-text-main">{t.estado?.nombre}</td>
                                        <td className="px-4 py-3 text-xs font-bold theme-text-muted">{t.horario?.nombre || '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </GeliaPageShell>
        </AppLayout>
    );
}
