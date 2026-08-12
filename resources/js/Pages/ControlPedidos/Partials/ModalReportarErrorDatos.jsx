import React, { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import { AlertTriangle, X } from 'lucide-react';
import { THEME_LABEL, THEME_TEXTAREA } from '../../../utils/geliaTheme';
import {
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    BTN_PRIMARY,
    BTN_SECONDARY,
} from './pedidosBmaStyles';
import EncabezadoFolioPedido from './EncabezadoFolioPedido';

/** Espejo de CamposIncorrectosPedidoBma::CAMPOS_SOLO_* */
const CAMPOS_SOLO_LOGISTICA = new Set([
    'paqueteria', 'tipo_guia', 'referencia', 'codigo_postal', 'ciudad_estado',
    'domicilio', 'destinatario', 'telefono',
    'costo_envio', 'costo_seguro', 'aplica_seguro', 'reexpedicion', 'cliente_proporciona_guia',
    'tipo_caja', 'numero_cajas', 'peso_real', 'peso_volumetrico', 'peso_cobrado',
    'numero_rastreo', 'guia_pdf',
]);

const CAMPOS_SOLO_TIENDA = new Set(['envio_tienda']);

const CAMPOS_SOLO_RESGUARDO = new Set(['apartado_resguardo', 'modo_resguardo']);

export const GRUPOS_ERROR_DATOS = [
    {
        id: 'vendedora',
        label: 'Datos de captura / envío',
        destino: 'vendedora',
        campos: [
            { id: 'domicilio', label: 'Domicilio / dirección' },
            { id: 'destinatario', label: 'Destinatario' },
            { id: 'telefono', label: 'Teléfono' },
            { id: 'paqueteria', label: 'Paquetería' },
            { id: 'tipo_guia', label: 'Tipo de guía' },
            { id: 'referencia', label: 'Referencias' },
            { id: 'codigo_postal', label: 'Código postal' },
            { id: 'ciudad_estado', label: 'Ciudad / estado' },
            { id: 'origen', label: 'Tipo de pedido' },
            { id: 'cliente', label: 'Cliente' },
            { id: 'fecha', label: 'Fecha' },
            { id: 'banco', label: 'Banco' },
            { id: 'almacen', label: 'Almacén' },
            { id: 'es_resguardo', label: 'Resguardo' },
            { id: 'modo_resguardo', label: 'Tipo de resguardo' },
            { id: 'total_mercancia', label: 'Total mercancía' },
            { id: 'costo_envio', label: 'Costo de envío' },
            { id: 'costo_seguro', label: 'Costo de seguro' },
            { id: 'total_a_cobrar', label: 'Total a cobrar' },
            { id: 'aplica_seguro', label: 'Aplica seguro' },
            { id: 'saldo_a_favor', label: 'Saldo a favor' },
            { id: 'reexpedicion', label: 'Reexpedición' },
            { id: 'cliente_proporciona_guia', label: 'Guía del cliente' },
            { id: 'anexar_remision', label: 'Anexar remisión' },
            { id: 'comentarios_drive', label: 'Comentarios Drive' },
            { id: 'comprobantes', label: 'Comprobantes' },
            { id: 'pagos', label: 'Pagos' },
            { id: 'envio_tienda', label: 'Envío de tienda' },
        ],
    },
    {
        id: 'auxiliar',
        label: 'Auditoría / remisión',
        destino: 'auxiliar',
        campos: [
            { id: 'remision', label: 'Remisión PDF' },
            { id: 'folio_remision', label: 'Folio de remisión' },
            { id: 'pago_validado', label: 'Validación de pago' },
            { id: 'anexo_envio', label: 'Anexo de envío' },
        ],
    },
    {
        id: 'cedis',
        label: 'CEDIS / empaque / pesaje',
        destino: 'CEDIS',
        campos: [
            { id: 'empaque', label: 'Empaque' },
            { id: 'producto_faltante', label: 'Producto faltante' },
            { id: 'producto_danado', label: 'Producto dañado' },
            { id: 'inventario', label: 'Inventario' },
            { id: 'tipo_caja', label: 'Tipo de caja' },
            { id: 'numero_cajas', label: 'Número de cajas' },
            { id: 'peso_real', label: 'Peso real' },
            { id: 'peso_volumetrico', label: 'Peso volumétrico' },
            { id: 'peso_cobrado', label: 'Peso cobrado' },
            { id: 'apartado_resguardo', label: 'Apartado de resguardo' },
        ],
    },
    {
        id: 'guias',
        label: 'Guía',
        destino: 'encargado de guías',
        campos: [
            { id: 'numero_rastreo', label: 'Número de guía' },
            { id: 'guia_pdf', label: 'PDF de guía' },
        ],
    },
];

export const CAMPOS_ERROR_DATOS = GRUPOS_ERROR_DATOS.flatMap((g) => g.campos);

const PRIORIDAD_DESTINO = ['vendedora', 'auxiliar', 'cedis', 'guias'];

/**
 * @param {{ requiere_logistica?: boolean }|null|undefined} origen
 * @param {boolean} esResguardo
 */
export function campoAplicaAlContexto(campoId, origen, esResguardo = false) {
    const requiereLogistica = origen?.requiere_logistica ?? true;
    if (requiereLogistica && CAMPOS_SOLO_TIENDA.has(campoId)) return false;
    if (!requiereLogistica && CAMPOS_SOLO_LOGISTICA.has(campoId)) return false;
    if (!esResguardo && CAMPOS_SOLO_RESGUARDO.has(campoId)) return false;
    return true;
}

/**
 * @param {'delegado'|'cedis'|'auditar'} vista
 */
export function gruposErrorParaPedido(pedido, vista = 'delegado') {
    const origenCat = pedido?.origen;
    const esResguardo = Boolean(pedido?.es_resguardo);

    return GRUPOS_ERROR_DATOS
        .filter((g) => !(vista === 'auditar' && g.id === 'auxiliar'))
        .map((g) => ({
            ...g,
            campos: g.campos.filter((c) => campoAplicaAlContexto(c.id, origenCat, esResguardo)),
        }))
        .filter((g) => g.campos.length > 0);
}

function resolverCola(seleccionados, grupos) {
    const gruposTocados = grupos.filter((g) =>
        g.campos.some((c) => seleccionados.includes(c.id))
    );
    const ordenados = PRIORIDAD_DESTINO
        .map((id) => gruposTocados.find((g) => g.id === id))
        .filter(Boolean);
    return {
        activo: ordenados[0] || null,
        pendientes: ordenados.slice(1),
    };
}

/**
 * @param {'delegado'|'cedis'|'auditar'} origen
 */
export default function ModalReportarErrorDatos({ abierto, onClose, onSuccess, pedido, origen = 'delegado' }) {
    const [seleccionados, setSeleccionados] = useState([]);
    const [detalle, setDetalle] = useState('');
    const [procesando, setProcesando] = useState(false);
    const [error, setError] = useState('');

    const gruposVisibles = useMemo(
        () => gruposErrorParaPedido(pedido, origen),
        [pedido, origen]
    );

    const etiquetaContexto = useMemo(() => {
        const logistica = pedido?.origen?.requiere_logistica ?? true;
        const nombre = pedido?.origen?.nombre;
        const tipo = logistica ? 'con logística' : 'tienda / sin logística';
        return nombre ? `${nombre} (${tipo})` : tipo;
    }, [pedido?.origen]);

    useEffect(() => {
        if (abierto) {
            setSeleccionados([]);
            setDetalle('');
            setError('');
            setProcesando(false);
        }
    }, [abierto, pedido?.id]);

    const cola = useMemo(
        () => resolverCola(seleccionados, gruposVisibles),
        [seleccionados, gruposVisibles]
    );

    if (!abierto || !pedido) return null;

    const toggle = (id) => {
        setSeleccionados((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
    };

    const ruta = origen === 'cedis'
        ? route('control_pedidos.cedis.reportar_error_datos', pedido.id)
        : origen === 'auditar'
            ? route('control_pedidos.auditar.reportar_error_datos', pedido.id)
            : route('control_pedidos.delegado.reportar_error_datos', pedido.id);

    const enviar = (e) => {
        e.preventDefault();
        if (seleccionados.length === 0) {
            setError('Seleccione al menos un dato incorrecto.');
            return;
        }
        setProcesando(true);
        setError('');
        router.post(ruta, {
            campos_incorrectos: seleccionados,
            detalle: detalle.trim() || null,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                onSuccess?.();
                onClose();
            },
            onError: (errors) => {
                setError(errors.campos_incorrectos || errors.detalle || 'No se pudo reportar el error.');
            },
            onFinish: () => setProcesando(false),
        });
    };

    const copyDestino = (() => {
        if (!cola.activo) {
            return 'Seleccione los datos incorrectos. Solo quien corresponda podrá corregir; el resto será notificado.';
        }
        let texto = `Primero irá a ${cola.activo.destino} para corregir.`;
        if (cola.pendientes.length > 0) {
            texto += ` Después: ${cola.pendientes.map((g) => g.destino).join(', ')}.`;
        }
        return texto;
    })();

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-center`} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-2xl w-full`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-5 border-b theme-border flex justify-between items-start gap-3">
                    <div>
                        <p className="text-[10px] font-black uppercase theme-text-muted m-0 mb-1">Reportar error</p>
                        <EncabezadoFolioPedido pedido={pedido} size="sm" />
                        <p className="text-[10px] font-bold theme-text-muted mt-1.5 m-0">
                            Campos según origen: {etiquetaContexto}
                        </p>
                        <p className="text-xs theme-text-muted font-bold mt-2 m-0">
                            {copyDestino}
                        </p>
                    </div>
                    <button type="button" onClick={onClose} className="p-2 rounded-full theme-text-muted outline-none" aria-label="Cerrar">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <form onSubmit={enviar} className="p-5 space-y-4 max-h-[70vh] overflow-y-auto">
                    {gruposVisibles.length === 0 ? (
                        <p className="text-sm theme-text-muted font-bold m-0">
                            No hay campos reportables para este origen.
                        </p>
                    ) : gruposVisibles.map((grupo) => (
                        <div key={grupo.id}>
                            <p className={`${THEME_LABEL} mb-2`}>
                                {grupo.label}
                                <span className="normal-case font-bold theme-text-muted ml-1">→ {grupo.destino}</span>
                            </p>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                {grupo.campos.map((campo) => (
                                    <label
                                        key={campo.id}
                                        className={`flex items-center gap-2 p-2.5 rounded-xl border text-xs font-bold cursor-pointer ${
                                            seleccionados.includes(campo.id)
                                                ? 'border-orange-500/50 bg-orange-500/10 text-orange-700'
                                                : 'theme-border theme-element theme-text-main'
                                        }`}
                                    >
                                        <input
                                            type="checkbox"
                                            checked={seleccionados.includes(campo.id)}
                                            onChange={() => toggle(campo.id)}
                                            className="rounded border theme-border"
                                        />
                                        {campo.label}
                                    </label>
                                ))}
                            </div>
                        </div>
                    ))}

                    <div>
                        <label htmlFor="detalle-error-datos" className={`${THEME_LABEL} ml-1`}>Detalle (opcional)</label>
                        <textarea
                            id="detalle-error-datos"
                            value={detalle}
                            onChange={(e) => setDetalle(e.target.value)}
                            rows={3}
                            className={`${THEME_TEXTAREA} w-full mt-1.5 text-sm font-bold`}
                            placeholder="Describe el error para quien deba corregirlo..."
                        />
                    </div>

                    {error && (
                        <p className="text-xs font-bold text-red-500 m-0 flex items-center gap-1">
                            <AlertTriangle className="w-3.5 h-3.5" /> {error}
                        </p>
                    )}

                    <div className="flex flex-wrap gap-3 pt-2">
                        <button type="button" onClick={onClose} className={`${BTN_SECONDARY} outline-none`}>
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            disabled={procesando || seleccionados.length === 0}
                            className={`${BTN_PRIMARY} flex items-center gap-2 outline-none disabled:opacity-50 ml-auto`}
                        >
                            <AlertTriangle className="w-4 h-4" />
                            {procesando ? 'Reportando…' : 'Reportar error'}
                        </button>
                    </div>
                </form>
            </div>
        </div>,
        document.body
    );
}
