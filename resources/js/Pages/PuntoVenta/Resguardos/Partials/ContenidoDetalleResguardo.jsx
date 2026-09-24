import React from 'react';
import { Link } from '@inertiajs/react';
import { AlertTriangle, Package, Printer } from 'lucide-react';
import { geliaCardClass, THEME_BTN_PRIMARY } from '../../../../utils/geliaTheme';
import PanelIncidenciasResguardo from './PanelIncidenciasResguardo';
import PanelExcepcionesResguardo from './PanelExcepcionesResguardo';
import PanelReponerVencidoResguardo from './PanelReponerVencidoResguardo';
import PanelAuditoriaResguardo from './PanelAuditoriaResguardo';
import { badgeAntiguedad, badgeEstadoResguardo, formatearFechaOperativa, BTN_SECONDARY } from './resguardosStyles';
import {
    cantidadBultosPendiente,
    cantidadBultosRecibida,
    resguardoAdmiteEntregaTotal,
    resguardoAdmiteRecepcion,
} from './recepcionFisicaUtils';
import { plazosOperativosResguardo } from './resguardosUtils';
import PanelPedidoRevisionResguardo from './PanelPedidoRevisionResguardo';
import { AccionEntregaResguardo } from './ModalEntregaResguardo';
import BotonConfirmarRecepcionResguardo, { BotonPasarARecepcionResguardo } from './BotonConfirmarRecepcionResguardo';

export default function ContenidoDetalleResguardo({
    resguardo,
    timeline = [],
    catalogos = {},
    permisos = {},
    almacenes = [],
    onAccionExito,
    mostrarRegresarListado = false,
    modoAuditoria = false,
}) {
    if (!resguardo) {
        return null;
    }

    const titulo = resguardo.snapshot_folio || `Resguardo #${resguardo.id}`;
    const plazos = plazosOperativosResguardo(resguardo);
    const clasificacionesActivas = Object.entries(resguardo.clasificaciones || {})
        .filter(([, activa]) => activa)
        .map(([clave]) => ({
            clave,
            etiqueta: catalogos.antiguedades?.[clave] || clave,
        }));

    return (
        <div className="space-y-6">
            <div className={`${geliaCardClass()} p-5 md:p-6 space-y-4`}>
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-2 min-w-0">
                        <div className="flex items-center gap-2">
                            <Package className="w-5 h-5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                            <h2 className="text-xl font-black italic uppercase theme-text-main m-0 truncate">
                                {titulo}
                            </h2>
                        </div>
                        <p className="text-sm font-bold theme-text-muted m-0">
                            Cliente {resguardo.referencia_cliente}
                        </p>
                        {resguardo.sucursal?.nombre && (
                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">
                                {resguardo.sucursal.nombre}
                            </p>
                        )}
                    </div>
                    <span className={`inline-flex px-3 py-1.5 rounded-xl text-[10px] font-black uppercase ${badgeEstadoResguardo(resguardo.estado)}`}>
                        {resguardo.estado_etiqueta || catalogos.estados?.[resguardo.estado] || resguardo.estado}
                    </span>
                </div>

                {clasificacionesActivas.length > 0 && (
                    <div className="flex flex-wrap gap-2">
                        {clasificacionesActivas.map(({ clave, etiqueta }) => (
                            <span
                                key={clave}
                                className={`inline-flex px-2 py-1 rounded-lg text-[9px] font-black uppercase ${badgeAntiguedad(clave)}`}
                            >
                                {etiqueta}
                            </span>
                        ))}
                    </div>
                )}

                {!resguardo.antiguedad_configurada && (
                    <p className="text-xs theme-text-muted m-0">
                        Los plazos operativos de custodia no están configurados; las clasificaciones de antigüedad no aplican.
                    </p>
                )}

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <DetalleCampo label="Bultos esperados" value={resguardo.cantidad_bultos_esperada} />
                    <DetalleCampo label="Bultos recibidos" value={cantidadBultosRecibida(resguardo)} />
                    <DetalleCampo label="Bultos pendientes" value={cantidadBultosPendiente(resguardo)} />
                    <DetalleCampo label="Salida CEDIS" value={formatearFechaOperativa(resguardo.salida_cedis_at)} />
                    <DetalleCampo label="Recepción física" value={formatearFechaOperativa(resguardo.recepcion_fisica_at)} />
                    <DetalleCampo label="Entrega completada" value={formatearFechaOperativa(resguardo.entrega_completada_at)} />
                    {plazos.map(({ id, etiqueta, fecha }) => (
                        <DetalleCampo
                            key={id}
                            label={etiqueta}
                            value={formatearFechaOperativa(fecha)}
                        />
                    ))}
                </div>

                {resguardo.registro_manual && (
                    <PanelRegistroManual registro={resguardo.registro_manual} />
                )}

                {resguardo.pedido && (
                    <p className="text-[10px] theme-text-muted font-bold m-0">
                        Pedido {resguardo.pedido.folio || resguardo.pedido.id}
                        {resguardo.pedido.folio_remision ? ` · Remisión ${resguardo.pedido.folio_remision}` : ''}
                    </p>
                )}

                {resguardo.entrega_bloqueada && (
                    <p className="text-[10px] font-black uppercase text-red-600 dark:text-red-300 m-0 flex items-center gap-2">
                        <AlertTriangle className="w-4 h-4 shrink-0" />
                        {resguardo.cancelacion_recibida
                            ? 'Pedido cancelado. Entrega bloqueada; se requiere devolución al origen.'
                            : 'Entrega bloqueada'}
                    </p>
                )}
            </div>

            <PanelPedidoRevisionResguardo resguardo={resguardo} />

            {resguardo.bultos?.length > 0 && (
                <div className={`${geliaCardClass()} overflow-hidden`}>
                    <div className="p-4 border-b theme-border">
                        <h3 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Bultos</h3>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse min-w-[520px]">
                            <thead>
                                <tr className="border-b theme-border">
                                    {['Folio', 'Tipo', 'Estado', 'Recepción', 'Entrega'].map((col) => (
                                        <th key={col} className="px-4 py-3 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">
                                            {col}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {resguardo.bultos.map((bulto) => (
                                    <tr key={bulto.id} className="border-b theme-border">
                                        <td className="px-4 py-3 text-sm font-semibold">{bulto.folio || `#${bulto.id}`}</td>
                                        <td className="px-4 py-3 text-sm theme-text-muted">{bulto.tipo}</td>
                                        <td className="px-4 py-3 text-sm theme-text-muted">{bulto.estado}</td>
                                        <td className="px-4 py-3 text-[10px] theme-text-muted">{formatearFechaOperativa(bulto.recepcion_at)}</td>
                                        <td className="px-4 py-3 text-[10px] theme-text-muted">{formatearFechaOperativa(bulto.entrega_at)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {!modoAuditoria && (
                <PanelReponerVencidoResguardo
                    resguardo={resguardo}
                    permisos={permisos}
                />
            )}

            {!modoAuditoria && (
                <PanelExcepcionesResguardo
                    resguardo={resguardo}
                    timeline={timeline}
                    catalogos={catalogos}
                    permisos={permisos}
                />
            )}

            <PanelIncidenciasResguardo
                key={resguardo.version}
                resguardo={resguardo}
                timeline={timeline}
                catalogos={catalogos}
                permisos={permisos}
                almacenes={almacenes}
            />

            <PanelAuditoriaResguardo
                resguardoId={resguardo.id}
                timelineInicial={timeline}
                catalogos={catalogos}
            />

            {!modoAuditoria && (
            <div className="flex flex-wrap justify-end gap-2">
                {resguardo.bultos?.length > 0 && permisos.ver_etiquetas && (
                    <a
                        href={route('punto_venta.resguardos.etiquetas.descargar', resguardo.id)}
                        className={`${BTN_SECONDARY} inline-flex items-center gap-2 min-h-[44px] px-5 text-[10px] font-black uppercase tracking-widest`}
                    >
                        <Printer className="w-4 h-4" /> Imprimir etiquetas
                    </a>
                )}
                {permisos.entregar
                    && resguardo.estado === 'en_custodia'
                    && !resguardo.entrega_bloqueada
                    && resguardoAdmiteEntregaTotal(resguardo) && (
                    <AccionEntregaResguardo
                        resguardo={resguardo}
                        variant="primary"
                        className="inline-flex min-h-[44px] px-5"
                        onExito={onAccionExito}
                    />
                )}
                {permisos.recibir && resguardoAdmiteRecepcion(resguardo) && (
                    <BotonConfirmarRecepcionResguardo
                        resguardo={resguardo}
                        className="inline-flex min-h-[44px] px-5 w-auto"
                        etiqueta="Confirmar recepción"
                        onExito={onAccionExito}
                    />
                )}
                {permisos.recibir && (resguardo.admite_pasar_a_recepcion || resguardo.estado === 'recibido') && resguardo.estado === 'recibido' && (
                    <BotonPasarARecepcionResguardo
                        resguardo={resguardo}
                        className="inline-flex min-h-[44px] px-5 w-auto"
                        onExito={onAccionExito}
                    />
                )}
                {permisos.confirmar_custodia && resguardo.estado === 'en_recepcion' && (
                    <Link
                        href={route('punto_venta.resguardos.custodia.create', resguardo.id)}
                        className={`${THEME_BTN_PRIMARY} inline-flex items-center gap-2 min-h-[44px] px-5 text-[10px] font-black uppercase tracking-widest`}
                    >
                        Revisar en recepción
                    </Link>
                )}
                {mostrarRegresarListado && (
                    <Link href={route('punto_venta.resguardos.index')} className={`${BTN_SECONDARY} inline-flex items-center gap-2`}>
                        Regresar al listado
                    </Link>
                )}
            </div>
            )}
            {modoAuditoria && resguardo.bultos?.length > 0 && permisos.ver_etiquetas && (
                <div className="flex flex-wrap justify-end gap-2">
                    <a
                        href={route('punto_venta.resguardos.etiquetas.descargar', resguardo.id)}
                        className={`${BTN_SECONDARY} inline-flex items-center gap-2 min-h-[44px] px-5 text-[10px] font-black uppercase tracking-widest`}
                    >
                        <Printer className="w-4 h-4" /> Imprimir etiquetas
                    </a>
                </div>
            )}
        </div>
    );
}

function DetalleCampo({ label, value }) {
    return (
        <div className="rounded-2xl border theme-border p-3">
            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">{label}</p>
            <p className="text-sm font-black theme-text-main m-0 mt-1">{value ?? '—'}</p>
        </div>
    );
}

function evidenciaPorUso(registro, uso) {
    return (registro?.evidencias || []).find((item) => item.uso === uso) || null;
}

function MiniaturaEvidencia({ evidencia, etiqueta }) {
    if (!evidencia) {
        return <DetalleCampo label={etiqueta} value="Sin archivo" />;
    }

    const esImagen = String(evidencia.mime_type || '').startsWith('image/') && evidencia.ruta_publica;

    return (
        <div className="rounded-2xl border theme-border p-3 space-y-2">
            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">{etiqueta}</p>
            {esImagen ? (
                <a href={evidencia.ruta_publica} target="_blank" rel="noreferrer" className="block">
                    <img
                        src={evidencia.ruta_publica}
                        alt={etiqueta}
                        className="w-full h-28 object-cover rounded-xl"
                    />
                </a>
            ) : (
                <a
                    href={evidencia.ruta_publica}
                    target="_blank"
                    rel="noreferrer"
                    className="text-sm font-bold theme-text-primario"
                >
                    {evidencia.nombre_original || 'Ver archivo'}
                </a>
            )}
        </div>
    );
}

function PanelRegistroManual({ registro }) {
    const piezas = Array.isArray(registro.piezas) ? registro.piezas : [];

    return (
        <div className="space-y-3">
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <DetalleCampo label="Área de origen" value={registro.origen_nombre} />
                <DetalleCampo label="Piezas" value={registro.cantidad_piezas} />
            </div>
            {registro.observaciones && (
                <div className="rounded-2xl border theme-border p-3">
                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">Observaciones</p>
                    <p className="text-sm font-semibold theme-text-main m-0 mt-1 whitespace-pre-wrap">{registro.observaciones}</p>
                </div>
            )}
            {piezas.length > 0 && (
                <div className="rounded-2xl border theme-border overflow-hidden">
                    <div className="px-3 py-2 border-b theme-border">
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">Piezas registradas</p>
                    </div>
                    <ul className="m-0 p-0 list-none divide-y theme-border">
                        {piezas.map((pieza) => (
                            <li key={`${pieza.producto_id}-${pieza.sku}`} className="px-3 py-2 flex items-center justify-between gap-3">
                                <span className="min-w-0">
                                    <span className="block text-sm font-bold theme-text-main truncate">{pieza.descripcion}</span>
                                    <span className="block text-xs theme-text-muted">{pieza.sku || 'Sin SKU'}</span>
                                </span>
                                <span className="text-sm font-black theme-text-main tabular-nums shrink-0">{pieza.cantidad}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <MiniaturaEvidencia evidencia={evidenciaPorUso(registro, 'ticket')} etiqueta="Ticket" />
                <MiniaturaEvidencia evidencia={evidenciaPorUso(registro, 'paquete')} etiqueta="Paquete" />
            </div>
        </div>
    );
}
