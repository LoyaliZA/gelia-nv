import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Building2, MapPin, ListTree, Tags, Activity, UserCheck, Map, Clock, Percent, TrendingUp, Landmark, Package, Warehouse, Boxes, Truck, Box } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import { geliaCardClass } from '../../utils/geliaTheme';

import TablaDepartamentos from './Partials/Catalogos/TablaDepartamentos';
import TablaAreas from './Partials/Catalogos/TablaAreas';
import TablaProcesos from './Partials/Catalogos/TablaProcesos';
import TablaListas from './Partials/Catalogos/TablaListas';
import TablaEstados from './Partials/Catalogos/TablaEstados';
import TablaTipoClientes from './Partials/Catalogos/TablaTipoClientes';
import TablaZonasEntrega from './Partials/Catalogos/TablaZonasEntrega';
import TablaHorariosEntrega from './Partials/Catalogos/TablaHorariosEntrega';
import TablaPorcentajesEscalonamiento from './Partials/Catalogos/TablaPorcentajesEscalonamiento';
import TablaPorcentajesListado from './Partials/Catalogos/TablaPorcentajesListado';
import TablaBancos from './Partials/Catalogos/TablaBancos';
import TablaTiposActivo from './Partials/Catalogos/TablaTiposActivo';
import TablaCategoriasActivo from './Partials/Catalogos/TablaCategoriasActivo';
import TablaSucursales from './Partials/Catalogos/TablaSucursales';
import TablaTiposAlmacen from './Partials/Catalogos/TablaTiposAlmacen';
import TablaMarcasProducto from './Partials/Catalogos/TablaMarcasProducto';
import TablaAlmacenes from './Partials/Catalogos/TablaAlmacenes';
import TablaCategoriasProducto from './Partials/Catalogos/TablaCategoriasProducto';
import TablaEstatusPedidos from './Partials/Catalogos/TablaEstatusPedidos';
import TablaCatalogoPedidoGenerico from './Partials/Catalogos/TablaCatalogoPedidoGenerico';
import TablaTiposCajaPedido from './Partials/Catalogos/TablaTiposCajaPedido';
import TablaPaqueteriasPedido from './Partials/Catalogos/TablaPaqueteriasPedido';


export default function Catalogos({
    auth, procesos, listas, estados, departamentos, areas, tipos_cliente,
    zonas_entrega, horarios_entrega, porcentajes_escalonamiento = [], porcentajes_listado = [],
    bancos = [], tipos_activo = [], categorias_activo = [],
    sucursales = [], tipos_almacen = [], marcas_producto = [], almacenes = [], categorias_producto = [],
    estatus_pedidos = [], almacenes_salida = [], paqueterias_pedido = [], tipos_caja_pedido = [],
    tipos_guia_pedido = [], zonas_pedido = [], envios_tienda = [],
}) {
    const [tabActiva, setTabActiva] = useState('departamentos');
    const activeCardClass = geliaCardClass('relative z-10');

    const tabs = [
        { id: 'tipos_cliente', label: 'Tipos Cliente', icon: UserCheck },
        { id: 'departamentos', label: 'Departamentos', icon: Building2 },
        { id: 'areas', label: 'Áreas', icon: MapPin },
        { id: 'sucursales', label: 'Sucursales', icon: Building2 },
        { id: 'tipos_almacen', label: 'Tipos Almacén', icon: Warehouse },
        { id: 'almacenes', label: 'Almacenes', icon: Boxes },
        { id: 'marcas_producto', label: 'Marcas', icon: Tags },
        { id: 'categorias_producto', label: 'Categorías Producto', icon: Package },
        { id: 'procesos', label: 'Procesos', icon: ListTree },
        { id: 'listas', label: 'Listas', icon: Tags },
        { id: 'porcentajes_escalonamiento', label: 'Escalonamiento', icon: TrendingUp },
        { id: 'porcentajes_listado', label: 'Listados', icon: Percent },
        { id: 'estados', label: 'Estados', icon: Activity },
        { id: 'bancos', label: 'Bancos', icon: Landmark },
        { id: 'tipos_activo', label: 'Tipos Activo', icon: Package },
        { id: 'categorias_activo', label: 'Categorías Activo', icon: Tags },
        { id: 'zonas_entrega', label: 'Zonas Logísticas', icon: Map },
        { id: 'horarios_entrega', label: 'Horarios Entrega', icon: Clock },
        { id: 'envios_tienda', label: 'Envíos / Tienda', icon: Truck },
        { id: 'estatus_pedidos', label: 'Estatus Pedidos', icon: Activity },
        { id: 'almacenes_salida', label: 'Almacenes Salida', icon: Warehouse },
        { id: 'paqueterias_pedido', label: 'Paqueterías', icon: Truck },
        { id: 'tipos_caja_pedido', label: 'Tipos Caja', icon: Box },
        { id: 'tipos_guia_pedido', label: 'Tipos Guía', icon: Map },
        { id: 'zonas_pedido', label: 'Zonas Pedido', icon: MapPin },
    ];

    return (
        <AppLayout auth={auth}>
            <Head title="Catálogos | GELIANV" />

            <div className="max-w-[1400px] w-full mx-auto p-4 md:p-8 space-y-8 relative">
                <header className={`${activeCardClass} p-8 md:p-12 flex flex-col md:flex-row justify-between items-start md:items-center gap-8`}>
                    <div className="space-y-3">
                        <div className="flex items-center gap-3">
                            <div className="w-8 h-1.5 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }} />
                            <span className="text-[10px] font-black tracking-[0.2em] uppercase theme-text-muted">Estructura de Datos_</span>
                        </div>
                        <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main leading-none m-0">
                            GESTIÓN DE <span style={{ color: 'var(--color-primario)' }}>CATÁLOGOS</span>
                        </h1>
                    </div>
                </header>

                <div className={`${activeCardClass} p-2 flex flex-wrap gap-2`}>
                    {tabs.map((tab) => (
                        <button
                            key={tab.id}
                            onClick={() => setTabActiva(tab.id)}
                            className={`flex-1 flex items-center justify-center gap-2 py-4 rounded-[1.5rem] text-[10px] font-black uppercase tracking-widest transition-all outline-none min-w-[120px] ${tabActiva === tab.id ? 'text-white shadow-lg' : 'theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5'}`}
                            style={tabActiva === tab.id ? { backgroundColor: 'var(--color-primario)' } : {}}
                        >
                            <tab.icon className="w-4 h-4" /> {tab.label}
                        </button>
                    ))}
                </div>

                <section className={`${activeCardClass} overflow-hidden`}>
                    {tabActiva === 'departamentos' && <TablaDepartamentos datos={departamentos} />}
                    {tabActiva === 'areas' && <TablaAreas datos={areas} departamentos={departamentos} />}
                    {tabActiva === 'sucursales' && <TablaSucursales datos={sucursales} />}
                    {tabActiva === 'tipos_almacen' && <TablaTiposAlmacen datos={tipos_almacen} />}
                    {tabActiva === 'almacenes' && <TablaAlmacenes datos={almacenes} sucursales={sucursales} tipos_almacen={tipos_almacen} />}
                    {tabActiva === 'marcas_producto' && <TablaMarcasProducto datos={marcas_producto} />}
                    {tabActiva === 'categorias_producto' && <TablaCategoriasProducto datos={categorias_producto} />}
                    {tabActiva === 'procesos' && <TablaProcesos datos={procesos} />}
                    {tabActiva === 'listas' && <TablaListas datos={listas} />}
                    {tabActiva === 'porcentajes_escalonamiento' && <TablaPorcentajesEscalonamiento datos={porcentajes_escalonamiento} listas={listas} />}
                    {tabActiva === 'porcentajes_listado' && <TablaPorcentajesListado datos={porcentajes_listado} listas={listas} />}
                    {tabActiva === 'estados' && <TablaEstados datos={estados} />}
                    {tabActiva === 'bancos' && <TablaBancos datos={bancos} />}
                    {tabActiva === 'tipos_activo' && <TablaTiposActivo datos={tipos_activo} />}
                    {tabActiva === 'categorias_activo' && <TablaCategoriasActivo datos={categorias_activo} />}
                    {tabActiva === 'tipos_cliente' && <TablaTipoClientes datos={tipos_cliente} />}
                    {tabActiva === 'zonas_entrega' && <TablaZonasEntrega datos={zonas_entrega} auth={auth} />}
                    {tabActiva === 'horarios_entrega' && <TablaHorariosEntrega datos={horarios_entrega} zonas_entrega={zonas_entrega} auth={auth} />}
                    {tabActiva === 'estatus_pedidos' && <TablaEstatusPedidos datos={estatus_pedidos} />}
                    {tabActiva === 'almacenes_salida' && (
                        <TablaCatalogoPedidoGenerico datos={almacenes_salida} titulo="Almacenes Salida_" icon={Warehouse} routePrefix="almacenes_salida" loaderMessage="Guardando Almacén_" />
                    )}
                    {tabActiva === 'envios_tienda' && (
                        <TablaCatalogoPedidoGenerico datos={envios_tienda} titulo="Envíos / Tienda_" icon={Truck} routePrefix="envios_tienda" loaderMessage="Guardando Envío_" />
                    )}
                    {tabActiva === 'paqueterias_pedido' && (
                        <TablaPaqueteriasPedido datos={paqueterias_pedido} />
                    )}
                    {tabActiva === 'tipos_caja_pedido' && <TablaTiposCajaPedido datos={tipos_caja_pedido} />}
                    {tabActiva === 'tipos_guia_pedido' && (
                        <TablaCatalogoPedidoGenerico datos={tipos_guia_pedido} titulo="Tipos de Guía_" icon={Map} routePrefix="tipos_guia_pedido" loaderMessage="Guardando Guía_" />
                    )}
                    {tabActiva === 'zonas_pedido' && (
                        <TablaCatalogoPedidoGenerico datos={zonas_pedido} titulo="Zonas Pedido_" icon={MapPin} routePrefix="zonas_pedido" loaderMessage="Guardando Zona_" />
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
