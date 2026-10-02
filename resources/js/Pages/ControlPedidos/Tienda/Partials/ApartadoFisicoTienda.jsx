import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { THEME_INPUT, THEME_LABEL, THEME_SELECT, THEME_TEXTAREA } from '../../../../utils/geliaTheme';
import { BTN_PRIMARY, BTN_SECONDARY, formatearFechaNegocio } from '../../Partials/pedidosBmaStyles';

export default function ApartadoFisicoTienda({ tarea, auth, config = {}, eventos = [], requisitos = {} }) {
    const apartado = tarea.apartado;
    const permisos = auth?.user?.permissions || [];
    const puedeSeparar = permisos.includes('control_pedidos.tienda.apartado.separar');
    const puedeDevolver = permisos.includes('control_pedidos.tienda.apartado.confirmar_devolucion');
    const puedeProrroga = permisos.includes('control_pedidos.tienda.apartado.aprobar_prorroga');
    const esVentaPropia = Number(apartado?.vendedor_id) > 0 && Number(auth?.user?.id) === Number(apartado.vendedor_id);
    const puedeValidarPago = permisos.includes('control_pedidos.salida.validar_pago') && !esVentaPropia;
    const puedeAutorizar = permisos.includes('control_pedidos.salida.autorizar') && !esVentaPropia;
    const puedeEntregar = permisos.includes('control_pedidos.salida.confirmar_entrega');
    const puedeModalidad = permisos.includes('control_pedidos.preparacion.solicitar')
        || permisos.includes('control_pedidos.preparacion.corregir');
    const [cantidad, setCantidad] = useState(String(tarea.piezas_solicitadas || 1));
    const [ubicacion, setUbicacion] = useState('');
    const [motivoDevolucion, setMotivoDevolucion] = useState('');
    const [ubicacionFinal, setUbicacionFinal] = useState(apartado?.ubicacion || '');
    const [foto, setFoto] = useState(null);
    const [motivoProrroga, setMotivoProrroga] = useState('');
    const [codigoModalidad, setCodigoModalidad] = useState('');
    const [motivoModalidad, setMotivoModalidad] = useState('');
    const [condicionCobro, setCondicionCobro] = useState('PAGADO');
    const [folioOperacion, setFolioOperacion] = useState('');
    const [receptor, setReceptor] = useState('');

    if (!apartado) return null;

    const separar = () => {
        router.post(route('control_pedidos.tienda.apartado.separar', tarea.id), {
            cantidad,
            ubicacion,
            version: apartado.version,
        });
    };

    const pedirDevolucion = () => {
        router.post(route('control_pedidos.tienda.apartado.devolucion', tarea.id), {
            motivo: motivoDevolucion,
            version: apartado.version,
        });
    };

    const confirmarDevolucion = () => {
        const form = new FormData();
        form.append('ubicacion', ubicacionFinal);
        form.append('version', apartado.version);
        if (foto) form.append('foto', foto);
        router.post(route('control_pedidos.tienda.apartado.confirmar_devolucion', tarea.id), form, { forceFormData: true });
    };

    const prorrogar = () => {
        router.post(route('control_pedidos.tienda.apartado.prorroga', tarea.id), {
            motivo: motivoProrroga,
            version: apartado.version,
        });
    };

    const confirmarPago = () => {
        router.post(route('control_pedidos.tienda.salida.pago', tarea.id), {
            condicion_cobro: condicionCobro,
            folio_operacion: folioOperacion,
            version: apartado.version,
        });
    };

    const autorizarSalida = () => {
        router.post(route('control_pedidos.tienda.salida.autorizar', tarea.id), {
            version: apartado.version,
        });
    };

    const confirmarEntrega = () => {
        router.post(route('control_pedidos.tienda.salida.entrega', tarea.id), {
            receptor,
            version: apartado.version,
        });
    };

    const cambiarModalidad = () => {
        router.post(route('control_pedidos.preparacion.cambiar_modalidad', tarea.id), {
            codigo_modalidad: codigoModalidad,
            motivo: motivoModalidad,
            version: tarea.version,
        });
    };

    const modalidades = (config.modalidades_catalogo || []).filter((m) => m.codigo !== tarea.modalidad?.codigo);
    const esRecogeHoy = tarea.modalidad?.codigo === 'RECOGE_TIENDA';
    const esTransferencia = tarea.modalidad?.codigo === 'RECOGE_TIENDA_TRANSFERENCIA';
    const esMunicipio = tarea.modalidad?.codigo === 'ENVIO_MUNICIPIO';
    const caratulaColocada = tarea.caratula?.estado === 'COLOCADA';
    const municipioListoSalida = esMunicipio && caratulaColocada && tarea.estado === 'RESPONDIDA';
    const cierrePdv = esRecogeHoy || esTransferencia || municipioListoSalida;
    const permitePorCobrar = esRecogeHoy || (esMunicipio && requisitos.permite_por_cobrar);
    const tareaAbierta = !['EN_TRASLADO', 'RECIBIDA_CEDIS', 'LIBERADA', 'CANCELADA'].includes(tarea.estado);

    return (
        <section className="rounded-xl border theme-border p-4 space-y-3 bg-amber-500/5">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h3 className="text-[10px] font-black uppercase tracking-widest m-0">Apartado físico</h3>
                <span className="text-[10px] font-black uppercase tracking-wider px-2 py-1 rounded-full border theme-border">
                    {apartado.estado_label}
                </span>
            </div>
            <p className="text-xs font-bold m-0 theme-text-muted">{apartado.aviso}</p>
            <div className="grid sm:grid-cols-3 gap-3 text-sm">
                <p className="m-0"><span className="theme-text-muted">Cantidad</span><br /><strong>{apartado.cantidad || '—'}</strong></p>
                <p className="m-0"><span className="theme-text-muted">Ubicación</span><br /><strong>{apartado.ubicacion || '—'}</strong></p>
                <p className="m-0"><span className="theme-text-muted">Vence</span><br /><strong>{apartado.vence_at ? formatearFechaNegocio(apartado.vence_at) : '—'}</strong></p>
                <p className="m-0"><span className="theme-text-muted">Cobro</span><br /><strong>{apartado.condicion_cobro || 'Pendiente'}</strong></p>
                <p className="m-0"><span className="theme-text-muted">Salida</span><br /><strong>{apartado.salida_autorizada_at ? formatearFechaNegocio(apartado.salida_autorizada_at) : 'Sin autorizar'}</strong></p>
                <p className="m-0"><span className="theme-text-muted">Entrega</span><br /><strong>{apartado.receptor_nombre || '—'}</strong></p>
            </div>

            {cierrePdv && !apartado.pago_confirmado_at && puedeValidarPago && (['POR_SEPARAR', 'SEPARADA'].includes(apartado.estado) || municipioListoSalida) && (
                <div className="grid sm:grid-cols-3 gap-3 items-end">
                    <div>
                        <label className={THEME_LABEL}>Condición de cobro</label>
                        <select className={`${THEME_SELECT} w-full mt-1`} value={condicionCobro} onChange={(e) => setCondicionCobro(e.target.value)}>
                            <option value="PAGADO">Pagado</option>
                            {permitePorCobrar && <option value="POR_COBRAR">Por cobrar</option>}
                        </select>
                    </div>
                    <div>
                        <label className={THEME_LABEL}>Folio de operación</label>
                        <input className={`${THEME_INPUT} w-full mt-1`} value={folioOperacion} onChange={(e) => setFolioOperacion(e.target.value)} />
                    </div>
                    <button type="button" className={BTN_PRIMARY} onClick={confirmarPago}>Validar pago</button>
                </div>
            )}

            {cierrePdv && apartado.pago_confirmado_at && puedeAutorizar
                && (apartado.estado === 'SEPARADA' || municipioListoSalida) && (
                <button type="button" className={BTN_PRIMARY} onClick={autorizarSalida}>Autorizar salida</button>
            )}

            {esRecogeHoy && apartado.estado === 'LISTA_PARA_SALIDA' && puedeEntregar && (
                <div className="grid sm:grid-cols-2 gap-3 items-end">
                    <div>
                        <label className={THEME_LABEL}>Quién recibe</label>
                        <input className={`${THEME_INPUT} w-full mt-1`} value={receptor} onChange={(e) => setReceptor(e.target.value)} />
                    </div>
                    <button type="button" className={BTN_PRIMARY} onClick={confirmarEntrega}>Confirmar entrega</button>
                </div>
            )}

            {esTransferencia && apartado.resguardo_pdv_id && (
                <p className="text-xs font-bold m-0">
                    Custodia en PDV. La entrega se confirma una sola vez en el resguardo {apartado.resguardo_pdv_id}.
                </p>
            )}

            {apartado.estado === 'POR_SEPARAR' && puedeSeparar && (
                <div className="grid sm:grid-cols-3 gap-3 items-end">
                    <div>
                        <label className={THEME_LABEL}>Cantidad</label>
                        <input className={`${THEME_INPUT} w-full mt-1`} type="number" min="1" value={cantidad} onChange={(e) => setCantidad(e.target.value)} />
                    </div>
                    <div>
                        <label className={THEME_LABEL}>Ubicación</label>
                        <input className={`${THEME_INPUT} w-full mt-1`} value={ubicacion} onChange={(e) => setUbicacion(e.target.value)} placeholder="Anaquel o zona" />
                    </div>
                    <button type="button" className={BTN_PRIMARY} onClick={separar}>Separar</button>
                </div>
            )}

            {apartado.estado === 'SEPARADA' && puedeSeparar && (
                <div className="space-y-2">
                    <label className={THEME_LABEL}>Recogida no concretada</label>
                    <textarea className={`${THEME_TEXTAREA} w-full mt-1`} value={motivoDevolucion} onChange={(e) => setMotivoDevolucion(e.target.value)} placeholder="Motivo" />
                    <button type="button" className={BTN_SECONDARY} onClick={pedirDevolucion}>Pasar a devolución pendiente</button>
                </div>
            )}

            {apartado.estado === 'SEPARADA' && puedeProrroga && !apartado.prorroga_aplicada && (
                <div className="space-y-2">
                    <label className={THEME_LABEL}>Prórroga VIP (una vez, solo mueve el vencimiento)</label>
                    <textarea className={`${THEME_TEXTAREA} w-full mt-1`} value={motivoProrroga} onChange={(e) => setMotivoProrroga(e.target.value)} placeholder="Motivo de la prórroga" />
                    <button type="button" className={BTN_SECONDARY} onClick={prorrogar}>Aprobar prórroga</button>
                </div>
            )}

            {apartado.estado === 'DEVOLUCION_PENDIENTE' && puedeDevolver && (
                <div className="grid sm:grid-cols-2 gap-3 items-end">
                    <div>
                        <label className={THEME_LABEL}>Ubicación final</label>
                        <input className={`${THEME_INPUT} w-full mt-1`} value={ubicacionFinal} onChange={(e) => setUbicacionFinal(e.target.value)} />
                    </div>
                    <div>
                        <label className={THEME_LABEL}>Foto</label>
                        <input className="mt-1 block w-full text-sm" type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => setFoto(e.target.files?.[0] || null)} />
                    </div>
                    <button type="button" className={BTN_PRIMARY} onClick={confirmarDevolucion}>Confirmar devolución al anaquel</button>
                </div>
            )}

            {puedeModalidad && tareaAbierta && modalidades.length > 0 && (
                <div className="space-y-2 border-t theme-border pt-3">
                    <label className={THEME_LABEL}>Cambiar modalidad</label>
                    <select className={`${THEME_SELECT} w-full mt-1`} value={codigoModalidad} onChange={(e) => setCodigoModalidad(e.target.value)}>
                        <option value="">Seleccione</option>
                        {modalidades.map((m) => <option key={m.codigo} value={m.codigo}>{m.nombre}</option>)}
                    </select>
                    <textarea className={`${THEME_TEXTAREA} w-full`} value={motivoModalidad} onChange={(e) => setMotivoModalidad(e.target.value)} placeholder="Motivo del cambio" />
                    <button type="button" className={BTN_SECONDARY} onClick={cambiarModalidad}>Registrar cambio</button>
                </div>
            )}

            {eventos.length > 0 && (
                <ul className="m-0 p-0 list-none space-y-1 text-xs">
                    {eventos.map((evento) => (
                        <li key={evento.id} className="theme-text-muted">
                            <strong className="theme-text-main">{evento.tipo_label}</strong>
                            {' · '}{evento.usuario || 'Sistema'}
                            {evento.created_at ? ` · ${formatearFechaNegocio(evento.created_at)}` : ''}
                            {evento.motivo ? ` · ${evento.motivo}` : ''}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
