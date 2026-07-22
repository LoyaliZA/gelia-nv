import React, { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { FileSpreadsheet, Package, Search, Truck, Send } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass, GELIA_SEGMENT_TABS_SCROLL, GELIA_SEGMENT_TABS_TRACK } from '../../../utils/geliaTheme';
import { THEME_INPUT, THEME_LABEL } from '../../../utils/geliaTheme';
import TablaDelegado from './Partials/TablaDelegado';
import PanelImportExport from './Partials/PanelImportExport';
import ModalAlertaPedido from '../Partials/ModalAlertaPedido';
import { TABS_DELEGADO } from '../Partials/pedidosBmaStyles';

const PROPS_LISTADO = ['pedidos', 'metricas', 'filtros'];

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

    const tabActiva = filtros.tab || 'PENDIENTES_GUIA';

    const onBuscar = (valor) => {
        if (debounceBusqueda.current) clearTimeout(debounceBusqueda.current);
        debounceBusqueda.current = setTimeout(() => {
            router.get(route('control_pedidos.delegado.index'), { q: valor, tab: tabActiva }, {
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

    const conteoTab = (tabId) => {
        const map = {
            TODOS: metricas.total,
            PENDIENTES_GUIA: metricas.pendientes_guia,
            PENDIENTES_ENVIO: metricas.pendientes_envio,
            ENVIADOS: metricas.enviados,
        };
        return map[tabId];
    };

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
                        Revisa datos, captura o corrige guías, y reporta errores para que la vendedora los corrija.
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
                        {tabActiva === 'PENDIENTES_GUIA' && <PanelImportExport onAlerta={setAlerta} />}
                    </div>
                </div>

                <div className={`${geliaCardClass()} p-5 grid grid-cols-1 sm:grid-cols-3 gap-4`}>
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
                        <Truck className="w-5 h-5 text-sky-500" />
                        <div>
                            <p className="text-[9px] font-black uppercase theme-text-muted m-0">Pendientes de envío</p>
                            <p className="text-2xl font-black m-0 text-sky-500">
                                {metricas.pendientes_envio ?? 0}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        <Send className="w-5 h-5 text-emerald-500" />
                        <div>
                            <p className="text-[9px] font-black uppercase theme-text-muted m-0">Enviados</p>
                            <p className="text-2xl font-black m-0 text-emerald-500">
                                {metricas.enviados ?? 0}
                            </p>
                        </div>
                    </div>
                </div>

                <div className={`${geliaCardClass()} p-2`}>
                    <div className={GELIA_SEGMENT_TABS_SCROLL}>
                        <div className={`gelia-segment ${GELIA_SEGMENT_TABS_TRACK} p-1 shadow-sm`} role="tablist" aria-label="Filtro de guías">
                            {TABS_DELEGADO.map((tab) => {
                                const conteo = conteoTab(tab.id);
                                return (
                                    <button
                                        key={tab.id}
                                        type="button"
                                        role="tab"
                                        aria-selected={tabActiva === tab.id}
                                        onClick={() => onTabChange(tab.id)}
                                        className="gelia-segment-btn whitespace-nowrap gap-1.5"
                                        data-active={tabActiva === tab.id}
                                    >
                                        {tab.label}
                                        {conteo !== undefined && (
                                            <span className="text-[9px] font-black px-1.5 py-0.5 rounded-md theme-element border theme-border">
                                                {conteo}
                                            </span>
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                </div>

                <TablaDelegado pedidos={pedidos} tabActiva={tabActiva} />
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
