import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { Filter, Search } from 'lucide-react';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import { GELIA_BTN_OUTLINE, GELIA_ESTADO_VIVO_TONO, THEME_BTN_PRIMARY, THEME_INPUT, THEME_LABEL, geliaCardClass } from '../../../utils/geliaTheme';
import FiltroClienteEscalonamiento from './FiltroClienteEscalonamiento';
import {
    dinero,
    etiquetaTipoDocumento,
    ETIQUETAS_ESTADO_DOCUMENTO,
    TONO_ESTADO_DOCUMENTO,
} from './escalonamientoUi';

export default function TablaDocumentosEscalonamiento({
    periodo,
    documentos = { data: [], paginacion: {} },
    busqueda = '',
    filtros = {},
    clienteSeleccionado = null,
}) {
    const [clienteId, setClienteId] = useState(filtros.cliente_id ? String(filtros.cliente_id) : '');

    useEffect(() => {
        setClienteId(filtros.cliente_id ? String(filtros.cliente_id) : '');
    }, [filtros.cliente_id]);

    const filas = documentos.data || [];
    const paginacion = documentos.paginacion || {};

    const navegar = (extra = {}) => {
        router.get(route('escalonamiento.index'), {
            periodo_id: periodo?.id,
            tab: 'documentos',
            q_doc: extra.q_doc !== undefined ? extra.q_doc : (busqueda || undefined),
            doc_page: extra.doc_page,
            cliente_id: extra.cliente_id !== undefined ? extra.cliente_id : (clienteId || undefined),
        }, { preserveState: true, preserveScroll: true });
    };

    const enviarBusqueda = (event) => {
        event.preventDefault();
        const q = new FormData(event.currentTarget).get('q_doc');
        navegar({ q_doc: q || undefined, doc_page: 1, cliente_id: clienteId || undefined });
    };

    const aplicarCliente = () => {
        navegar({ doc_page: 1, cliente_id: clienteId || undefined, q_doc: busqueda || undefined });
    };

    return (
        <div className="space-y-5">
            <div className={`${geliaCardClass()} p-5 md:p-6 space-y-5`}>
                <div className="flex items-center gap-3 theme-text-main">
                    <Filter className="w-5 h-5" aria-hidden />
                    <span className="text-base font-bold">Filtros</span>
                </div>
                <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                    <FiltroClienteEscalonamiento
                        valorId={clienteId}
                        etiquetaInicial={clienteSeleccionado?.etiqueta}
                        onSeleccionar={(c) => setClienteId(c ? String(c.id) : '')}
                    />
                    <div className="flex items-end lg:pb-px">
                        <button type="button" className={GELIA_BTN_OUTLINE} onClick={aplicarCliente}>
                            Aplicar cliente
                        </button>
                    </div>
                </div>
                <form onSubmit={enviarBusqueda} className="flex flex-col sm:flex-row gap-4 sm:items-end border-t theme-border pt-5">
                    <label className="flex-1 space-y-1.5">
                        <span className={THEME_LABEL}>Buscar documento</span>
                        <div className="theme-field-with-icon">
                            <Search className="theme-field-icon" aria-hidden />
                            <input
                                name="q_doc"
                                className={`${THEME_INPUT} w-full`}
                                placeholder="Folio, número o nombre"
                                defaultValue={busqueda}
                            />
                        </div>
                    </label>
                    <button type="submit" className={`${THEME_BTN_PRIMARY} shrink-0`}>
                        <Search className="w-4 h-4" aria-hidden />
                        Buscar
                    </button>
                </form>
            </div>

            <div className={geliaCardClass('overflow-hidden p-0')}>
                <div className="overflow-x-auto max-h-[min(70vh,720px)] px-3 pt-3 pb-2 sm:px-4 sm:pt-4">
                    <table className="w-full text-sm min-w-[860px]">
                        <thead className="sticky top-0 z-10 theme-surface border-b theme-border">
                            <tr className="text-left theme-text-muted">
                                <th className="py-3 px-4 text-xs font-bold uppercase tracking-wide">Documento</th>
                                <th className="py-3 px-4 text-xs font-bold uppercase tracking-wide">Cliente</th>
                                <th className="py-3 px-4 text-xs font-bold uppercase tracking-wide">Fecha</th>
                                <th className="py-3 px-4 text-xs font-bold uppercase tracking-wide">Sucursal</th>
                                <th className="py-3 px-4 text-xs font-bold uppercase tracking-wide text-right">Total</th>
                                <th className="py-3 px-4 text-xs font-bold uppercase tracking-wide">Estado</th>
                                <th className="py-3 px-4 text-xs font-bold uppercase tracking-wide">Acumulado</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filas.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="py-8 px-3 text-center text-sm theme-text-muted">
                                        {busqueda || clienteId
                                            ? 'No se encontraron documentos con estos filtros.'
                                            : 'No hay documentos registrados en este período.'}
                                    </td>
                                </tr>
                            ) : filas.map((fila) => (
                                <tr key={fila.id} className="border-t theme-border hover:bg-black/[0.02] dark:hover:bg-white/[0.03]">
                                    <td className="py-3.5 px-4 theme-text-main align-top leading-relaxed">
                                        <span className="font-semibold block">{etiquetaTipoDocumento(fila.tipo)}</span>
                                        <span className="text-xs theme-text-muted tabular-nums">{fila.folio || '—'}</span>
                                    </td>
                                    <td className="py-3.5 px-4 theme-text-main align-top leading-relaxed">
                                        <span className="block">{fila.nombre || '—'}</span>
                                        <span className="text-xs theme-text-muted tabular-nums">{fila.numero_cliente || ''}</span>
                                    </td>
                                    <td className="py-3.5 px-4 theme-text-muted align-top tabular-nums whitespace-nowrap">{fila.fecha || '—'}</td>
                                    <td className="py-3.5 px-4 theme-text-muted align-top">{fila.sucursal || '—'}</td>
                                    <td className="py-3.5 px-4 theme-text-main text-right tabular-nums align-top whitespace-nowrap">
                                        {dinero(fila.total)}
                                        {fila.moneda && fila.moneda !== 'MXN' && (
                                            <span className="block text-xs theme-text-muted">{fila.moneda}</span>
                                        )}
                                    </td>
                                    <td className="py-3.5 px-4 align-top">
                                        <EtiquetaDocumento estado={fila.estado} />
                                    </td>
                                    <td className="py-3.5 px-4 align-top">
                                        <span className={`gelia-estado-vivo gelia-estado-vivo--compacto inline-flex text-xs font-semibold ${fila.aporta_acumulado ? 'gelia-estado-vivo--exito' : 'gelia-estado-vivo--neutro'}`}>
                                            {fila.aporta_acumulado ? 'Elegible' : 'No aporta'}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <GeliaPaginacion
                    embedded
                    paginator={paginacion}
                    onIrAPagina={(page) => navegar({ q_doc: busqueda || undefined, doc_page: page, cliente_id: clienteId || undefined })}
                />
            </div>
        </div>
    );
}

function EtiquetaDocumento({ estado }) {
    const texto = ETIQUETAS_ESTADO_DOCUMENTO[estado] || estado || '—';
    const tono = GELIA_ESTADO_VIVO_TONO[TONO_ESTADO_DOCUMENTO[estado] || 'neutro'];
    return (
        <span className={`gelia-estado-vivo gelia-estado-vivo--compacto inline-flex text-xs font-semibold ${tono}`}>
            {texto}
        </span>
    );
}
