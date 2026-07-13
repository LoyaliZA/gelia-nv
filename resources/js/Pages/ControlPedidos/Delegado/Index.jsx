import React, { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { FileSpreadsheet, Package, Search, Pencil } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass, GELIA_SEGMENT_TABS_SCROLL, GELIA_SEGMENT_TABS_TRACK } from '../../../utils/geliaTheme';
import { THEME_INPUT, THEME_LABEL } from '../../../utils/geliaTheme';
import TablaDelegado from './Partials/TablaDelegado';
import PanelImportExport from './Partials/PanelImportExport';
import ModalAlertaPedido from '../Partials/ModalAlertaPedido';

const PROPS_LISTADO = ['pedidos', 'metricas', 'filtros'];

const TABS_DELEGADO = [
    { id: 'PENDIENTES_GUIA', label: 'Pendientes de guía' },
    { id: 'CORRECCION', label: 'Corrección pre-envío' },
];

export default function Index({ auth, pedidos, metricas = {}, filtros = {} }) {
    const { flash } = usePage().props;
    const [alerta, setAlerta] = useState({ abierto: false, tipo: 'success', titulo: '', mensaje: '' });
    const debounceBusqueda = useRef(null);

    useEffect(() => {
        if (flash?.success) {
            setAlerta({ abierto: true, tipo: 'success', titulo: 'Operación exitosa', mensaje: flash.success });
        } else if (flash?.error) {
            setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: flash.error });
        }
    }, [flash?.success, flash?.error]);

    const onBuscar = (valor) => {
        if (debounceBusqueda.current) clearTimeout(debounceBusqueda.current);
        debounceBusqueda.current = setTimeout(() => {
            router.get(route('control_pedidos.delegado.index'), { q: valor, tab: filtros.tab || 'PENDIENTES_GUIA' }, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: PROPS_LISTADO,
            });
        }, 400);
    };

    const onTabChange = (tab) => {
        router.get(route('control_pedidos.delegado.index'), { tab, q: filtros.q || '' }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: PROPS_LISTADO,
        });
    };

    const tabActiva = filtros.tab || 'PENDIENTES_GUIA';
    const modo = tabActiva === 'CORRECCION' ? 'correccion' : 'asignar';

    return (
        <AppLayout auth={auth}>
            <Head title="Actualizar guías | GELIANV" />
            <GeliaPageShell className="space-y-6">
                <header className={`${geliaCardClass()} p-6 md:p-8`}>
                    <div className="flex items-center gap-2 mb-2">
                        <FileSpreadsheet className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                        <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Control de pedidos_</span>
                    </div>
                    <h1 className="text-3xl font-black italic uppercase tracking-tighter theme-text-main m-0">
                        Actualizar <span style={{ color: 'var(--color-primario)' }}>guías</span>
                    </h1>
                    <p className="text-sm theme-text-muted font-bold mt-2 m-0">
                        Captura guías pedido por pedido o exporta/importa CSV para actualización masiva.
                    </p>
                </header>

                <div className={`${geliaCardClass()} p-5`}>
                    <div className="flex flex-col lg:flex-row lg:items-end gap-4 justify-between">
                        <div className="flex-1 max-w-md">
                            <label htmlFor="delegado-busqueda" className={`${THEME_LABEL} ml-1`}>Buscar</label>
                            <div className="theme-field-with-icon relative mt-1.5">
                                <Search className="theme-field-icon w-4 h-4" aria-hidden />
                                <input
                                    id="delegado-busqueda"
                                    type="text"
                                    defaultValue={filtros.q || ''}
                                    onChange={(e) => onBuscar(e.target.value)}
                                    placeholder="Buscar folio o cliente..."
                                    className={`${THEME_INPUT} w-full py-3.5 text-sm font-bold`}
                                />
                            </div>
                        </div>
                        {tabActiva !== 'CORRECCION' && <PanelImportExport onAlerta={setAlerta} />}
                    </div>
                </div>

                <div className={`${geliaCardClass()} p-5 grid grid-cols-1 sm:grid-cols-2 gap-4`}>
                    <div className="flex items-center gap-3">
                        <Package className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                        <div>
                            <p className="text-[9px] font-black uppercase theme-text-muted m-0">Pendientes de guía</p>
                            <p className="text-2xl font-black m-0" style={{ color: 'var(--color-primario)' }}>
                                {metricas.pendientes_guia ?? 0}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        <Pencil className="w-5 h-5 text-sky-500" />
                        <div>
                            <p className="text-[9px] font-black uppercase theme-text-muted m-0">Corrección pre-envío</p>
                            <p className="text-2xl font-black m-0 text-sky-500">
                                {metricas.pendientes_correccion ?? 0}
                            </p>
                        </div>
                    </div>
                </div>

                <div className={`${geliaCardClass()} p-2`}>
                    <div className={GELIA_SEGMENT_TABS_SCROLL}>
                        <div className={GELIA_SEGMENT_TABS_TRACK}>
                            {TABS_DELEGADO.map((tab) => (
                                <button
                                    key={tab.id}
                                    type="button"
                                    onClick={() => onTabChange(tab.id)}
                                    className={`px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest outline-none transition-colors ${
                                        tabActiva === tab.id
                                            ? 'bg-[var(--color-primario)] text-white'
                                            : 'theme-text-muted hover:theme-text-main'
                                    }`}
                                >
                                    {tab.label}
                                </button>
                            ))}
                        </div>
                    </div>
                </div>

                <TablaDelegado pedidos={pedidos} modo={modo} />
            </GeliaPageShell>

            <ModalAlertaPedido
                abierto={alerta.abierto}
                tipo={alerta.tipo}
                titulo={alerta.titulo}
                mensaje={alerta.mensaje}
                onClose={() => setAlerta({ ...alerta, abierto: false })}
            />
        </AppLayout>
    );
}
