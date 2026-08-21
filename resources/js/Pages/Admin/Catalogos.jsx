import React, { useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import {
    Building2, MapPin, ListTree, Tags, Activity, UserCheck, Map, Clock, Percent,
    TrendingUp, Landmark, Package, Warehouse, Boxes, Truck, Box, FileText, Receipt, Search,
    Ruler, Flower2, Puzzle,
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import { geliaCardClass, THEME_INPUT } from '../../utils/geliaTheme';

import TablaDepartamentos from './Partials/Catalogos/TablaDepartamentos';
import TablaAreas from './Partials/Catalogos/TablaAreas';
import TablaProcesos from './Partials/Catalogos/TablaProcesos';
import TablaListas from './Partials/Catalogos/TablaListas';
import TablaEstados from './Partials/Catalogos/TablaEstados';
import TablaTipoClientes from './Partials/Catalogos/TablaTipoClientes';
import TablaZonasEntrega from './Partials/Catalogos/TablaZonasEntrega';
import TablaHorariosEntrega from './Partials/Catalogos/TablaHorariosEntrega';
import TablaHorariosTraspaso from './Partials/Catalogos/TablaHorariosTraspaso';
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
import TablaAtributosProducto from './Partials/Catalogos/TablaAtributosProducto';
import TablaUnidadesMedida from './Partials/Catalogos/TablaUnidadesMedida';
import TablaNotasOlfativas from './Partials/Catalogos/TablaNotasOlfativas';
import TablaExtensionesProducto from './Partials/Catalogos/TablaExtensionesProducto';
import TablaEstatusPedidos from './Partials/Catalogos/TablaEstatusPedidos';
import TablaCatalogoPedidoGenerico from './Partials/Catalogos/TablaCatalogoPedidoGenerico';
import TablaTiposCajaPedido from './Partials/Catalogos/TablaTiposCajaPedido';
import TablaPaqueteriasPedido from './Partials/Catalogos/TablaPaqueteriasPedido';
import TablaOrigenesPedido from './Partials/Catalogos/TablaOrigenesPedido';
import TablaReexpedicionPedido from './Partials/Catalogos/TablaReexpedicionPedido';
import TablaZonasPedido from './Partials/Catalogos/TablaZonasPedido';
import TablaCatalogoFiscal from './Partials/Catalogos/TablaCatalogoFiscal';

function normalizarBusqueda(texto) {
    return String(texto || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();
}

function valorBuscable(valor) {
    if (valor == null) return '';
    if (typeof valor === 'string' || typeof valor === 'number' || typeof valor === 'boolean') {
        return String(valor);
    }
    if (Array.isArray(valor)) {
        return valor.map(valorBuscable).join(' ');
    }
    if (typeof valor === 'object') {
        return ['nombre', 'codigo', 'etiqueta', 'clave', 'descripcion']
            .map((k) => valor[k])
            .filter(Boolean)
            .join(' ');
    }
    return '';
}

function filtrarFilas(items, query) {
    const q = normalizarBusqueda(query).trim();
    if (!q || !Array.isArray(items)) return items;
    return items.filter((item) => {
        const texto = normalizarBusqueda(
            Object.values(item || {})
                .map(valorBuscable)
                .join(' ')
        );
        return texto.includes(q);
    });
}

function SearchField({ value, onChange, placeholder, className = '' }) {
    return (
        <div className={`relative ${className}`}>
            <Search className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 theme-text-muted" aria-hidden />
            <input
                type="search"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                className={`${THEME_INPUT} w-full !pl-9 !py-2 text-xs font-bold`}
                aria-label={placeholder}
            />
        </div>
    );
}

export default function Catalogos({
    auth, procesos, listas, estados, departamentos, areas, tipos_cliente,
    zonas_entrega, horarios_entrega, horarios_traspaso = [], porcentajes_escalonamiento = [], porcentajes_listado = [],
    bancos = [], tipos_activo = [], categorias_activo = [],
    sucursales = [], tipos_almacen = [], marcas_producto = [], almacenes = [], categorias_producto = [],
    atributos_producto = [], unidades_medida = [], notas_olfativas = [],
    extensiones_producto = [], perfumeria_en_uso = false,
    estatus_pedidos = [], paqueterias_pedido = [], tipos_caja_pedido = [],
    tipos_guia_pedido = [], zonas_pedido = [], reexpedicion_pedido = [], envios_tienda = [], origenes_pedido = [],
    regimenes_fiscales = [], usos_cfdi = [],
    logos_disponibles = [],
}) {
    const [tabActiva, setTabActiva] = useState('departamentos');
    const [busquedaMenu, setBusquedaMenu] = useState('');
    const [busquedaTabla, setBusquedaTabla] = useState('');
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
        { id: 'atributos_producto', label: 'Atributos Producto', icon: ListTree },
        { id: 'unidades_medida', label: 'Unidades Medida', icon: Ruler },
        { id: 'extensiones_producto', label: 'Extensiones', icon: Puzzle },
        ...(perfumeria_en_uso ? [{ id: 'notas_olfativas', label: 'Notas Olfativas', icon: Flower2 }] : []),
        { id: 'procesos', label: 'Procesos', icon: ListTree },
        { id: 'listas', label: 'Listas', icon: Tags },
        { id: 'porcentajes_escalonamiento', label: 'Escalonamiento', icon: TrendingUp },
        { id: 'porcentajes_listado', label: 'Listados', icon: Percent },
        { id: 'estados', label: 'Estados', icon: Activity },
        { id: 'bancos', label: 'Bancos', icon: Landmark },
        { id: 'regimenes_fiscales', label: 'Régimen Fiscal', icon: FileText },
        { id: 'usos_cfdi', label: 'Uso CFDI', icon: Receipt },
        { id: 'tipos_activo', label: 'Tipos Activo', icon: Package },
        { id: 'categorias_activo', label: 'Categorías Activo', icon: Tags },
        { id: 'zonas_entrega', label: 'Zonas Logísticas', icon: Map },
        { id: 'horarios_entrega', label: 'Horarios Entrega', icon: Clock },
        { id: 'horarios_traspaso', label: 'Horarios Traspaso', icon: Clock },
        { id: 'origenes_pedido', label: 'Orígenes Pedido', icon: Tags },
        { id: 'envios_tienda', label: 'Envíos / Tienda', icon: Truck },
        { id: 'estatus_pedidos', label: 'Estatus Pedidos', icon: Activity },
        { id: 'paqueterias_pedido', label: 'Paqueterías', icon: Truck },
        { id: 'tipos_caja_pedido', label: 'Tipos Caja', icon: Box },
        { id: 'tipos_guia_pedido', label: 'Tipos Guía', icon: Map },
        { id: 'zonas_pedido', label: 'Zonas Pedido', icon: MapPin },
        { id: 'reexpedicion_pedido', label: 'Reexpedición', icon: MapPin },
    ];

    const tabsFiltrados = useMemo(() => {
        const q = normalizarBusqueda(busquedaMenu).trim();
        if (!q) return tabs;
        return tabs.filter((tab) => normalizarBusqueda(tab.label).includes(q));
    }, [busquedaMenu]);

    const seleccionarTab = (id) => {
        setTabActiva(id);
        setBusquedaTabla('');
    };

    const f = (items) => filtrarFilas(items, busquedaTabla);

    return (
        <AppLayout auth={auth}>
            <Head title="Catálogos | GELIANV" />

            <div className="w-full mx-auto flex flex-col gap-4 relative h-[calc(100dvh-var(--gelia-mobile-topbar-height,0px)-4.5rem)] md:h-[calc(100dvh-3.25rem)] overflow-hidden">
                <header className={`${activeCardClass} shrink-0 px-5 py-4 md:px-8 md:py-5 flex flex-col md:flex-row justify-between items-start md:items-center gap-3`}>
                    <div className="space-y-1.5">
                        <div className="flex items-center gap-3">
                            <div className="w-8 h-1.5 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }} />
                            <span className="text-[10px] font-black tracking-[0.2em] uppercase theme-text-muted">Estructura de Datos_</span>
                        </div>
                        <h1 className="text-2xl md:text-3xl font-black italic tracking-tighter uppercase theme-text-main leading-none m-0">
                            GESTIÓN DE <span style={{ color: 'var(--color-primario)' }}>CATÁLOGOS</span>
                        </h1>
                    </div>
                </header>

                <div className={`${activeCardClass} flex-1 min-h-0 overflow-hidden flex flex-col md:flex-row`}>
                    {/* Móvil: búsqueda + scroll horizontal */}
                    <div className="md:hidden border-b theme-border shrink-0 space-y-2 p-3">
                        <SearchField
                            value={busquedaMenu}
                            onChange={setBusquedaMenu}
                            placeholder="Buscar catálogo…"
                        />
                        <nav className="flex gap-2 overflow-x-auto" aria-label="Catálogos">
                            {tabsFiltrados.map((tab) => (
                                <button
                                    key={tab.id}
                                    type="button"
                                    onClick={() => seleccionarTab(tab.id)}
                                    className={`shrink-0 flex items-center gap-2 px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all outline-none whitespace-nowrap ${
                                        tabActiva === tab.id ? 'text-white shadow-md' : 'theme-text-muted theme-element border theme-border'
                                    }`}
                                    style={tabActiva === tab.id ? { backgroundColor: 'var(--color-primario)' } : {}}
                                >
                                    <tab.icon className="w-3.5 h-3.5" /> {tab.label}
                                </button>
                            ))}
                            {tabsFiltrados.length === 0 && (
                                <p className="text-[10px] font-bold theme-text-muted uppercase py-2 m-0">Sin coincidencias</p>
                            )}
                        </nav>
                    </div>

                    {/* Desktop: sidebar vertical con scroll */}
                    <aside className="hidden md:flex flex-col w-64 shrink-0 border-r theme-border min-h-0">
                        <div className="px-3 py-3 border-b theme-border shrink-0 space-y-2">
                            <p className="text-[9px] font-black uppercase tracking-[0.2em] theme-text-muted m-0 px-1">Catálogos_</p>
                            <SearchField
                                value={busquedaMenu}
                                onChange={setBusquedaMenu}
                                placeholder="Buscar catálogo…"
                            />
                        </div>
                        <nav className="flex-1 min-h-0 overflow-y-auto p-2 space-y-0.5" aria-label="Catálogos">
                            {tabsFiltrados.map((tab) => (
                                <button
                                    key={tab.id}
                                    type="button"
                                    onClick={() => seleccionarTab(tab.id)}
                                    className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-[10px] font-black uppercase tracking-widest transition-all outline-none ${
                                        tabActiva === tab.id
                                            ? 'text-white shadow-md'
                                            : 'theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5'
                                    }`}
                                    style={tabActiva === tab.id ? { backgroundColor: 'var(--color-primario)' } : {}}
                                >
                                    <tab.icon className="w-4 h-4 shrink-0" />
                                    <span className="truncate">{tab.label}</span>
                                </button>
                            ))}
                            {tabsFiltrados.length === 0 && (
                                <p className="px-3 py-4 text-[10px] font-bold theme-text-muted uppercase m-0">Sin coincidencias</p>
                            )}
                        </nav>
                    </aside>

                    <div className="flex-1 min-w-0 min-h-0 flex flex-col overflow-hidden">
                        <div className="shrink-0 px-4 py-3 md:px-6 border-b theme-border">
                            <SearchField
                                value={busquedaTabla}
                                onChange={setBusquedaTabla}
                                placeholder="Buscar en la tabla…"
                                className="max-w-md"
                            />
                        </div>
                        <section className="flex-1 min-h-0 overflow-y-auto">
                            {tabActiva === 'departamentos' && (
                                <TablaDepartamentos datos={f(departamentos)} logosDisponibles={logos_disponibles} />
                            )}
                            {tabActiva === 'areas' && <TablaAreas datos={f(areas)} departamentos={departamentos} />}
                            {tabActiva === 'sucursales' && <TablaSucursales datos={f(sucursales)} />}
                            {tabActiva === 'tipos_almacen' && <TablaTiposAlmacen datos={f(tipos_almacen)} />}
                            {tabActiva === 'almacenes' && <TablaAlmacenes datos={f(almacenes)} sucursales={sucursales} tipos_almacen={tipos_almacen} />}
                            {tabActiva === 'marcas_producto' && <TablaMarcasProducto datos={f(marcas_producto)} />}
                            {tabActiva === 'categorias_producto' && (
                                <TablaCategoriasProducto
                                    datos={f(categorias_producto)}
                                    atributos={atributos_producto}
                                    extensiones={extensiones_producto}
                                />
                            )}
                            {tabActiva === 'atributos_producto' && <TablaAtributosProducto datos={f(atributos_producto)} />}
                            {tabActiva === 'unidades_medida' && <TablaUnidadesMedida datos={f(unidades_medida)} />}
                            {tabActiva === 'extensiones_producto' && <TablaExtensionesProducto datos={f(extensiones_producto)} />}
                            {tabActiva === 'notas_olfativas' && perfumeria_en_uso && <TablaNotasOlfativas datos={f(notas_olfativas)} />}
                            {tabActiva === 'procesos' && <TablaProcesos datos={f(procesos)} />}
                            {tabActiva === 'listas' && <TablaListas datos={f(listas)} />}
                            {tabActiva === 'porcentajes_escalonamiento' && <TablaPorcentajesEscalonamiento datos={f(porcentajes_escalonamiento)} listas={listas} />}
                            {tabActiva === 'porcentajes_listado' && <TablaPorcentajesListado datos={f(porcentajes_listado)} listas={listas} />}
                            {tabActiva === 'estados' && <TablaEstados datos={f(estados)} />}
                            {tabActiva === 'bancos' && <TablaBancos datos={f(bancos)} />}
                            {tabActiva === 'regimenes_fiscales' && (
                                <TablaCatalogoFiscal
                                    datos={f(regimenes_fiscales)}
                                    titulo="Régimen Fiscal_"
                                    icon={FileText}
                                    routePrefix="regimenes_fiscales"
                                    loaderMessage="Guardando Régimen_"
                                />
                            )}
                            {tabActiva === 'usos_cfdi' && (
                                <TablaCatalogoFiscal
                                    datos={f(usos_cfdi)}
                                    titulo="Uso CFDI_"
                                    icon={Receipt}
                                    routePrefix="usos_cfdi"
                                    loaderMessage="Guardando Uso CFDI_"
                                />
                            )}
                            {tabActiva === 'tipos_activo' && <TablaTiposActivo datos={f(tipos_activo)} />}
                            {tabActiva === 'categorias_activo' && <TablaCategoriasActivo datos={f(categorias_activo)} />}
                            {tabActiva === 'tipos_cliente' && <TablaTipoClientes datos={f(tipos_cliente)} />}
                            {tabActiva === 'zonas_entrega' && <TablaZonasEntrega datos={f(zonas_entrega)} auth={auth} />}
                            {tabActiva === 'horarios_entrega' && <TablaHorariosEntrega datos={f(horarios_entrega)} zonas_entrega={zonas_entrega} auth={auth} />}
                            {tabActiva === 'horarios_traspaso' && <TablaHorariosTraspaso datos={f(horarios_traspaso)} />}
                            {tabActiva === 'estatus_pedidos' && <TablaEstatusPedidos datos={f(estatus_pedidos)} />}
                            {tabActiva === 'origenes_pedido' && (
                                <TablaOrigenesPedido datos={f(origenes_pedido)} />
                            )}
                            {tabActiva === 'envios_tienda' && (
                                <TablaCatalogoPedidoGenerico datos={f(envios_tienda)} titulo="Envíos / Tienda_" icon={Truck} routePrefix="envios_tienda" loaderMessage="Guardando Envío_" />
                            )}
                            {tabActiva === 'paqueterias_pedido' && (
                                <TablaPaqueteriasPedido datos={f(paqueterias_pedido)} />
                            )}
                            {tabActiva === 'tipos_caja_pedido' && <TablaTiposCajaPedido datos={f(tipos_caja_pedido)} />}
                            {tabActiva === 'tipos_guia_pedido' && (
                                <TablaCatalogoPedidoGenerico datos={f(tipos_guia_pedido)} titulo="Tipos de Guía_" icon={Map} routePrefix="tipos_guia_pedido" loaderMessage="Guardando Guía_" />
                            )}
                            {tabActiva === 'zonas_pedido' && (
                                <TablaZonasPedido datos={f(zonas_pedido)} auth={auth} />
                            )}
                            {tabActiva === 'reexpedicion_pedido' && (
                                <TablaReexpedicionPedido datos={f(reexpedicion_pedido)} paqueterias={paqueterias_pedido} />
                            )}
                        </section>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
