import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import axios from 'axios';
import { X, Upload } from 'lucide-react';
import { THEME_INPUT, THEME_SELECT, THEME_TEXTAREA } from '../../../../utils/geliaTheme';
import InputMoneda from '../../Partials/InputMoneda';
import { labelOpcionDireccion } from '../../Partials/codigoDireccionCliente';
import {
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_LABEL,
    BTN_PRIMARY,
    BTN_SECONDARY,
    etiquetaEnvio,
} from '../../Partials/pedidosBmaStyles';

const SECCION = `${THEME_LABEL} mb-2 block`;

const formInicial = (pedido = null) => ({
    cliente_direccion_id: pedido?.cliente_direccion_id || '',
    codigo_postal: pedido?.codigo_postal || '',
    domicilio_entrega: pedido?.domicilio_entrega || '',
    catalogo_paqueteria_id: pedido?.catalogo_paqueteria_id || '',
    catalogo_tipo_guia_id: pedido?.catalogo_tipo_guia_id || '',
    catalogo_zona_id: pedido?.catalogo_zona_id || '',
    peso_real_kg: '',
    numero_cajas: '',
    costo_envio: '',
    catalogo_banco_id: '',
    comentarios: '',
    comprobante: null,
});

export default function ModalLiberarResguardoAbierto({
    abierto,
    onClose,
    onSuccess,
    pedido,
    bancos = [],
    catalogos = {},
    routeName = 'control_pedidos.auditar.liberar_resguardo',
    titulo = 'Liberar resguardo abierto',
    etiquetaConfirmar = 'Liberar y anexar envío',
}) {
    const { data, setData, post, processing, reset, errors } = useForm(formInicial());
    const [previewNombre, setPreviewNombre] = useState('');
    const [direcciones, setDirecciones] = useState([]);
    const [cargandoDir, setCargandoDir] = useState(false);

    useEffect(() => {
        if (!abierto || !pedido) return;
        reset();
        setPreviewNombre('');
        setData(formInicial(pedido));
        setDirecciones([]);

        const clienteId = pedido.cliente_id || pedido.cliente?.id;
        if (!clienteId) return undefined;

        let cancelado = false;
        setCargandoDir(true);
        axios.get(`/api/clientes/id/${clienteId}/direccion-envio`)
            .then((res) => {
                if (cancelado) return;
                const dirs = res.data?.direcciones || [];
                setDirecciones(dirs);
                const preferida = dirs.find((d) => String(d.id) === String(pedido.cliente_direccion_id))
                    || dirs.find((d) => d.es_principal)
                    || dirs[0];
                if (preferida) {
                    setData('cliente_direccion_id', preferida.id);
                    setData('codigo_postal', preferida.codigo_postal || '');
                    setData('domicilio_entrega', preferida.direccion_resumida || '');
                }
            })
            .catch(() => {
                if (!cancelado) setDirecciones([]);
            })
            .finally(() => {
                if (!cancelado) setCargandoDir(false);
            });

        return () => { cancelado = true; };
    }, [abierto, pedido?.id]);

    if (!abierto || !pedido) return null;

    const folio = pedido.folio_remision || pedido.folio;
    const nComplementos = (pedido.complementos || []).length;
    const usaPesajeCedis = Boolean(pedido.pesaje_respondido_at);
    const cajasPesaje = pedido.cajas || [];
    const paqueterias = catalogos.paqueterias || [];
    const tiposGuia = catalogos.tipos_guia || [];
    const zonas = catalogos.zonas || [];
    const numeroCliente = pedido.cliente?.numero_cliente || pedido.numero_cliente;

    const aplicarDireccion = (id) => {
        const sel = direcciones.find((d) => String(d.id) === String(id));
        setData({
            ...data,
            cliente_direccion_id: id,
            codigo_postal: sel?.codigo_postal || '',
            domicilio_entrega: sel?.direccion_resumida || '',
        });
    };

    const enviar = (e) => {
        e.preventDefault();
        post(route(routeName, pedido.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
                onSuccess?.();
            },
        });
    };

    return createPortal(
        <div className={THEME_MODAL_OVERLAY} onClick={onClose}>
            <form
                className={`${THEME_MODAL_SHELL} max-w-lg w-full flex flex-col`}
                style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                onClick={(e) => e.stopPropagation()}
                onSubmit={enviar}
            >
                <div className="flex items-center justify-between p-5 border-b theme-border shrink-0">
                    <div>
                        <h2 className="text-lg font-black uppercase italic theme-text-main m-0">{titulo}</h2>
                        <p className="text-xs theme-text-muted font-bold mt-1 m-0">
                            Pedido {folio} · Capture dirección, logística, costo y comprobante
                        </p>
                    </div>
                    <button type="button" onClick={onClose} className="p-2 rounded-xl theme-element border theme-border outline-none">
                        <X className="w-4 h-4" />
                    </button>
                </div>

                <div className="p-5 space-y-4 overflow-y-auto flex-1">
                    {nComplementos > 0 && (
                        <div className="rounded-xl border border-teal-500/30 bg-teal-500/5 p-3">
                            <p className="text-[10px] font-black uppercase text-teal-700 dark:text-teal-400 m-0 mb-1">
                                Paquete con complementos
                            </p>
                            <p className="text-xs font-bold theme-text-main m-0">
                                Dirección y costo aplican al folio padre más {nComplementos} complemento{nComplementos === 1 ? '' : 's'}.
                            </p>
                        </div>
                    )}

                    <div>
                        <label className={SECCION}>Dirección de envío *</label>
                        <select
                            value={data.cliente_direccion_id}
                            onChange={(e) => aplicarDireccion(e.target.value)}
                            className={`${THEME_SELECT} w-full py-3`}
                            disabled={cargandoDir}
                        >
                            <option value="">{cargandoDir ? 'Cargando…' : 'Seleccionar dirección…'}</option>
                            {direcciones.map((d) => (
                                <option key={d.id} value={d.id}>
                                    {labelOpcionDireccion(numeroCliente, d)}
                                    {d.es_principal ? ' · Principal' : ''}
                                </option>
                            ))}
                        </select>
                        {errors.cliente_direccion_id && <p className="text-[10px] text-red-500 font-bold mt-1 m-0">{errors.cliente_direccion_id}</p>}
                        {!cargandoDir && direcciones.length === 0 && (
                            <p className="text-[10px] font-bold text-amber-600 mt-1 m-0">
                                Este cliente no tiene direcciones verificadas. Registre una antes de completar el envío.
                            </p>
                        )}
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label className={SECCION}>Paquetería *</label>
                            <select
                                value={data.catalogo_paqueteria_id}
                                onChange={(e) => setData('catalogo_paqueteria_id', e.target.value)}
                                className={`${THEME_SELECT} w-full py-3`}
                            >
                                <option value="">Seleccionar...</option>
                                {paqueterias.map((p) => (
                                    <option key={p.id} value={p.id}>{p.nombre}</option>
                                ))}
                            </select>
                            {errors.catalogo_paqueteria_id && <p className="text-[10px] text-red-500 font-bold mt-1 m-0">{errors.catalogo_paqueteria_id}</p>}
                        </div>
                        <div>
                            <label className={SECCION}>Tipo de guía *</label>
                            <select
                                value={data.catalogo_tipo_guia_id}
                                onChange={(e) => setData('catalogo_tipo_guia_id', e.target.value)}
                                className={`${THEME_SELECT} w-full py-3`}
                            >
                                <option value="">Seleccionar...</option>
                                {tiposGuia.map((t) => (
                                    <option key={t.id} value={t.id}>{t.nombre}</option>
                                ))}
                            </select>
                            {errors.catalogo_tipo_guia_id && <p className="text-[10px] text-red-500 font-bold mt-1 m-0">{errors.catalogo_tipo_guia_id}</p>}
                        </div>
                        <div>
                            <label className={SECCION}>Reexpedición *</label>
                            <select
                                value={data.catalogo_zona_id}
                                onChange={(e) => setData('catalogo_zona_id', e.target.value)}
                                className={`${THEME_SELECT} w-full py-3`}
                            >
                                <option value="">Seleccionar...</option>
                                {zonas.map((z) => (
                                    <option key={z.id} value={z.id}>{z.nombre}</option>
                                ))}
                            </select>
                            {errors.catalogo_zona_id && <p className="text-[10px] text-red-500 font-bold mt-1 m-0">{errors.catalogo_zona_id}</p>}
                        </div>
                        <div>
                            <label className={SECCION}>Código postal *</label>
                            <input
                                type="text"
                                value={data.codigo_postal}
                                onChange={(e) => setData('codigo_postal', e.target.value)}
                                className={`${THEME_INPUT} w-full py-3`}
                            />
                            {errors.codigo_postal && <p className="text-[10px] text-red-500 font-bold mt-1 m-0">{errors.codigo_postal}</p>}
                        </div>
                    </div>
                    {errors.domicilio_entrega && <p className="text-[10px] text-red-500 font-bold m-0">{errors.domicilio_entrega}</p>}

                    {usaPesajeCedis ? (
                        <div className="rounded-xl border theme-border theme-element p-3 space-y-1">
                            <p className="text-[10px] font-black uppercase theme-text-muted m-0">Pesaje CEDIS (no editable)</p>
                            <p className="text-sm font-bold theme-text-main m-0">
                                Peso: {pedido.peso_real_kg != null ? `${pedido.peso_real_kg} kg` : '—'}
                                {' · '}
                                Envíos: {pedido.numero_cajas ?? '—'}
                            </p>
                            {cajasPesaje.length > 0 && [...cajasPesaje].sort((a, b) => (a.orden ?? 0) - (b.orden ?? 0)).map((c, idx) => (
                                <p key={c.id || idx} className="text-xs theme-text-muted font-bold m-0">
                                    {etiquetaEnvio(idx, c)}
                                    {c.peso_cobrado_kg != null ? ` · cobrado ${c.peso_cobrado_kg} kg` : ''}
                                </p>
                            ))}
                        </div>
                    ) : (
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className={SECCION}>Peso real (kg) *</label>
                                <input
                                    type="number"
                                    step="0.0001"
                                    min="0"
                                    value={data.peso_real_kg}
                                    onChange={(e) => setData('peso_real_kg', e.target.value)}
                                    className={`${THEME_INPUT} w-full py-3`}
                                />
                                {errors.peso_real_kg && <p className="text-[10px] text-red-500 font-bold mt-1 m-0">{errors.peso_real_kg}</p>}
                            </div>
                            <div>
                                <label className={SECCION}>N° de envíos *</label>
                                <select
                                    value={data.numero_cajas === '' || data.numero_cajas == null ? '' : String(data.numero_cajas)}
                                    onChange={(e) => setData('numero_cajas', e.target.value)}
                                    className={`${THEME_SELECT} w-full py-3`}
                                >
                                    <option value="">Seleccionar...</option>
                                    <option value="0">N/A</option>
                                    {Array.from({ length: 20 }, (_, i) => i + 1).map((n) => (
                                        <option key={n} value={String(n)}>{n}</option>
                                    ))}
                                </select>
                                {errors.numero_cajas && <p className="text-[10px] text-red-500 font-bold mt-1 m-0">{errors.numero_cajas}</p>}
                            </div>
                        </div>
                    )}
                    <div>
                        <label className={SECCION}>Costo de envío *</label>
                        <InputMoneda value={data.costo_envio} onChange={(v) => setData('costo_envio', v)} className="w-full py-3" />
                        {errors.costo_envio && <p className="text-[10px] text-red-500 font-bold mt-1 m-0">{errors.costo_envio}</p>}
                    </div>
                    <div>
                        <label className={SECCION}>Banco / cuenta *</label>
                        <select
                            value={data.catalogo_banco_id}
                            onChange={(e) => setData('catalogo_banco_id', e.target.value)}
                            className={`${THEME_SELECT} w-full py-3`}
                        >
                            <option value="">Seleccionar...</option>
                            {bancos.map((b) => (
                                <option key={b.id} value={b.id}>{b.nombre}</option>
                            ))}
                        </select>
                        {errors.catalogo_banco_id && <p className="text-[10px] text-red-500 font-bold mt-1 m-0">{errors.catalogo_banco_id}</p>}
                    </div>
                    <div>
                        <label className={SECCION}>Comprobante de envío *</label>
                        <label className="flex items-center gap-2 p-4 rounded-xl border theme-border theme-element cursor-pointer">
                            <Upload className="w-4 h-4 theme-text-muted" />
                            <span className="text-xs font-bold theme-text-main">
                                {previewNombre || 'Adjuntar imagen o PDF'}
                            </span>
                            <input
                                type="file"
                                accept="image/*,.pdf"
                                className="hidden"
                                onChange={(e) => {
                                    const file = e.target.files?.[0] || null;
                                    setData('comprobante', file);
                                    setPreviewNombre(file?.name || '');
                                }}
                            />
                        </label>
                        {errors.comprobante && <p className="text-[10px] text-red-500 font-bold mt-1 m-0">{errors.comprobante}</p>}
                    </div>
                    <div>
                        <label className={SECCION}>Comentarios</label>
                        <textarea
                            value={data.comentarios}
                            onChange={(e) => setData('comentarios', e.target.value)}
                            rows={2}
                            className={`${THEME_TEXTAREA} w-full`}
                        />
                    </div>
                </div>

                <div className="flex justify-end gap-3 p-5 border-t theme-border shrink-0">
                    <button type="button" onClick={onClose} className={BTN_SECONDARY} disabled={processing}>Cancelar</button>
                    <button type="submit" className={BTN_PRIMARY} disabled={processing}>
                        {processing ? 'Guardando...' : etiquetaConfirmar}
                    </button>
                </div>
            </form>
        </div>,
        document.body
    );
}
