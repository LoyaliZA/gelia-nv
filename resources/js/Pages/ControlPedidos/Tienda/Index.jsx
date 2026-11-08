import useActualizacionesPedidos from '../Partials/useActualizacionesPedidos';
import React, { useEffect, useRef, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { Store } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass, GELIA_ICON_BOX } from '../../../utils/geliaTheme';
import FiltrosTienda, { TABS_TIENDA } from './Partials/FiltrosTienda';
import MetricasBandejaTienda from './Partials/MetricasBandejaTienda';
import useFiltrosOperacion from '../Partials/useFiltrosOperacion';
import ListadoTienda from './Partials/ListadoTienda';
import ModalPreparacionTienda from './Partials/ModalPreparacionTienda';
import AvisoResultadoTienda from './Partials/AvisoResultadoTienda';
import useListadoDiscreto from '../Partials/useListadoDiscreto';


export default function Index({
    auth,
    tareas,
    metricas = {},
    cola_estados_cuenta = { total: 0, casos: [] },
    filtros = {},
    fecha_hoy,
}) {
    const { flash } = usePage().props;
    const [modalTarea, setModalTarea] = useState(null);
    const tareaDesdeFiltro = useRef(null);
    const abrirPreparacion = (tarea, atender = false) => setModalTarea({ id: tarea.id, atender });
    const {
        tareas: tareasVista,
        metricas: metricasVista,
        cargando,
        errorListado,
        cargar,
    } = useListadoDiscreto({
        listadoRoute: 'control_pedidos.tienda.listado',
        indexRoute: 'control_pedidos.tienda.index',
        tareas,
        metricas,
    });

    const { valores, params: paramsListado, cambiar, limpiar } = useFiltrosOperacion(filtros, cargar);
    const tabActiva = valores.tab;

    const tabLabel = (TABS_TIENDA.find((t) => t.id === tabActiva) || TABS_TIENDA[0]).label;

    useActualizacionesPedidos({ onActualizar: () => onActualizar(), pausado: Boolean(cargando) });


    useEffect(() => {
        if (filtros.tarea && tareasVista?.data?.length) {
            const t = tareasVista.data.find((x) => String(x.id) === String(filtros.tarea));
            if (t && tareaDesdeFiltro.current !== filtros.tarea) {
                tareaDesdeFiltro.current = filtros.tarea;
                abrirPreparacion(t);
            }
        }
    }, [filtros.tarea, tareasVista]);

    const onTabChange = tab => cambiar({ tab });
    const onLimpiarFiltros = limpiar;

    const onActualizar = () => {
        cargar(paramsListado({ page: tareasVista?.current_page || 1 }));
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Preparación Tienda | GELIANV" />
            <GeliaPageShell className="gelia-tienda-op space-y-4 md:space-y-6">
                <a className="text-primario underline text-sm" href={route('traspasos.abastecimiento.index')}>Abastecimiento a preparación en PDV</a>
                <header className={`${geliaCardClass()} p-4 sm:p-5`}>
                    <div className="flex items-center gap-3">
                        <span className={`${GELIA_ICON_BOX} p-3 rounded-xl`} aria-hidden="true"><Store className="w-6 h-6" /></span>
                        <div className="min-w-0">
                            <h1 className="text-xl md:text-2xl font-bold theme-text-main m-0">Preparación Tienda</h1>
                            <p className="text-sm theme-text-muted mt-1 m-0">Pedidos de tu sucursal</p>
                        </div>
                    </div>
                </header>

                <AvisoResultadoTienda flash={flash} />

                {cola_estados_cuenta?.total > 0 && (
                    <div
                        className={`${geliaCardClass()} p-4 theme-element border theme-border`}
                        role="status"
                    >
                        <p className="text-sm font-bold gelia-estado-vivo gelia-estado-vivo--compacto gelia-estado-vivo--aviso inline-flex m-0 mb-2">
                            Estados de cuenta en Caja
                        </p>
                        <p className="text-sm theme-text-muted font-medium m-0">
                            {cola_estados_cuenta.total} caso(s) con comprobante pendiente de conciliar.
                            La separación en Tienda puede continuar.
                        </p>
                    </div>
                )}

                <div className={`${geliaCardClass()} gelia-tienda-op-workspace p-3 sm:p-5 space-y-4`}>
                    <MetricasBandejaTienda
                        metricas={metricasVista}
                        tabActiva={tabActiva}
                        onTabChange={onTabChange}
                    />
                    <div className="gelia-tienda-op-divider" aria-hidden />
                    {errorListado && (
                        <p className="text-sm font-medium m-0 theme-text-peligro" role="alert">{errorListado}</p>
                    )}
                    <FiltrosTienda fechaHoy={fecha_hoy} valores={valores} onCambiar={cambiar} onLimpiar={limpiar} onActualizar={onActualizar} buscando={cargando} />
                </div>

                <ListadoTienda
                    onAbrir={abrirPreparacion}
                    tareas={tareasVista}
                    auth={auth}
                    tabLabel={tabLabel}
                    buscando={cargando}
                    hayFiltrosActivos={Boolean(valores.q || valores.vendedora || valores.desde || valores.hasta) || tabActiva !== 'TODOS'}
                    onLimpiarFiltros={onLimpiarFiltros}
                    onIrAPagina={(page) => cargar(paramsListado({ page }))}
                />
            </GeliaPageShell>
            {modalTarea && <ModalPreparacionTienda key={modalTarea.id} tareaId={modalTarea.id} atender={modalTarea.atender} auth={auth}
                onClose={() => { setModalTarea(null); onActualizar(); }} onActualizado={onActualizar} />}
        </AppLayout>
    );
}
