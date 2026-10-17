import React, { useEffect, useRef, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { ArrowDown, Boxes, FileSpreadsheet, History, Paperclip, Plus, RotateCcw, Send, Sparkles, Trash2, UserRound, X } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import GeliaLogo from '@/Components/GeliaLogo';
import GeliaAiMarkdown from '@/Components/GeliaAi/GeliaAiMarkdown';
import PropuestaAccionCard from '@/Components/GeliaAi/PropuestaAccionCard';
import ResultadoAccionCard from '@/Components/GeliaAi/ResultadoAccionCard';
import { EstadoBadge, EstadoTrabajoGelia, GeliaAiAviso } from '@/Components/GeliaAi/GeliaAiEstado';
import {
    estadoTarjetaDesdeServidor,
    etiquetaArchivo,
    mensajeErrorHttp,
    textoPrepararDeNuevo,
} from '@/Components/GeliaAi/geliaAiEstados';
import { THEME_BTN_PRIMARY } from '@/utils/geliaTheme';

const MAX_FILES = 10;
const DRAWER_MS = 320;

const SIN_CONFIGURAR = 'GELIA aún no está configurada. Una persona administradora debe completar la conexión del servicio de IA en Configuración del sistema.';

let secuenciaMensaje = 0;
const conId = (m) => ({ id: `m${++secuenciaMensaje}`, ...m });
const saludo = () => [];

function mensajeErrorUpload(err) {
    const msg = mensajeErrorHttp(err, 'No se pudieron subir los archivos.');
    if (/csv|xlsx|xls|mimes|extensions/i.test(msg)) return 'Solo se permiten archivos CSV, XLSX o XLS.';
    return msg;
}

function enlaceImportaciones() {
    try {
        return route().has('almacenes.importaciones.index') ? route('almacenes.importaciones.index') : null;
    } catch {
        return null;
    }
}

/** Ubica la tarjeta pendiente en el mensaje que la anunció (su texto incluye el resumen). */
function restaurarPropuesta(mensajes, propuesta) {
    if (!propuesta) return mensajes;
    const idx = mensajes.findLastIndex(
        (m) => m.role === 'assistant' && propuesta.resumen_corto && m.content.includes(propuesta.resumen_corto),
    );
    const tarjeta = { propuesta, propuestaEstado: 'pendiente' };
    if (idx === -1) return [...mensajes, conId({ role: 'assistant', content: '', ...tarjeta })];
    return mensajes.map((m, i) => (i === idx ? { ...m, ...tarjeta } : m));
}

function ArchivoChip({ nombre, kind, onQuitar }) {
    return (
        <div className={`gelia-ai-file-card ${onQuitar ? '' : 'gelia-ai-file-card--static'}`}>
            <span className="gelia-ai-file-icon" aria-hidden>
                <FileSpreadsheet className="w-4 h-4" />
            </span>
            <span className="gelia-ai-file-meta">
                <span className="gelia-ai-file-name" title={nombre}>{nombre}</span>
                <span className="gelia-ai-file-sub">{etiquetaArchivo(kind)}</span>
            </span>
            {onQuitar && (
                <button type="button" className="gelia-ai-file-x" onClick={onQuitar} aria-label={`Quitar ${nombre}`}>
                    <X className="w-3 h-3" aria-hidden />
                </button>
            )}
        </div>
    );
}

export default function Index({
    auth,
    configurado = false,
    conversaciones: conversacionesIniciales = [],
    limiteSegundos = 90,
}) {
    const nombre = String(auth?.user?.name || '').replace(/[\p{C}\s]+/gu, ' ').trim().slice(0, 80);
    const [messages, setMessages] = useState(saludo);
    const [input, setInput] = useState('');
    const [trabajo, setTrabajo] = useState(null);
    const [destello, setDestello] = useState(false);
    const [error, setError] = useState('');
    const [conversacionId, setConversacionId] = useState(null);
    const [conversaciones, setConversaciones] = useState(conversacionesIniciales);
    const [drawerMounted, setDrawerMounted] = useState(false);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [adjuntos, setAdjuntos] = useState([]);
    const [archivosContexto, setArchivosContexto] = useState([]);
    const bottomRef = useRef(null);
    const seguirFinal = useRef(true);
    const [lejosDelFinal, setLejosDelFinal] = useState(false);
    const fileRef = useRef(null);
    const inputRef = useRef(null);
    const historialBtnRef = useRef(null);
    const drawerCloseRef = useRef(null);
    const enCurso = useRef(false);
    const readyTimer = useRef(null);
    const drawerTimer = useRef(null);

    const ocupado = trabajo !== null;

    useEffect(() => {
        if (seguirFinal.current) {
            bottomRef.current?.scrollIntoView({ behavior: 'instant', block: 'end' });
        }
    }, [messages, trabajo]);

    useEffect(() => {
        const campo = inputRef.current;
        if (!campo) return;
        campo.style.height = 'auto';
        campo.style.height = `${Math.min(campo.scrollHeight, 160)}px`;
    }, [input]);

    const irAlFinal = () => {
        seguirFinal.current = true;
        setLejosDelFinal(false);
        bottomRef.current?.scrollIntoView({ behavior: 'instant', block: 'end' });
    };

    const prepararConsulta = (texto) => {
        setInput(texto);
        inputRef.current?.focus();
    };

    useEffect(() => () => {
        clearTimeout(readyTimer.current);
        clearTimeout(drawerTimer.current);
    }, []);

    useEffect(() => {
        if (!drawerOpen) return undefined;
        drawerCloseRef.current?.focus();
        const onKey = (ev) => {
            if (ev.key === 'Escape') cerrarDrawer();
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [drawerOpen]);

    const iniciarTrabajo = (tipo) => {
        enCurso.current = true;
        setTrabajo({ tipo, desde: Date.now() });
    };

    const terminarTrabajo = (exito) => {
        enCurso.current = false;
        setTrabajo(null);
        if (exito) {
            setDestello(true);
            clearTimeout(readyTimer.current);
            readyTimer.current = setTimeout(() => setDestello(false), 550);
        }
    };

    const actualizarMensaje = (id, cambios) => {
        setMessages((prev) => prev.map((m) => (m.id === id ? { ...m, ...cambios } : m)));
    };

    const agregarMensaje = (m) => setMessages((prev) => [...prev, conId(m)]);

    const abrirDrawer = () => {
        clearTimeout(drawerTimer.current);
        setDrawerMounted(true);
        requestAnimationFrame(() => {
            requestAnimationFrame(() => setDrawerOpen(true));
        });
        refrescarLista();
    };

    const cerrarDrawer = () => {
        setDrawerOpen(false);
        clearTimeout(drawerTimer.current);
        drawerTimer.current = setTimeout(() => setDrawerMounted(false), DRAWER_MS);
        if (drawerMounted) historialBtnRef.current?.focus();
    };

    const reiniciarChat = () => {
        seguirFinal.current = true;
        setLejosDelFinal(false);
        setConversacionId(null);
        setMessages(saludo());
        setAdjuntos([]);
        setArchivosContexto([]);
        setError('');
    };

    const nuevoChat = () => {
        if (ocupado) return;
        reiniciarChat();
        cerrarDrawer();
    };

    const cargarConversacion = async (id) => {
        if (ocupado) return;
        try {
            const { data } = await axios.get(route('gelia_ai.conversaciones.show', id));
            const loaded = (data.messages || [])
                .filter((m) => m.role === 'user' || m.role === 'assistant')
                .map((m) => conId({ ...m, reveal: false }));
            setConversacionId(data.id);
            seguirFinal.current = true;
            setLejosDelFinal(false);
            setMessages(restaurarPropuesta(loaded.length ? loaded : saludo(), data.propuesta_pendiente));
            setArchivosContexto(Array.isArray(data.archivos) ? data.archivos : []);
            setAdjuntos([]);
            setError('');
            cerrarDrawer();
        } catch (err) {
            setError(mensajeErrorHttp(err, 'No se pudo abrir el chat. Vuelve a intentarlo.'));
        }
    };

    const eliminarConversacion = async (id) => {
        try {
            await axios.delete(route('gelia_ai.conversaciones.destroy', id));
            setConversaciones((prev) => prev.filter((c) => c.id !== id));
            if (conversacionId === id) reiniciarChat();
        } catch (err) {
            setError(mensajeErrorHttp(err, 'No se pudo eliminar el chat. Vuelve a intentarlo.'));
        }
    };

    const refrescarLista = async () => {
        try {
            const { data } = await axios.get(route('gelia_ai.conversaciones.index'));
            setConversaciones(data.data || []);
        } catch {
            /* la lista local sigue siendo válida */
        }
    };

    const onPickFiles = async (ev) => {
        const files = Array.from(ev.target.files || []);
        ev.target.value = '';
        if (!files.length || enCurso.current) return;
        if (adjuntos.length + files.length > MAX_FILES) {
            setError(`Puedes adjuntar hasta ${MAX_FILES} archivos por mensaje.`);
            return;
        }
        setError('');
        iniciarTrabajo('subida');
        try {
            const fd = new FormData();
            files.forEach((f) => fd.append('archivos[]', f));
            const { data } = await axios.post(route('gelia_ai.archivos.store'), fd);
            setAdjuntos((prev) => [...prev, ...(data.files || [])].slice(0, MAX_FILES));
            terminarTrabajo(false);
        } catch (err) {
            setError(mensajeErrorUpload(err));
            terminarTrabajo(false);
        }
    };

    const quitarAdjunto = (fileId) => {
        setAdjuntos((prev) => prev.filter((f) => f.file_id !== fileId));
    };

    const confirmarAccion = async (msg) => {
        if (enCurso.current || msg.propuestaEstado !== 'pendiente') return;
        iniciarTrabajo('accion');
        actualizarMensaje(msg.id, { propuestaEstado: 'ejecutando' });
        try {
            const { data } = await axios.post(route('gelia_ai.acciones.ejecutar'), {
                propuesta_id: msg.propuesta.id,
                conversacion_id: conversacionId || undefined,
                confirmado: true,
            });
            actualizarMensaje(msg.id, { propuestaEstado: 'ejecutada' });
            agregarMensaje({
                role: 'assistant',
                kind: 'resultado',
                content: data.mensaje || data.reporte?.resumen || 'Acción terminada.',
                reporte: data.reporte || {},
            });
            terminarTrabajo(true);
        } catch (err) {
            if (!err?.response) {
                // Sin respuesta no se sabe si el servidor llegó a ejecutarla: no se ofrece repetirla.
                actualizarMensaje(msg.id, {
                    propuestaEstado: 'procesando',
                    propuestaMensaje: 'No recibimos respuesta del servidor. La acción pudo haberse ejecutado: revisa el módulo correspondiente antes de preparar otra.',
                });
            } else {
                actualizarMensaje(msg.id, {
                    propuestaEstado: estadoTarjetaDesdeServidor(err.response.data?.estado_propuesta),
                    propuestaMensaje: mensajeErrorHttp(err, 'No se pudo ejecutar la acción.'),
                });
            }
            terminarTrabajo(false);
        }
    };

    const cancelarPropuesta = async (msg) => {
        if (enCurso.current || msg.propuestaEstado !== 'pendiente') return;
        iniciarTrabajo('cancelacion');
        actualizarMensaje(msg.id, { propuestaEstado: 'cancelando' });
        try {
            await axios.post(route('gelia_ai.acciones.cancelar'), {
                propuesta_id: msg.propuesta.id,
                conversacion_id: conversacionId || undefined,
            });
            actualizarMensaje(msg.id, { propuestaEstado: 'cancelada', propuestaMensaje: 'Cancelaste la propuesta; no se ejecutó nada.' });
        } catch (err) {
            const estado = err?.response?.data?.estado_propuesta;
            actualizarMensaje(msg.id, estado
                ? { propuestaEstado: estadoTarjetaDesdeServidor(estado), propuestaMensaje: mensajeErrorHttp(err, '') }
                : { propuestaEstado: 'pendiente' });
            if (!estado) setError(mensajeErrorHttp(err, 'No se pudo cancelar la propuesta. Vuelve a intentarlo.'));
        }
        terminarTrabajo(false);
    };

    const prepararDeNuevo = (msg) => {
        setInput(textoPrepararDeNuevo(msg.propuesta));
        inputRef.current?.focus();
    };

    const enviarTexto = async (messageText, reintentoDe = null) => {
        if (enCurso.current) return;
        if (!configurado) {
            setError(SIN_CONFIGURAR);
            return;
        }

        setError('');
        seguirFinal.current = true;
        setLejosDelFinal(false);
        const fileIds = adjuntos.map((f) => f.file_id);
        if (reintentoDe) {
            setMessages((prev) => prev.filter((m) => m.id !== reintentoDe));
        } else {
            setInput('');
            agregarMensaje({
                role: 'user',
                content: messageText,
                files: adjuntos.map((f) => ({ name: f.original_name, kind: f.kind })),
            });
        }
        iniciarTrabajo('consulta');

        try {
            // El historial y los archivos ya adjuntos se recuperan en el servidor por conversacion_id.
            const { data } = await axios.post(route('gelia_ai.chat'), {
                message: messageText,
                conversacion_id: conversacionId || undefined,
                file_ids: fileIds.length ? fileIds : undefined,
            });
            setAdjuntos([]);
            if (Array.isArray(data.archivos)) setArchivosContexto(data.archivos);
            agregarMensaje({
                role: 'assistant',
                content: data.reply || 'GELIA no devolvió texto en esta respuesta.',
                reveal: true,
                kind: data.incompleta ? 'incompleta' : undefined,
                faltantes: Array.isArray(data.faltantes) ? data.faltantes : [],
                propuesta: data.propuesta_accion || null,
                propuestaEstado: data.propuesta_accion ? 'pendiente' : undefined,
            });
            if (data.conversacion_id) {
                setConversacionId(data.conversacion_id);
                setConversaciones((prev) => [
                    {
                        id: data.conversacion_id,
                        titulo: data.titulo || messageText.slice(0, 80),
                        temporal: false,
                        updated_at: new Date().toISOString(),
                    },
                    ...prev.filter((c) => c.id !== data.conversacion_id),
                ]);
            }
            terminarTrabajo(true);
        } catch (err) {
            // Los adjuntos se conservan en el compositor para volver a enviar sin subirlos otra vez.
            agregarMensaje({
                role: 'assistant',
                kind: 'error',
                content: mensajeErrorHttp(err, 'No se pudo contactar a GELIA.'),
                reintentar: messageText,
            });
            terminarTrabajo(false);
        }
    };

    const enviar = (e) => {
        e?.preventDefault?.();
        const text = input.trim();
        if (!text && adjuntos.length === 0) return;
        enviarTexto(text || `Tengo ${adjuntos.length} archivo(s) adjunto(s). ¿Qué acción operativa propones?`);
    };

    const onKeyDown = (ev) => {
        if (ev.key === 'Enter' && !ev.shiftKey && !ev.nativeEvent.isComposing) {
            ev.preventDefault();
            enviar();
        }
    };

    const importaciones = enlaceImportaciones();
    const archivosVisibles = archivosContexto.filter((a) => !adjuntos.some((f) => f.file_id === a.file_id));

    const renderAsistente = (m) => {
        if (m.kind === 'error') {
            return (
                <GeliaAiAviso
                    tono="error"
                    titulo="No se pudo completar la consulta"
                    accion={m.reintentar && (
                        <button
                            type="button"
                            className="gelia-ai-btn mt-2"
                            disabled={ocupado}
                            onClick={() => enviarTexto(m.reintentar, m.id)}
                        >
                            <RotateCcw className="w-3.5 h-3.5" aria-hidden />
                            Volver a enviar
                        </button>
                    )}
                >
                    <p className="gelia-ai-aviso-text">{m.content}</p>
                </GeliaAiAviso>
            );
        }
        if (m.kind === 'resultado') {
            return <ResultadoAccionCard reporte={m.reporte} enlaceImportaciones={importaciones} />;
        }
        if (m.propuesta) {
            return (
                <PropuestaAccionCard
                    propuesta={m.propuesta}
                    estado={m.propuestaEstado}
                    mensaje={m.propuestaMensaje}
                    bloqueado={ocupado}
                    onConfirmar={() => confirmarAccion(m)}
                    onCancelar={() => cancelarPropuesta(m)}
                    onPrepararDeNuevo={() => prepararDeNuevo(m)}
                />
            );
        }
        return (
            <>
                {m.kind === 'incompleta' && (
                    <div className="mb-1.5">
                        <EstadoBadge etiqueta="Respuesta incompleta" tono="aviso" icono="aviso" />
                    </div>
                )}
                <GeliaAiMarkdown text={m.content} reveal={Boolean(m.reveal)} />
                {m.faltantes?.length > 0 && (
                    <GeliaAiAviso tono="aviso" titulo="Falta información para preparar la acción">
                        <ul className="gelia-ai-aviso-list">
                            {m.faltantes.map((f) => <li key={f}>{f}</li>)}
                        </ul>
                        {archivosContexto.length > 0 && (
                            <p className="gelia-ai-aviso-text">
                                Los archivos de este chat siguen disponibles; no necesitas subirlos de nuevo.
                            </p>
                        )}
                    </GeliaAiAviso>
                )}
            </>
        );
    };

    return (
        <AppLayout user={auth.user} fullScreen>
            <Head title="GELIA" />

            <div className="gelia-ai-page" data-empty={messages.length === 0} data-state={ocupado ? 'thinking' : destello ? 'ready' : 'idle'}>
                <div className="gelia-ai-glow" aria-hidden />

                <div className="gelia-ai-toolbar">
                    <div className="flex items-center gap-2">
                        <button
                            ref={historialBtnRef}
                            type="button"
                            className="gelia-ai-icon-btn"
                            onClick={abrirDrawer}
                            aria-label="Historial de chats"
                            title="Historial de chats"
                        >
                            <History className="w-4 h-4" aria-hidden />
                        </button>
                        <button
                            type="button"
                            className="gelia-ai-icon-btn"
                            onClick={nuevoChat}
                            disabled={ocupado}
                            aria-label="Nuevo chat"
                            title="Nuevo chat"
                        >
                            <Plus className="w-4 h-4" aria-hidden />
                        </button>
                    </div>
                    {nombre && <span className="gelia-ai-session"><UserRound className="w-3.5 h-3.5" aria-hidden /><span>{nombre}</span></span>}
                </div>

                <header className="gelia-ai-top">
                    <div className="gelia-ai-logo-wrap">
                        <GeliaLogo
                            variant="sparkle"
                            className="w-8 h-8"
                        />
                    </div>
                    <div className="gelia-ai-brand">
                        <h1 className="gelia-ai-title">GEL<span className="gelia-ai-title-ia">IA</span></h1>
                        <p>Tu asistente en Gelia-NV</p>
                    </div>
                </header>

                {!configurado && <p className="gelia-ai-warn" role="status">{SIN_CONFIGURAR}</p>}

                <div className="gelia-ai-messages" onScroll={(ev) => {
                    const { scrollHeight, scrollTop, clientHeight } = ev.currentTarget;
                    const lejos = scrollHeight - scrollTop - clientHeight > 96;
                    seguirFinal.current = !lejos;
                    setLejosDelFinal(lejos);
                }}>
                    {messages.length === 0 && (
                        <section className="gelia-ai-welcome" aria-label="Bienvenida">
                            <div className="gelia-ai-welcome-mark" aria-hidden><GeliaLogo variant="sparkle" className="w-14 h-14" /></div>
                            <h2>{nombre ? `Hola, ${nombre}.` : 'Hola, soy GELIA.'}<br />¿Qué resolvemos hoy?</h2>
                            <p>Consulta tu inventario, prepara un listado o trabaja con tus archivos. Vamos paso a paso.</p>
                            <div className="gelia-ai-starters">
                                <button type="button" disabled={ocupado} onClick={() => prepararConsulta('Quiero consultar el inventario de ')}><Boxes aria-hidden /><span>Consultar inventario<small>Busca un producto o SKU</small></span></button>
                                <button type="button" disabled={ocupado} onClick={() => prepararConsulta('Quiero generar un listado. ¿Qué archivos necesito?')}><FileSpreadsheet aria-hidden /><span>Preparar un listado<small>Revisa qué archivos necesitas</small></span></button>
                                <button type="button" disabled={ocupado} onClick={() => fileRef.current?.click()}><Paperclip aria-hidden /><span>Adjuntar archivos<small>Trabaja con CSV o Excel</small></span></button>
                            </div>
                        </section>
                    )}
                    <div className="gelia-ai-thread" role="log" aria-live="polite" aria-label="Conversación con GELIA">
                        {messages.map((m) => (
                            m.role === 'user' ? (
                                <div key={m.id} className="gelia-ai-msg gelia-ai-msg--user">
                                    <div className="gelia-ai-msg-author">{nombre || 'Tú'}</div>
                                    <div className="gelia-ai-user-text">{m.content}</div>
                                    {Array.isArray(m.files) && m.files.length > 0 && (
                                        <div className="gelia-ai-msg-files">
                                            {m.files.map((f) => <ArchivoChip key={f.name} nombre={f.name} kind={f.kind} />)}
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <div key={m.id} className="gelia-ai-msg gelia-ai-msg--assistant">
                                    <div className="gelia-ai-msg-author"><Sparkles className="w-3.5 h-3.5" aria-hidden />GELIA</div>
                                    {renderAsistente(m)}
                                </div>
                            )
                        ))}
                    </div>
                    <div className="gelia-ai-thread-status"><EstadoTrabajoGelia trabajo={trabajo} limiteSegundos={limiteSegundos} /></div>
                    <div ref={bottomRef} />
                </div>

                {error && (
                    <div className="gelia-ai-error" role="alert">
                        <span>{error}</span>
                        <button type="button" className="gelia-ai-error-x" onClick={() => setError('')} aria-label="Cerrar aviso">
                            <X className="w-3.5 h-3.5" aria-hidden />
                        </button>
                    </div>
                )}

                <div className="gelia-ai-composer-wrap">
                    {lejosDelFinal && <button type="button" className="gelia-ai-jump gelia-ai-btn" onClick={irAlFinal}><ArrowDown className="w-3.5 h-3.5" aria-hidden />Ir al último mensaje</button>}
                    <form
                        className="gelia-ai-composer"
                        data-plain={adjuntos.length === 0 && archivosVisibles.length === 0 ? 'true' : 'false'}
                        onSubmit={enviar}
                    >
                        <input
                            ref={fileRef}
                            type="file"
                            className="hidden"
                            accept=".csv,.xlsx,.xls,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                            multiple
                            onChange={onPickFiles}
                            tabIndex={-1}
                            aria-hidden
                        />
                        {archivosVisibles.length > 0 && (
                            <div className="gelia-ai-context-files">
                                <p className="gelia-ai-context-label">Disponibles en este chat</p>
                                <div className="gelia-ai-attach-row">
                                    {archivosVisibles.map((f) => (
                                        <ArchivoChip key={f.file_id} nombre={f.original_name} kind={f.kind} />
                                    ))}
                                </div>
                            </div>
                        )}
                        {adjuntos.length > 0 && (
                            <div className="gelia-ai-attach-row">
                                {adjuntos.map((f) => (
                                    <ArchivoChip
                                        key={f.file_id}
                                        nombre={f.original_name}
                                        kind={f.kind}
                                        onQuitar={() => quitarAdjunto(f.file_id)}
                                    />
                                ))}
                            </div>
                        )}
                        <div className="gelia-ai-composer-row">
                            <button
                                type="button"
                                className="gelia-ai-attach"
                                disabled={ocupado || adjuntos.length >= MAX_FILES}
                                onClick={() => fileRef.current?.click()}
                                aria-label="Adjuntar archivos CSV o Excel"
                                title={`Adjuntar CSV o Excel (hasta ${MAX_FILES})`}
                            >
                                <Paperclip className="w-4 h-4" aria-hidden />
                            </button>
                            <textarea
                                ref={inputRef}
                                name="mensaje"
                                value={input}
                                onChange={(ev) => setInput(ev.target.value)}
                                onKeyDown={onKeyDown}
                                placeholder="Pregunta a GELIA…"
                                rows={1}
                                maxLength={2000}
                                autoComplete="off"
                                aria-label="Mensaje para GELIA"
                                aria-describedby="gelia-ai-composer-help"
                            />
                            <button
                                type="submit"
                                className="gelia-ai-send"
                                disabled={ocupado || (!input.trim() && adjuntos.length === 0)}
                                aria-label="Enviar mensaje"
                            >
                                <Send className="w-4 h-4" aria-hidden />
                            </button>
                        </div>
                    </form>
                    <p id="gelia-ai-composer-help" className="gelia-ai-composer-help"><span>Enter para enviar · Mayús + Enter para otra línea</span><span>Verifica los datos importantes.</span></p>
                </div>

                {drawerMounted && (
                    <div
                        className="gelia-ai-drawer"
                        data-open={drawerOpen ? 'true' : 'false'}
                        role="dialog"
                        aria-modal="true"
                        aria-label="Historial de chats"
                    >
                        <button
                            type="button"
                            className="gelia-ai-drawer-backdrop"
                            aria-label="Cerrar historial"
                            tabIndex={-1}
                            onClick={cerrarDrawer}
                        />
                        <aside className="gelia-ai-drawer-panel">
                            <div className="flex items-center justify-between gap-2">
                                <h2 className="m-0 text-sm font-black theme-text-main">Chats</h2>
                                <button
                                    ref={drawerCloseRef}
                                    type="button"
                                    className="gelia-ai-icon-btn"
                                    onClick={cerrarDrawer}
                                    aria-label="Cerrar historial"
                                >
                                    <X className="w-4 h-4" aria-hidden />
                                </button>
                            </div>
                            <button
                                type="button"
                                className={`${THEME_BTN_PRIMARY} w-full !py-2.5 text-[11px] inline-flex items-center justify-center gap-2`}
                                onClick={nuevoChat}
                                disabled={ocupado}
                            >
                                <Plus className="w-4 h-4" aria-hidden />
                                Nuevo chat
                            </button>
                            <div className="gelia-ai-drawer-list custom-scrollbar">
                                {conversaciones.length === 0 && (
                                    <p className="text-xs theme-text-muted m-0 px-1">
                                        Aún no hay chats guardados. Se guardan al recibir la primera respuesta.
                                    </p>
                                )}
                                {conversaciones.map((c, idx) => (
                                    <div
                                        key={c.id}
                                        className="gelia-ai-hist-item"
                                        data-active={conversacionId === c.id}
                                        style={{ animationDelay: `${Math.min(idx * 35, 280)}ms` }}
                                    >
                                        <button
                                            type="button"
                                            className="gelia-ai-hist-title"
                                            onClick={() => cargarConversacion(c.id)}
                                            aria-current={conversacionId === c.id ? 'true' : undefined}
                                            disabled={ocupado}
                                        >
                                            {c.titulo}
                                        </button>
                                        <button
                                            type="button"
                                            className="gelia-ai-icon-btn !w-8 !h-8"
                                            onClick={() => eliminarConversacion(c.id)}
                                            aria-label={`Eliminar chat «${c.titulo}»`}
                                        >
                                            <Trash2 className="w-3.5 h-3.5" aria-hidden />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        </aside>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
