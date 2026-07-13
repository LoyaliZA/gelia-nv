import React, { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Plus, FileSpreadsheet, Package } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPageShell from '../../Components/GeliaPageShell';
import { geliaCardClass } from '../../utils/geliaTheme';
import FiltrosPedidos from './Partials/FiltrosPedidos';
import TablaPedidos from './Partials/TablaPedidos';
import ModalFormPedido from './Partials/ModalFormPedido';
import ModalDetallePedido from './Partials/ModalDetallePedido';
import ModalBitacoraPedido from './Partials/ModalBitacoraPedido';
import ModalAlertaPedido from './Partials/ModalAlertaPedido';
import ModalConfirmarAccion from './Partials/ModalConfirmarAccion';
import { BTN_PRIMARY, BTN_SECONDARY } from './Partials/pedidosBmaStyles';

const PROPS_LISTADO = ['pedidos', 'metricas', 'filtros'];

export default function Index({ auth, pedidos, metricas = {}, filtros = {}, catalogos = {} }) {
    const { flash } = usePage().props;
    const permisos = auth?.user?.permissions || [];
    const can = (permiso) => permisos.includes(permiso) || auth?.user?.roles?.includes('Super Admin');

    const [tabActiva, setTabActiva] = useState(filtros.tab || 'TODAS');
    const [modalForm, setModalForm] = useState({ abierto: false, pedido: null });
    const [modalDetalle, setModalDetalle] = useState({ abierto: false, pedido: null });
    const [modalBitacora, setModalBitacora] = useState({ abierto: false, pedido: null });
    const [pedidoAEliminar, setPedidoAEliminar] = useState(null);
    const [alerta, setAlerta] = useState({ abierto: false, tipo: 'success', titulo: '', mensaje: '' });
    const debounceBusqueda = useRef(null);

    useEffect(() => {
        if (flash?.success) {
            setAlerta({ abierto: true, tipo: 'success', titulo: 'Operación exitosa', mensaje: flash.success });
        } else if (flash?.error) {
            setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: flash.error });
        }
    }, [flash?.success, flash?.error]);

    const onTabChange = (tab) => {
        setTabActiva(tab);
        router.get(route('control_pedidos.index'), { tab, q: filtros.q || '' }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: PROPS_LISTADO,
        });
    };

    const onBuscar = (valor) => {
        if (debounceBusqueda.current) clearTimeout(debounceBusqueda.current);
        debounceBusqueda.current = setTimeout(() => {
            router.get(route('control_pedidos.index'), { tab: tabActiva, q: valor }, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: PROPS_LISTADO,
            });
        }, 400);
    };

    const abrirNuevo = () => setModalForm({ abierto: true, pedido: null });
    const abrirEditar = (pedido) => setModalForm({ abierto: true, pedido });
    const abrirVer = (pedido) => setModalDetalle({ abierto: true, pedido });
    const abrirBitacora = (pedido) => setModalBitacora({ abierto: true, pedido });

    const confirmarEliminar = () => {
        if (!pedidoAEliminar) return;
        const id = pedidoAEliminar.id;
        setPedidoAEliminar(null);
        router.delete(route('control_pedidos.destroy', id), { preserveScroll: true });
    };

    const exportarCsv = () => {
        window.location.href = route('control_pedidos.exportar', { tab: tabActiva, q: filtros.q || '' });
    };

    const etiquetaEliminar = pedidoAEliminar?.folio_remision || pedidoAEliminar?.folio || 'este borrador';

    return (
        <AppLayout auth={auth}>
            <Head title="Gestión de pedidos | GELIANV" />
            <GeliaPageShell className="space-y-6">
                <header className={`${geliaCardClass()} p-6 md:p-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4`}>
                    <div>
                        <div className="flex items-center gap-2 mb-2">
                            <Package className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                            <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Control de pedidos_</span>
                        </div>
                        <h1 className="text-3xl font-black italic uppercase tracking-tighter theme-text-main m-0">
                            Gestión de <span style={{ color: 'var(--color-primario)' }}>pedidos</span>
                        </h1>
                    </div>
                    <div className="flex flex-wrap gap-3">
                        {can('control_pedidos.exportar') && (
                            <button type="button" onClick={exportarCsv} className={`${BTN_SECONDARY} flex items-center gap-2 outline-none`}>
                                <FileSpreadsheet className="w-4 h-4" /> Exportar CSV
                            </button>
                        )}
                        {can('control_pedidos.crear') && (
                            <button type="button" onClick={abrirNuevo} className={`${BTN_PRIMARY} flex items-center gap-2 outline-none`}>
                                <Plus className="w-4 h-4" /> Nuevo pedido
                            </button>
                        )}
                    </div>
                </header>

                <div className={`${geliaCardClass()} p-5`}>
                    <FiltrosPedidos
                        filtros={filtros}
                        tabActiva={tabActiva}
                        onTabChange={onTabChange}
                        onBuscar={onBuscar}
                        metricas={metricas}
                    />
                </div>

                <TablaPedidos
                    pedidos={pedidos}
                    can={can}
                    onVer={abrirVer}
                    onBitacora={abrirBitacora}
                    onEditar={abrirEditar}
                    onEliminar={setPedidoAEliminar}
                />
            </GeliaPageShell>

            <ModalFormPedido
                key={modalForm.pedido?.id ?? 'new'}
                abierto={modalForm.abierto}
                pedido={modalForm.pedido}
                catalogos={catalogos}
                onClose={() => setModalForm({ abierto: false, pedido: null })}
            />
            <ModalDetallePedido
                abierto={modalDetalle.abierto}
                pedido={modalDetalle.pedido}
                onClose={() => setModalDetalle({ abierto: false, pedido: null })}
            />
            <ModalBitacoraPedido
                abierto={modalBitacora.abierto}
                pedido={modalBitacora.pedido}
                onClose={() => setModalBitacora({ abierto: false, pedido: null })}
            />
            <ModalConfirmarAccion
                abierto={Boolean(pedidoAEliminar)}
                titulo="Eliminar borrador"
                mensaje={`¿Eliminar el borrador ${etiquetaEliminar}?`}
                etiquetaConfirmar="Eliminar"
                variante="danger"
                onClose={() => setPedidoAEliminar(null)}
                onConfirm={confirmarEliminar}
            />
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
