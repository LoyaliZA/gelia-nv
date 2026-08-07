import React, { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { MapPin, Search, ClipboardList, ClipboardCheck, Loader2, Upload } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import ModalImportarCatalogo from '../../../Components/Catalogos/ModalImportarCatalogo';
import {
    geliaCardClass,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_INPUT,
} from '../../../utils/geliaTheme';
import useListadoDiscreto from '../Partials/useListadoDiscreto';

const IMPORT_DIRECCIONES_CONFIG = {
    titulo: 'Importar Direcciones',
    rutaPlantilla: 'control_pedidos.direcciones.plantilla_importacion',
    rutaImportar: 'control_pedidos.direcciones.importar',
    columnas: [
        { key: 'numero_cliente', label: 'numero_cliente', requerido: true, nota: 'Debe existir en clientes' },
        { key: 'es_principal', label: 'es_principal', requerido: true, nota: '1/0 o si/no — principal vs adicional' },
        { key: 'nombre_destinatario', label: 'nombre_destinatario', requerido: true },
        { key: 'telefono_destinatario', label: 'telefono_destinatario', requerido: false },
        { key: 'calle', label: 'calle', requerido: true, nota: 'Opcional si domicilio_irregular=1' },
        { key: 'numero_exterior', label: 'numero_exterior', requerido: false },
        { key: 'numero_interior', label: 'numero_interior', requerido: false },
        { key: 'colonia', label: 'colonia', requerido: true, nota: 'Opcional si domicilio_irregular=1' },
        { key: 'codigo_postal', label: 'codigo_postal', requerido: true, nota: '5 dígitos; opcional si irregular' },
        { key: 'municipio', label: 'municipio', requerido: true, nota: 'O ciudad si irregular' },
        { key: 'ciudad', label: 'ciudad', requerido: false },
        { key: 'estado', label: 'estado', requerido: true },
        { key: 'pais', label: 'pais', requerido: false, nota: 'Por defecto México' },
        { key: 'referencias', label: 'referencias', requerido: false, nota: 'Obligatorias si domicilio_irregular=1' },
        { key: 'indicaciones_entrega', label: 'indicaciones_entrega', requerido: false },
        { key: 'etiqueta', label: 'etiqueta', requerido: false },
        { key: 'tipo_direccion', label: 'tipo_direccion', requerido: false, nota: 'envio / ocurre / sucursal' },
        { key: 'domicilio_irregular', label: 'domicilio_irregular', requerido: false, nota: '1/0 — domicilio conocido / sin calle formal (no es “adicional”)' },
        { key: 'anexa_remision', label: 'anexa_remision', requerido: false, nota: '1/0 — nota de compra en el envío' },
    ],
    notas: [
        'Una fila = una dirección. Varias filas del mismo numero_cliente = principal + adicionales.',
        'es_principal e domicilio_irregular son independientes.',
        'Si ya existe principal y la fila pide principal, se crea como adicional (aviso en reporte).',
        'Duplicados exactos se omiten. Cliente inexistente se omite.',
    ],
};

export default function Index({ clientes = { data: [] }, filtros = {} }) {
    const { auth } = usePage().props;
    const can = (p) => auth?.user?.permissions?.includes(p)
        || auth?.user?.roles?.includes('Admin')
        || auth?.user?.roles?.includes('Super Admin')
        || auth?.user?.roles?.includes('Super admin (admin)');

    const {
        clientes: clientesVista,
        cargando,
        cargar,
    } = useListadoDiscreto({
        listadoRoute: 'control_pedidos.direcciones.listado',
        indexRoute: 'control_pedidos.direcciones.index',
        clientes,
    });

    const [q, setQ] = useState(filtros.q || '');
    const [modalImportar, setModalImportar] = useState(false);
    const debounce = useRef(null);
    const skipPrimeraCarga = useRef(true);

    useEffect(() => {
        if (skipPrimeraCarga.current) {
            skipPrimeraCarga.current = false;
            return undefined;
        }
        if (debounce.current) clearTimeout(debounce.current);
        debounce.current = setTimeout(() => {
            cargar({ q: q.trim() || undefined, page: 1 });
        }, 350);
        return () => clearTimeout(debounce.current);
    }, [q, cargar]);

    const irCliente = (id) => {
        router.get(route('control_pedidos.direcciones.cliente', id));
    };

    const filas = clientesVista?.data || [];
    const qActiva = q.trim();

    return (
        <AppLayout>
            <Head title="Direcciones · Auxiliar" />
            <GeliaPageShell className="space-y-6">
                <header className={`${geliaCardClass()} p-6 md:p-8`}>
                    <div className="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>
                                Gestión Pedidos
                            </p>
                            <h1 className="text-3xl md:text-4xl font-black italic tracking-tighter uppercase theme-text-main m-0 mt-1">
                                Direcciones
                            </h1>
                            <p className="text-sm theme-text-muted mt-2 m-0">
                                Clientes y direcciones registradas. Use la búsqueda para filtrar.
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {can('clientes.direcciones.crear') && (
                                <button
                                    type="button"
                                    className={`${THEME_BTN_PRIMARY} inline-flex items-center gap-2`}
                                    onClick={() => setModalImportar(true)}
                                >
                                    <Upload className="w-4 h-4" />
                                    Importar
                                </button>
                            )}
                            {can('control_pedidos.auditar') && (
                                <button
                                    type="button"
                                    className={`${THEME_BTN_SECONDARY} inline-flex items-center gap-2`}
                                    onClick={() => router.get(route('control_pedidos.auditar.index'))}
                                >
                                    <ClipboardCheck className="w-4 h-4" />
                                    Auditar
                                </button>
                            )}
                            {can('clientes.direcciones.revisar_solicitudes') && (
                                <button
                                    type="button"
                                    className={`${THEME_BTN_SECONDARY} inline-flex items-center gap-2`}
                                    onClick={() => router.get(route('control_pedidos.direcciones.solicitudes.index'))}
                                >
                                    <ClipboardList className="w-4 h-4" />
                                    Solicitudes
                                </button>
                            )}
                        </div>
                    </div>
                </header>

                <div className={`${geliaCardClass()} p-5 md:p-6 space-y-4`}>
                    <label className="block">
                        <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Buscar cliente</span>
                        <div className="theme-field-with-icon relative mt-2">
                            <Search className="theme-field-icon w-4 h-4" />
                            <input
                                type="text"
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder="Número o nombre…"
                                className={`${THEME_INPUT} w-full py-3.5 pr-10`}
                                autoComplete="off"
                                aria-busy={cargando}
                            />
                            {cargando && (
                                <Loader2 className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 animate-spin theme-text-muted" aria-label="Buscando" />
                            )}
                        </div>
                    </label>
                    <div className="pt-1 border-t theme-border">
                        <GeliaPaginacion
                            paginator={clientesVista}
                            onIrAPagina={(page) => cargar({
                                q: qActiva || undefined,
                                page,
                            })}
                            embedded
                            className="!border-0 !p-0 !pt-3"
                        />
                    </div>
                </div>

                <div className="relative min-h-[12rem]">
                    {cargando && (
                        <div className="absolute inset-0 z-10 flex items-start justify-center pt-16 pointer-events-none">
                            <Loader2 className="w-8 h-8 animate-spin" style={{ color: 'var(--color-primario)' }} aria-label="Cargando" />
                        </div>
                    )}
                    <div className={`${geliaCardClass()} overflow-hidden`}>
                        {filas.length === 0 ? (
                            <div className="p-12 text-center space-y-3">
                                <MapPin className="w-10 h-10 mx-auto theme-text-muted opacity-40" />
                                <p className="text-[11px] font-black uppercase tracking-widest theme-text-muted italic m-0">
                                    {qActiva ? 'Sin coincidencias con este filtro_' : 'No hay clientes registrados_'}
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left border-collapse min-w-[640px]">
                                    <thead>
                                        <tr className="border-b theme-border">
                                            <th className="p-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Número_</th>
                                            <th className="p-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Cliente_</th>
                                            <th className="p-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Direcciones activas_</th>
                                            <th className="p-4 text-[10px] font-black uppercase tracking-widest theme-text-muted text-right">Acción_</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {filas.map((c) => (
                                            <tr
                                                key={c.id}
                                                className="border-b theme-border hover:bg-black/5 dark:hover:bg-white/5 transition-colors"
                                            >
                                                <td className="p-4 font-mono text-xs font-black theme-text-muted">
                                                    {c.numero_cliente}
                                                </td>
                                                <td className="p-4 text-sm font-bold theme-text-main">
                                                    {c.nombre}
                                                </td>
                                                <td className="p-4 text-sm theme-text-muted">
                                                    {c.direcciones_activas_count ?? 0}
                                                </td>
                                                <td className="p-4 text-right">
                                                    <button
                                                        type="button"
                                                        className={THEME_BTN_PRIMARY}
                                                        onClick={() => irCliente(c.id)}
                                                    >
                                                        Abrir
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </GeliaPageShell>

            {modalImportar && (
                <ModalImportarCatalogo
                    config={IMPORT_DIRECCIONES_CONFIG}
                    onClose={() => setModalImportar(false)}
                />
            )}
        </AppLayout>
    );
}
