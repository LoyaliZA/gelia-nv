import React, { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { CheckCircle2, AlertTriangle, ChevronDown, ClipboardCheck, FileText, X } from 'lucide-react';
import usePedidoDialog from './usePedidoDialog';
import RutaPedidoTienda from './RutaPedidoTienda';
import AlternativasDisponibilidadCedis from './AlternativasDisponibilidadCedis';
import ResumenCambiosDisponibilidad from './ResumenCambiosDisponibilidad';
import ModalSeleccionProductosDetalle from './ModalSeleccionProductosDetalle';
import { anexosPendientesRevision, cambiosDisponibilidad, equivalenciasUnidades } from './cambiosDisponibilidad';
import GaleriaEvidenciasPedido from './GaleriaEvidenciasPedido';
import ModalVistaPreviaDocumento, { MiniaturaDocumento } from './ModalVistaPreviaDocumento';
import { GELIA_BTN_OUTLINE, THEME_BTN_PRIMARY, THEME_BTN_SECONDARY, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../utils/geliaTheme';
import { presentacionPedidoCedis } from './presentacionPedidoCedis';
import CapturaExcepcionesConsulta, {
    EstadoPedidoConsulta, EtiquetasPieza, aplicarCambioExcepcion, excepcionDesdeElegido, liberarPreviews, mensajeValidacionExcepciones,
} from './CapturaExcepcionesConsulta';

function formDataDe({ confirmacion, excepciones, archivos }) {
    const form = new FormData();
    form.append('confirmacion_explicita', confirmacion ? '1' : '0');
    excepciones.forEach((pieza, indice) => {
        for (const campo of ['descripcion', 'sku', 'cantidad', 'condicion', 'detalle']) {
            form.append(`excepciones[${indice}][${campo}]`, String(pieza[campo] || ''));
        }
        for (const atributo of ['unica_pieza', 'mejor_ejemplar']) form.append(`excepciones[${indice}][${atributo}]`, pieza[atributo] ? '1' : '0');
        if (pieza.origen_cambio) form.append(`excepciones[${indice}][origen_unidad_id]`, String(pieza.origen_cambio));
        if (pieza.condicion === 'detalle') (pieza.archivos || []).forEach(archivo => form.append(`excepciones[${indice}][evidencias][]`, archivo));
    });
    archivos.forEach(archivo => form.append('evidencias[]', archivo));
    return form;
}

export default function PanelRespuestaDisponibilidad({ modo = 'seccion', abierto = true, onClose, pedido = {}, documentos = [], revision = null, piezas = [], puedeResponder = false, tomada = false, origen = 'CEDIS', mostrarCambios = true, evidenciasGuardadas = [], onDirtyChange, avisoActualizacion = '', puedeLeerDocumentos = true, onEnviar }) {
    const solicitud = pedido.solicitud_preparacion || pedido.solicitud_preparacion_json || {};
    const actualizacion = anexosPendientesRevision(solicitud) || Boolean(revision?.actualizacion_pendiente);
    const cambios = cambiosDisponibilidad(solicitud, pedido.documentos || documentos);
    const [respuestasCambios, setRespuestasCambios] = useState({});
    const [origenIncidencia, setOrigenIncidencia] = useState(null);
    const persistidasRef = useRef({});
    const colaRef = useRef(Promise.resolve());
    const pendientesRef = useRef(new Map());
    const temporizadorRef = useRef(null);
    const erroresGuardadoRef = useRef(new Map());
    const fotosRecuperadasRef = useRef(new Map());
    const cambiosPreviosRef = useRef(null);
    const identidadesRef = useRef(new Map());
    const identidadActual = clave => identidadesRef.current.get(clave) || clave;
    const tienda = origen === 'Tienda';
    const [capturaRapida, setCapturaRapida] = useState(null);
    const [estado, setEstado] = useState('');
    const [excepciones, setExcepciones] = useState([]);
    const [manual, setManual] = useState(false);
    const [archivos, setArchivos] = useState([]);
    const [previews, setPreviews] = useState(() => actualizacion || !puedeLeerDocumentos ? [] : [...evidenciasGuardadas.map(d => ({ url: d.url, name: d.nombre_original, mime: d.mime_type, remoto: true })), ...(pedido.documentos || []).filter(d => d.tipo === 'evidencia_condicion' && d.url && d.activo !== false && (!d.relacion_tipo || d.relacion_tipo === 'revision_general')).map(d => ({ url: d.url, name: d.nombre_original, mime: d.mime_type, remoto: true }))]);
    const [vista, setVista] = useState(null);
    // Captura vigente del pedido: se conserva a la vista; una nueva solo se adjunta si el pedido cambió.
    const capturaAnterior = !actualizacion || !puedeLeerDocumentos ? [] : (() => {
        const generales = (pedido.documentos || documentos || []).filter(d => d.tipo === 'evidencia_condicion' && d.activo !== false && d.url && (!d.relacion_tipo || d.relacion_tipo === 'revision_general'));
        const vigentes = revision?.evidencia_actual_ids ? generales.filter(d => revision.evidencia_actual_ids.includes(d.id)) : [];
        return vigentes.length ? vigentes : generales;
    })();
    const secuencia = useRef(0);
    // Recuperar las piezas de las unidades guardadas sin convertirlas en una respuesta enviada.
    useEffect(() => {
        if (!tienda || !actualizacion) return;
        // Solo reconciliar referencias de esta misma consulta; no cambia el tratamiento de ciclos.
        const equivalencias = cambiosPreviosRef.current && cambiosPreviosRef.current.consulta === revision?.actualizacion_id
            ? equivalenciasUnidades(cambiosPreviosRef.current.cambios, cambios) : new Map();
        cambiosPreviosRef.current = { consulta: revision?.actualizacion_id, cambios };
        for (const [anterior, nueva] of equivalencias) {
            identidadesRef.current.set(anterior, nueva.clave);
            if (persistidasRef.current[anterior]) { persistidasRef.current[nueva.clave] = persistidasRef.current[anterior]; delete persistidasRef.current[anterior]; }
            if (erroresGuardadoRef.current.has(anterior)) { erroresGuardadoRef.current.set(nueva.clave, erroresGuardadoRef.current.get(anterior)); erroresGuardadoRef.current.delete(anterior); }
            if (pendientesRef.current.has(anterior)) {
                const [, resultado, filas] = pendientesRef.current.get(anterior);
                pendientesRef.current.delete(anterior);
                pendientesRef.current.set(nueva.clave, [nueva, resultado, filas.map(p => ({ ...p, origen_cambio: nueva.clave }))]);
            }
        }
        if (equivalencias.size) {
            setRespuestasCambios(actual => Object.fromEntries(Object.entries(actual).map(([clave, valor]) => [identidadActual(clave), valor])));
            setOrigenIncidencia(actual => equivalencias.get(actual?.clave) || actual);
        }
        setExcepciones(actual => {
            actual = actual.map(p => identidadActual(p.origen_cambio) !== p.origen_cambio ? { ...p, origen_cambio: identidadActual(p.origen_cambio) } : p);
            const recuperadas = cambios.flatMap(cambio => {
                if (actual.some(fila => fila.origen_cambio === cambio.clave) || cambio.resultado !== 'incidencia') return [];
                const unidad = (solicitud.anexo_sin_remision || []).flatMap(a => a.unidades || []).find(u => u.unidad_id === cambio.unidad_id);
                return (unidad?.revision_ids || []).flatMap(id => {
                    const pieza = (pedido.excepciones || []).find(p => Number(p.id) === Number(id));
                    if (!pieza) return [];
                    return [{ ...pieza, clave: ++secuencia.current, origen_cambio: cambio.clave, cantidad: pieza.cantidad || 1,
                        condicion: pieza.estado_fisico === 'sin_existencia' ? 'faltante' : 'detalle', archivos: [],
                        previews: (pieza.documentos || []).map(d => ({ url: d.url, name: d.nombre_original, mime: d.mime_type, remoto: true })),
                    }];
                });
            });
            return recuperadas.length ? [...actual, ...recuperadas] : actual;
        });
    }, [pedido.excepciones, solicitud.anexo_sin_remision, tienda, actualizacion]);
    useEffect(() => () => clearTimeout(temporizadorRef.current), []);
    const incidenciasRef = useRef(null);
    useEffect(() => { if (origenIncidencia) incidenciasRef.current?.scrollIntoView?.({ block: 'nearest', behavior: 'smooth' }); }, [origenIncidencia]);
    const excepcionesRef = useRef([]);
    excepcionesRef.current = excepciones;
    useEffect(() => () => excepcionesRef.current.forEach(fila => liberarPreviews(fila.previews)), []);
    const [error, setError] = useState('');
    const [ocupado, setOcupado] = useState(false);
    const ciclo = revision?.actualizacion_id || revision?.respuesta_id || 'inicial';
    const [cicloEnviado, setCicloEnviado] = useState(null);
    const enviada = cicloEnviado === ciclo;
    const cicloAnterior = useRef(ciclo);
    const previewsRef = useRef([]);
    previewsRef.current = previews;
    useEffect(() => () => previewsRef.current.forEach(p => { if (p.url?.startsWith('blob:')) URL.revokeObjectURL(p.url); }), []);
    const almacenId = pedido.almacen?.id || pedido.almacen_id;
    const guardando = ocupado;
    const respondida = enviada || Boolean(revision?.estado && !actualizacion);
    const tieneCaptura = Boolean(estado || excepciones.length || archivos.length || Object.keys(respuestasCambios).length || capturaRapida);
    useEffect(() => { onDirtyChange?.(guardando || (!respondida && tieneCaptura)); }, [guardando, respondida, tieneCaptura, onDirtyChange]);
    useEffect(() => {
        if (cicloAnterior.current === ciclo || guardando) return;
        // Una captura aún no enviada se conserva, incluso ante nuevas props.
        if (tieneCaptura && cicloEnviado !== cicloAnterior.current) return;
        cicloAnterior.current = ciclo;
        if (!puedeLeerDocumentos && !actualizacion) {
            // El servidor ya confirmó la respuesta, pero la etapa terminó. Conservar
            // las vistas locales enviadas evita descargar archivos fuera de alcance.
            setCicloEnviado(ciclo);
            setArchivos([]);
            return;
        }
        liberarPreviews(previewsRef.current);
        excepcionesRef.current.forEach(fila => liberarPreviews(fila.previews));
        setEstado(''); setExcepciones([]); setArchivos([]); setRespuestasCambios({});
        setOrigenIncidencia(null); setCapturaRapida(null); setManual(false); setError('');
        setPreviews(actualizacion ? [] : (pedido.documentos || []).filter(d => d.tipo === 'evidencia_condicion' && d.activo !== false && d.url && (!d.relacion_tipo || d.relacion_tipo === 'revision_general') && (!revision?.evidencia_actual_ids || revision.evidencia_actual_ids.includes(d.id))).map(d => ({ url: d.url, name: d.nombre_original, mime: d.mime_type, remoto: true })));
    }, [ciclo, guardando, tieneCaptura, cicloEnviado, actualizacion, pedido.documentos, revision?.evidencia_actual_ids, puedeLeerDocumentos]);
    const editable = tomada && puedeResponder && !respondida;
    const dialogo = usePedidoDialog({ abierto: modo === 'dialogo' && abierto, onClose, bloqueado: guardando });
    const firmaDe = (resultado, afectadas) => JSON.stringify([resultado, afectadas.map(p => [p.descripcion, p.sku, p.condicion, p.cantidad, p.detalle, p.unica_pieza, p.mejor_ejemplar,
        (p.archivos || []).map(f => [f.name, f.size, f.lastModified]), (p.previews || []).filter(v => v.remoto).map(v => v.url)])]);
    const completarFotos = afectadas => Promise.all(afectadas.map(async pieza => ({ ...pieza, archivos: [
        ...await Promise.all((pieza.previews || []).filter(v => v.remoto).map(async vista => {
            if (!fotosRecuperadasRef.current.has(vista.url)) {
                const respuesta = await fetch(vista.url);
                if (!respuesta.ok) throw new Error('No se pudo recuperar la foto guardada. Intente de nuevo.');
                fotosRecuperadasRef.current.set(vista.url, new File([await respuesta.blob()], vista.name, { type: vista.mime }));
            }
            return fotosRecuperadasRef.current.get(vista.url);
        })), ...(pieza.archivos || []),
    ] })));
    const guardarUnidad = async (cambio, resultado, afectadas = []) => {
        // El servidor rechaza guardar una unidad que ya pertenece a una respuesta enviada.
        if (tienda && cambio.ya_atendida) return;
        const firma = tienda ? firmaDe(resultado, afectadas) : `${resultado}:${afectadas.map(pieza => `${pieza.descripcion}|${pieza.condicion}|${pieza.cantidad}`).join(';')}`;
        if (persistidasRef.current[cambio.clave] === firma || (cambio.persistida && cambio.resultado === resultado && afectadas.length === 0)) return;
        if (tienda) afectadas = await completarFotos(afectadas);
        const form = formDataDe({ confirmacion: false, excepciones: afectadas.map(pieza => ({ ...pieza, origen_cambio: cambio.unidad_id || cambio.clave })), archivos: [] });
        form.append('persistir_unidad', '1');
        form.append('cambios_revisados[0][tipo]', cambio.tipo);
        form.append('cambios_revisados[0][indice]', String(cambio.indice ?? 0));
        form.append('cambios_revisados[0][unidad_id]', cambio.unidad_id || cambio.clave);
        if (cambio.documento_id) form.append('cambios_revisados[0][documento_id]', String(cambio.documento_id));
        form.append('cambios_revisados[0][resultado]', resultado);
        if (revision?.actualizacion_id) form.append('actualizacion_id', revision.actualizacion_id);
        try {
            await onEnviar?.(form);
            persistidasRef.current[identidadActual(cambio.clave)] = firma;
        } catch (e) {
            const errores = e?.response?.data?.errors;
            setError(errores ? Object.values(errores).flat().join(' ') : e?.response?.data?.message || 'No se pudo guardar la revisión.');
            if (tienda) throw e;
        }
    };
    const vaciarGuardados = () => {
        clearTimeout(temporizadorRef.current);
        const pendientes = [...pendientesRef.current.values()];
        pendientesRef.current.clear();
        if (pendientes.length) colaRef.current = colaRef.current.then(async () => {
            for (const args of pendientes) {
                try { await guardarUnidad(...args); erroresGuardadoRef.current.delete(identidadActual(args[0].clave)); }
                catch (e) { erroresGuardadoRef.current.set(identidadActual(args[0].clave), e); setError(e?.response?.data?.message || e.message || 'No se pudo guardar la revisión.'); }
            }
        });
        return colaRef.current;
    };
    const persistirUnidad = (cambio, resultado, afectadas = []) => {
        if (!tienda) return guardarUnidad(cambio, resultado, afectadas);
        pendientesRef.current.set(cambio.clave, [cambio, resultado, afectadas]);
        clearTimeout(temporizadorRef.current);
        temporizadorRef.current = setTimeout(vaciarGuardados, 450);
    };
    const piezasCompletasDe = (clave, filas) => {
        const de = filas.filter(fila => fila.origen_cambio === clave);
        if (!de.length) return [];
        if (de.some(pieza => !String(pieza.descripcion || '').trim())) return [];
        if (de.some(pieza => pieza.condicion === 'detalle' && !tienda && !pieza.unica_pieza && !pieza.mejor_ejemplar)) return [];
        if (de.some(pieza => pieza.condicion === 'detalle' && !(tienda ? pieza.previews?.length : pieza.archivos?.length))) return [];
        return de;
    };
    const persistirSiCompleta = (clave, filas) => {
        const cambio = cambios.find(c => c.clave === clave);
        const completas = clave ? piezasCompletasDe(clave, filas) : [];
        if (cambio && completas.length) persistirUnidad(cambio, 'incidencia', completas);
    };
    const cambiar = (indice, cambiosPieza) => setExcepciones(actual => {
        const siguiente = actual.map((fila, i) => i !== indice ? fila : aplicarCambioExcepcion(fila, cambiosPieza));
        if (!tienda) persistirSiCompleta(siguiente[indice]?.origen_cambio, siguiente);
        return siguiente;
    });
    useEffect(() => {
        if (!tienda || !actualizacion) return;
        for (const clave of new Set(excepciones.map(p => p.origen_cambio).filter(Boolean))) persistirSiCompleta(clave, excepciones);
    }, [excepciones]);
    const quitar = indice => {
        liberarPreviews(excepciones[indice]?.previews);
        setExcepciones(actual => actual.filter((_, i) => i !== indice));
    };
    const responderCambio = (cambio, respuesta) => {
        setRespuestasCambios(actual => ({ ...actual, [cambio.clave]: respuesta }));
        setExcepciones(actual => {
            actual.filter(f => f.origen_cambio === cambio.clave).forEach(f => liberarPreviews(f.previews));
            return actual.filter(f => f.origen_cambio !== cambio.clave);
        });
        if (origenIncidencia?.clave === cambio.clave) setOrigenIncidencia(null);
        if (respuesta === 'correcto') persistirUnidad(cambio, 'correcto');
    };
    const reportarCambio = (cambio, condicion) => {
        if (cambio.esDocumento) { setCapturaRapida({ origen: cambio }); return; }
        const pieza = excepcionDesdeElegido(cambio, {
            clave: ++secuencia.current, origen_cambio: cambio.clave, condicion,
            cantidad: condicion === 'faltante' ? Number(cambio.cantidad) || 1 : 1,
        });
        setOrigenIncidencia(cambio);
        setRespuestasCambios(actual => ({ ...actual, [cambio.clave]: 'incidencia' }));
        setExcepciones(actual => actual.some(f => f.origen_cambio === cambio.clave) ? actual : [...actual, pieza]);
        if (condicion === 'faltante') persistirUnidad(cambio, 'incidencia', [pieza]);
    };
    const agregarSeleccionDetalle = (seleccionadas, origen) => {
        let fotos = excepciones.filter(f => f.porFoto).length;
        const nuevas = seleccionadas.map(pieza => excepcionDesdeElegido(pieza.porFoto ? { ...pieza, descripcion: `Pieza con detalle ${++fotos}` } : pieza, {
            clave: ++secuencia.current, origen_cambio: origen?.clave, abrir_detalle: seleccionadas.length === 1, porFoto: Boolean(pieza.porFoto),
        }));
        setExcepciones(actual => [...actual, ...nuevas]);
        // Las piezas sin existencias ya están completas: se guardan sin esperar otro cambio.
        if (!tienda && origen && nuevas.some(pieza => pieza.condicion === 'faltante')) persistirSiCompleta(origen.clave, [...excepciones, ...nuevas]);
        if (seleccionadas.some(pieza => !pieza.sku && !pieza.porFoto)) setManual(true);
        if (origen) {
            setOrigenIncidencia(origen);
            setRespuestasCambios(actual => ({ ...actual, [origen.clave]: 'incidencia' }));
        }
        setCapturaRapida(null);
        requestAnimationFrame(() => incidenciasRef.current?.scrollIntoView?.({ block: 'nearest' }));
    };
    // Lo ya respondido pasa al historial: el empacador solo ve lo que sigue por trabajar.
    const archivadas = useMemo(() => new Set(excepciones.filter(pieza => {
        const origen = cambios.find(c => c.clave === pieza.origen_cambio);
        return Boolean(origen?.persistida && origen.resultado === 'incidencia' && origen.clave !== origenIncidencia?.clave);
    }).map(pieza => pieza.clave)), [excepciones, cambios, origenIncidencia]);
    const presentacion = presentacionPedidoCedis(pedido);
    const referencias = documentos.filter(d => d.url);

    const enviar = async () => {
        if (guardando || !editable) return;
        setError('');
        const afectadas = actualizacion || estado === 'detalle' ? excepciones : [];
        if (!actualizacion && !estado) return setError('Indica el estado del pedido.');
        if (!actualizacion && estado === 'detalle') {
            const mensaje = mensajeValidacionExcepciones(afectadas, { usaPreviews: tienda });
            if (mensaje) return setError(mensaje);
        }
        const pendientes = cambios.filter(c => c.pendiente && !respuestasCambios[c.clave] && !c.persistida);
        if (actualizacion && pendientes.length) return setError('Revise cada captura: marque Todo OK o registre las piezas con detalle.');
        if (actualizacion && cambios.some(c => (respuestasCambios[c.clave] || c.resultado) === 'incidencia' && !afectadas.some(f => f.origen_cambio === c.clave) && !(c.persistida && c.resultado === 'incidencia'))) return setError('Registre la pieza con detalle o marque Todo OK en su captura.');
        if (actualizacion) {
            const mensaje = mensajeValidacionExcepciones(afectadas, { usaPreviews: tienda });
            if (afectadas.length && mensaje) return setError(mensaje);
        }
        if (!archivos.length && (actualizacion ? !capturaAnterior.length : !evidenciasGuardadas.length)) return setError('Adjunta evidencia en Captura del pedido.');
        setOcupado(true);
        try {
            if (tienda && actualizacion) {
                // Reintentar el último contenido local y esperar todos los avances antes del envío explícito.
                for (const cambio of cambios) {
                    const resultado = respuestasCambios[cambio.clave] || cambio.resultado;
                    if (resultado) persistirUnidad(cambio, resultado, afectadas.filter(p => p.origen_cambio === cambio.clave));
                }
                await vaciarGuardados();
                if (erroresGuardadoRef.current.size) throw erroresGuardadoRef.current.values().next().value;
            }
            const finales = tienda && actualizacion ? await completarFotos(afectadas) : afectadas;
            const form = formDataDe({ confirmacion: actualizacion ? finales.length === 0 : estado === 'correcto', excepciones: finales, archivos });
            if (!actualizacion) evidenciasGuardadas.forEach(d => form.append('evidencias_guardadas[]', String(d.id)));
            if (actualizacion) cambios.filter(c => c.indice !== undefined && (respuestasCambios[c.clave] || c.resultado)).forEach((c, i) => {
                form.append(`cambios_revisados[${i}][tipo]`, c.tipo);
                form.append(`cambios_revisados[${i}][indice]`, String(c.indice ?? 0));
                form.append(`cambios_revisados[${i}][unidad_id]`, c.unidad_id || c.clave);
                if (c.documento_id) form.append(`cambios_revisados[${i}][documento_id]`, String(c.documento_id));
                form.append(`cambios_revisados[${i}][resultado]`, respuestasCambios[c.clave] || c.resultado);
            });
            if (revision?.actualizacion_id) form.append('actualizacion_id', revision.actualizacion_id);
            await onEnviar?.(form);
            setCicloEnviado(ciclo);
        } catch (e) {
            const errores = e?.response?.data?.errors;
            setError(errores ? Object.values(errores).flat().join(' ') : e?.response?.data?.message || 'No se pudo registrar la revisión.');
        } finally { setOcupado(false); }
    };
    if (modo === 'dialogo' && !abierto) return null;
    const resultado = !puedeLeerDocumentos && cicloEnviado === ciclo && respondida ? excepciones : piezas.length && !actualizacion ? piezas : actualizacion || estado === 'detalle' ? excepciones : [];
    const contenido = <section className="gelia-preparacion gelia-cedis-disponibilidad" aria-label={`Consulta de disponibilidad en ${origen}`}>
        {avisoActualizacion && <p role="status" className="gelia-cedis-alerta text-sm m-0">{avisoActualizacion}</p>}
        <header className="gelia-preparacion-contexto">
            <div className="min-w-0">
                <div className="registrar-tienda-seccion-titulo"><ClipboardCheck size={18} aria-hidden /><h3>{actualizacion ? 'Actualización de consulta' : 'Disponibilidad'} · {origen}</h3></div>
                <dl className="gelia-preparacion-meta">
                    {pedido.cliente?.nombre && <div><dt className="shrink-0">Cliente</dt><dd>{pedido.cliente.nombre}</dd></div>}

                </dl>
            </div>
            {(origen === 'CEDIS' || presentacion.modalidad === 'municipal') && <RutaPedidoTienda origen={presentacion.origen} destino={presentacion.destino} tipoDestino={presentacion.tipoDestino} />}
            <button className={GELIA_BTN_OUTLINE} type="button" disabled={!referencias.length || !puedeLeerDocumentos} onClick={() => setVista({ documentos: referencias, indice: 0 })}><FileText size={16} aria-hidden />Ver pedido{referencias.length > 1 ? ` (${referencias.length})` : ''}</button>
        </header>
        <div className="gelia-preparacion-workspace" data-pedido-visible="false">
            <div className="gelia-preparacion-captura space-y-4">
                {actualizacion ? <ResumenCambiosDisponibilidad solicitud={solicitud} documentos={pedido.documentos || documentos} vista="revision" interactivo={editable} puedeVerDocumentos={puedeLeerDocumentos}
                    respuestas={respuestasCambios} onResponder={responderCambio} onIncidencia={reportarCambio} disabled={guardando}
                    onVer={(documentos, indice) => setVista({ documentos, indice })} />
                    : mostrarCambios && <AlternativasDisponibilidadCedis pedido={pedido} puedeVerDocumentos={puedeLeerDocumentos} onVer={(documentos, indice) => setVista({ documentos, indice })} />}

                {actualizacion && !respondida && <p className="text-sm theme-text-main m-0" role="status">Revise las capturas de los anexos. Los retiros ya quedaron informados. La captura vigente se conserva; tome una nueva solo si el pedido cambió.</p>}
                {respondida ? <div className="grid gap-3" role="status">
                    <span className="gelia-estado-vivo gelia-estado-vivo--compacto gelia-estado-vivo--exito justify-self-start">Consulta respondida</span>
                    <p className="text-sm theme-text-muted m-0">Ventas confirma las piezas con el cliente.</p>
                    {resultado.length > 0 && <ul className="list-none p-0 m-0 space-y-2">{resultado.map((p, i) => {
                        const fotos = p.previews || (puedeLeerDocumentos ? pedido.documentos || [] : []).filter(d => d.activo !== false && d.relacion_tipo === 'revision_producto' && Number(d.relacion_id) === Number(p.id)).map(d => ({ url: d.url, name: d.nombre_original, mime: d.mime_type, remoto: true }));
                        return <li key={p.id || i} className="gelia-cedis-panel"><details open={resultado.length <= 3 ? true : undefined}><summary className="gelia-cedis-producto-resumen"><span><strong>{p.descripcion}</strong><span className="block text-xs theme-text-muted">{p.sku}{Number(p.cantidad) > 1 ? ` · ${p.cantidad} pza(s)` : ''}</span><EtiquetasPieza fila={p} /></span><ChevronDown size={18} aria-hidden /></summary><p className="text-sm">{p.detalle}</p>{fotos.length > 0 && <GaleriaEvidenciasPedido compacto label="Fotos del defecto" previews={fotos} disabled onVer={(docs, indice) => setVista({ documentos: docs, indice })} />}</details></li>;
                    })}</ul>}
                </div> : !editable ? <p className="text-sm theme-text-muted">Toma la revisión para responder.</p> : <>
                    {!actualizacion && <EstadoPedidoConsulta estado={estado} onChange={setEstado} disabled={guardando} />}
                    {(estado === 'detalle' || actualizacion && excepciones.length > 0) && <CapturaExcepcionesConsulta
                        actualizacion={actualizacion} excepciones={excepciones} archivadas={archivadas} origenIncidencia={origenIncidencia}
                        incidenciasRef={incidenciasRef} manual={manual} disabled={guardando}
                        onAgregarDetalle={() => setCapturaRapida({ origen: origenIncidencia })}
                        onChange={cambiar} onQuitar={quitar} onVer={(docs, indice) => setVista({ documentos: docs, indice })}
                    />}

                </>}
                {(actualizacion || estado || respondida) && <section className="gelia-cedis-panel" aria-label="Captura del pedido">{capturaAnterior.length > 0 && !respondida && <div className="grid gap-2 mb-3"><p className="text-sm font-semibold theme-text-main m-0">Captura vigente del pedido</p><div className="flex flex-wrap gap-2">{capturaAnterior.map((doc, i) => <MiniaturaDocumento key={doc.id || i} documento={doc} onVer={() => setVista({ documentos: capturaAnterior, indice: i })} />)}</div><p className="text-xs theme-text-muted m-0">Se conserva. Adjunta una nueva solo si el pedido completo cambió.</p></div>}<GaleriaEvidenciasPedido compacto accionesCamara id="cedis-evidencias-disponibilidad" label={capturaAnterior.length > 0 && !respondida ? 'Nueva captura (opcional)' : 'Captura del pedido'} obligatorio={!respondida && !capturaAnterior.length} maxArchivos={8} archivos={archivos} previews={previews} disabled={guardando || respondida} onChange={(files, vistas) => { setArchivos(files); setPreviews(vistas); }} onVer={(docs, indice) => setVista({ documentos: docs, indice })} /></section>}
            </div>

        </div>
        <footer className="gelia-preparacion-footer">
            <div className="gelia-cedis-alerta min-w-0 text-sm" data-error={Boolean(error)} role={error ? 'alert' : 'status'}><AlertTriangle size={18} className="shrink-0" aria-hidden /><span>{error || (respondida ? 'Respuesta enviada a Ventas.' : 'Consulta de disponibilidad · sin confirmar apartado.')}</span></div>
            {editable && <button type="button" className={`${THEME_BTN_PRIMARY} shrink-0`} disabled={guardando} onClick={enviar}>{guardando ? 'Procesando…' : 'Enviar respuesta'}</button>}
        </footer>
        {capturaRapida && <ModalSeleccionProductosDetalle almacenId={almacenId} origen={capturaRapida.origen} onClose={() => setCapturaRapida(null)} onAgregar={agregarSeleccionDetalle} />}
        <ModalVistaPreviaDocumento abierto={Boolean(vista)} {...vista} onClose={() => setVista(null)} accionesDocumento={actualizacion && editable ? documento => {
            const cambio = cambios.find(c => c.pendiente && c.tipo !== 'retiro' && c.documentos.some(d => d.id === documento.id));
            if (!cambio) return null;
            return <div className="consulta-visor-acciones">
                <button type="button" className={GELIA_BTN_OUTLINE} data-respuesta="correcto" onClick={() => { responderCambio(cambio, 'correcto'); setVista(null); }}><CheckCircle2 size={16} aria-hidden />Todo OK</button>
                <button type="button" className={GELIA_BTN_OUTLINE} onClick={() => { reportarCambio(cambio, 'detalle'); setVista(null); }}><AlertTriangle size={16} aria-hidden />Registrar detalle</button>
            </div>;
        } : null} />
    </section>;
    if (modo !== 'dialogo') return contenido;
    return createPortal(<div className={`${THEME_MODAL_OVERLAY} p-0 md:p-4`}>
        <div {...dialogo} aria-label="Consulta de disponibilidad" className={`${THEME_MODAL_SHELL} gelia-preparacion-modal w-full max-w-7xl max-h-[100dvh] md:max-h-[94dvh] flex flex-col overflow-hidden`}>
            <header className="flex items-center justify-between gap-3 px-4 py-3 border-b theme-border shrink-0"><div className="min-w-0"><h2 className="text-base font-semibold theme-text-main m-0">Consulta de disponibilidad</h2>{(pedido.folio_remision || pedido.folio) && <p className="text-xs theme-text-muted m-0">Folio No. : {pedido.folio_remision || pedido.folio}</p>}</div><button type="button" className={THEME_BTN_SECONDARY} disabled={guardando} onClick={onClose} aria-label="Cerrar consulta"><X size={20} /></button></header>
            <div className="gelia-modal-body min-h-0 flex-1 overflow-y-auto">{contenido}</div>
        </div>
    </div>, document.body);
}
