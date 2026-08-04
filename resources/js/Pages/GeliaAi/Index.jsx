import React, { useEffect, useRef, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { Download, FileSpreadsheet, History, Paperclip, Plus, Send, Trash2, X } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import GeliaLogo from '@/Components/GeliaLogo';
import GeliaAiMarkdown from '@/Components/GeliaAi/GeliaAiMarkdown';
import { THEME_BTN_PRIMARY } from '@/utils/geliaTheme';

const MAX_TURNS = 4;
const MAX_FILES = 10;
const DRAWER_MS = 320;

const GREETING =
    'Hola, soy GELIA. Puedo explicarte listados y solicitudes, consultar inventario, y con archivos proponer importaciones o listados. ¿En qué te ayudo?';

function isGreeting(content) {
    return typeof content === 'string' && (
        content === GREETING
        || content.startsWith('Hola, soy GELIA')
        || content.startsWith('Hola, soy Gel-IA')
        || content.startsWith('Hola, soy GEL-IA')
    );
}

function etiquetaArchivo(kind, name = '') {
    const ext = (name.split('.').pop() || '').toLowerCase();
    if (ext === 'csv' || ext === 'xlsx' || ext === 'xls') {
        return 'Hoja de cálculo';
    }
    const labels = {
        costos: 'Costos',
        existencias: 'Existencias',
        precios: 'Precios',
        desconocido: 'Hoja de cálculo',
    };
    return labels[kind] || 'Hoja de cálculo';
}

function mensajeErrorUpload(err) {
    const errors = err?.response?.data?.errors;
    if (errors && typeof errors === 'object') {
        const first = Object.values(errors).flat().find(Boolean);
        if (typeof first === 'string') {
            if (/csv|xlsx|xls|mimes|type/i.test(first)) {
                return 'Solo se permiten archivos CSV, XLSX o XLS.';
            }
            if (/max/i.test(first) && /10|archivos/i.test(first)) {
                return `Máximo ${MAX_FILES} archivos.`;
            }
            return first;
        }
    }
    return err?.response?.data?.message || 'No se pudieron subir los archivos.';
}

function formatearReporte(data) {
    const r = data?.reporte || {};
    const lines = [
        `**${r.resumen || 'Acción completada'}**`,
        '',
        `- Acción: \`${data.accion || '—'}\``,
    ];
    if (r.log_id) {
        lines.push(`- Log importación: \`${r.log_id}\` (seguimiento en Almacenes)`);
    }
    if (r.conteos) {
        lines.push(`- Conteos: ok ${r.conteos.ok ?? 0}, error ${r.conteos.error ?? 0}`);
    }
    const detalles = Array.isArray(r.detalles) ? r.detalles.slice(0, 8) : [];
    if (detalles.length) {
        lines.push('', 'Errores / avisos:');
        detalles.forEach((d) => {
            lines.push(`- ${d.sku || '—'}: ${d.error || JSON.stringify(d)}`);
        });
        if ((r.detalles?.length || 0) > 8) {
            lines.push(`- …y ${(r.detalles.length - 8)} más`);
        }
    }
    return lines.join('\n');
}

export default function Index({
    auth,
    configurado = false,
    conversaciones: conversacionesIniciales = [],
}) {
    const [messages, setMessages] = useState([{ role: 'assistant', content: GREETING, reveal: false }]);
    const [input, setInput] = useState('');
    const [state, setState] = useState('idle');
    const [error, setError] = useState('');
    const [conversacionId, setConversacionId] = useState(null);
    const [conversaciones, setConversaciones] = useState(conversacionesIniciales);
    const [drawerMounted, setDrawerMounted] = useState(false);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [adjuntos, setAdjuntos] = useState([]);
    const [subiendo, setSubiendo] = useState(false);
    const bottomRef = useRef(null);
    const fileRef = useRef(null);
    const readyTimer = useRef(null);
    const drawerTimer = useRef(null);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }, [messages, state]);

    useEffect(() => () => {
        clearTimeout(readyTimer.current);
        clearTimeout(drawerTimer.current);
    }, []);

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
    };

    const nuevoChat = () => {
        setConversacionId(null);
        setMessages([{ role: 'assistant', content: GREETING, reveal: false }]);
        setAdjuntos([]);
        setError('');
        cerrarDrawer();
    };

    const cargarConversacion = async (id) => {
        try {
            const { data } = await axios.get(route('gelia_ai.conversaciones.show', id));
            const loaded = (data.messages || [])
                .filter((m) => m.role === 'user' || m.role === 'assistant')
                .map((m) => ({ ...m, reveal: false }));
            setConversacionId(data.id);
            setMessages(loaded.length ? loaded : [{ role: 'assistant', content: GREETING, reveal: false }]);
            setAdjuntos([]);
            setError('');
            cerrarDrawer();
        } catch {
            setError('No se pudo cargar el chat.');
        }
    };

    const eliminarConversacion = async (id, e) => {
        e?.stopPropagation?.();
        try {
            await axios.delete(route('gelia_ai.conversaciones.destroy', id));
            setConversaciones((prev) => prev.filter((c) => c.id !== id));
            if (conversacionId === id) {
                setConversacionId(null);
                setMessages([{ role: 'assistant', content: GREETING, reveal: false }]);
                setAdjuntos([]);
                setError('');
            }
        } catch {
            setError('No se pudo eliminar el chat.');
        }
    };

    const refrescarLista = async () => {
        try {
            const { data } = await axios.get(route('gelia_ai.conversaciones.index'));
            setConversaciones(data.data || []);
        } catch {
            /* ignore */
        }
    };

    const onPickFiles = async (ev) => {
        const files = Array.from(ev.target.files || []);
        ev.target.value = '';
        if (!files.length) return;
        if (adjuntos.length + files.length > MAX_FILES) {
            setError(`Máximo ${MAX_FILES} archivos.`);
            return;
        }
        setSubiendo(true);
        setError('');
        try {
            const fd = new FormData();
            files.forEach((f) => fd.append('archivos[]', f));
            const { data } = await axios.post(route('gelia_ai.archivos.store'), fd);
            setAdjuntos((prev) => [...prev, ...(data.files || [])].slice(0, MAX_FILES));
        } catch (err) {
            setError(mensajeErrorUpload(err));
        } finally {
            setSubiendo(false);
        }
    };

    const quitarAdjunto = (fileId) => {
        setAdjuntos((prev) => prev.filter((f) => f.file_id !== fileId));
    };

    const confirmarAccion = async (msgIndex, propuesta) => {
        if (!propuesta?.accion || state === 'thinking') return;
        setState('thinking');
        setError('');
        try {
            const { data } = await axios.post(route('gelia_ai.acciones.ejecutar'), {
                accion: propuesta.accion,
                payload: propuesta.payload || {},
                confirmado: true,
            });
            setMessages((prev) => {
                const next = [...prev];
                if (next[msgIndex]) {
                    next[msgIndex] = { ...next[msgIndex], propuesta: null, propuesta_done: true };
                }
                next.push({
                    role: 'assistant',
                    content: formatearReporte(data),
                    reveal: true,
                    reporte: data.reporte || null,
                });
                return next;
            });
            setState('ready');
            clearTimeout(readyTimer.current);
            readyTimer.current = setTimeout(() => setState('idle'), 550);
        } catch (err) {
            const msg = err?.response?.data?.message || 'No se pudo ejecutar.';
            setError(msg);
            setMessages((prev) => [
                ...prev,
                { role: 'assistant', content: `No pude ejecutar: ${msg}`, reveal: true },
            ]);
            setState('idle');
        }
    };

    const cancelarPropuesta = (msgIndex) => {
        setMessages((prev) => {
            const next = [...prev];
            if (next[msgIndex]) {
                next[msgIndex] = { ...next[msgIndex], propuesta: null };
            }
            return next;
        });
    };

    const enviar = async (e) => {
        e?.preventDefault?.();
        const text = input.trim();
        if ((!text && adjuntos.length === 0) || state === 'thinking') return;
        if (!configurado) {
            setError('DeepSeek no está configurado. Un admin debe cargar api_token y base_url en Configuración del sistema.');
            return;
        }

        const messageText = text || (adjuntos.length
            ? `Tengo ${adjuntos.length} archivo(s) adjunto(s). ¿Qué acción operativa propones?`
            : '');
        if (!messageText) return;

        setError('');
        setInput('');
        const fileIds = adjuntos.map((f) => f.file_id);
        const nextMessages = [
            ...messages,
            {
                role: 'user',
                content: messageText,
                reveal: false,
                files: adjuntos.map((f) => ({ name: f.original_name, kind: f.kind })),
            },
        ];
        setMessages(nextMessages);
        setState('thinking');

        const history = nextMessages
            .filter((m) => m.role === 'user' || m.role === 'assistant')
            .filter((m) => !isGreeting(m.content))
            .slice(0, -1)
            .slice(-MAX_TURNS)
            .map((m) => ({
                role: m.role,
                content: m.role === 'assistant' && m.content.length > 400
                    ? `${m.content.slice(0, 400)}…`
                    : m.content,
            }));

        try {
            const { data } = await axios.post(route('gelia_ai.chat'), {
                message: messageText,
                messages: history,
                conversacion_id: conversacionId || undefined,
                file_ids: fileIds.length ? fileIds : undefined,
            });
            setMessages((prev) => [
                ...prev,
                {
                    role: 'assistant',
                    content: data.reply || 'Sin respuesta.',
                    reveal: true,
                    propuesta: data.propuesta_accion || null,
                },
            ]);
            if (data.conversacion_id) {
                setConversacionId(data.conversacion_id);
                setConversaciones((prev) => {
                    const rest = prev.filter((c) => c.id !== data.conversacion_id);
                    return [
                        {
                            id: data.conversacion_id,
                            titulo: data.titulo || messageText.slice(0, 80),
                            temporal: false,
                            updated_at: new Date().toISOString(),
                        },
                        ...rest,
                    ];
                });
            }
            setState('ready');
            clearTimeout(readyTimer.current);
            readyTimer.current = setTimeout(() => setState('idle'), 550);
        } catch (err) {
            const msg = err?.response?.data?.message || 'No se pudo contactar a GELIA.';
            setError(msg);
            setMessages((prev) => [
                ...prev,
                { role: 'assistant', content: `No pude completar la consulta: ${msg}`, reveal: true },
            ]);
            setState('idle');
        }
    };

    const onKeyDown = (ev) => {
        if (ev.key === 'Enter' && !ev.shiftKey) {
            ev.preventDefault();
            enviar();
        }
    };

    return (
        <AppLayout user={auth.user} fullScreen>
            <Head title="GELIA" />

            <div className="gelia-ai-page" data-state={state}>
                <div className="gelia-ai-glow" aria-hidden />

                <div className="gelia-ai-toolbar">
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            className="gelia-ai-icon-btn"
                            onClick={abrirDrawer}
                            aria-label="Historial de chats"
                        >
                            <History className="w-4 h-4" />
                        </button>
                        <button
                            type="button"
                            className="gelia-ai-icon-btn"
                            onClick={nuevoChat}
                            aria-label="Nuevo chat temporal"
                            title="Nuevo chat"
                        >
                            <Plus className="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <header className="gelia-ai-top">
                    <div className="gelia-ai-logo-wrap">
                        <GeliaLogo
                            variant="sparkle"
                            className="w-16 h-16 md:w-[4.5rem] md:h-[4.5rem]"
                        />
                    </div>
                    <h1 className="gelia-ai-title">
                        GEL<span className="gelia-ai-title-ia">IA</span>
                    </h1>
                </header>

                {!configurado && (
                    <p className="gelia-ai-warn">
                        Falta configurar DeepSeek en Configuración del sistema (`deepseek.api_token` y `deepseek.base_url`).
                    </p>
                )}

                <div className="gelia-ai-messages custom-scrollbar" role="log" aria-live="polite">
                    {messages.map((m, i) => (
                        m.role === 'user' ? (
                            <div key={`${m.role}-${i}`} className="gelia-ai-msg gelia-ai-msg--user">
                                {m.content}
                                {Array.isArray(m.files) && m.files.length > 0 && (
                                    <div className="gelia-ai-msg-files">
                                        {m.files.map((f) => (
                                            <div key={f.name} className="gelia-ai-file-card gelia-ai-file-card--static">
                                                <span className="gelia-ai-file-icon" aria-hidden>
                                                    <FileSpreadsheet className="w-4 h-4" />
                                                </span>
                                                <span className="gelia-ai-file-meta">
                                                    <span className="gelia-ai-file-name">{f.name}</span>
                                                    <span className="gelia-ai-file-sub">
                                                        {etiquetaArchivo(f.kind, f.name)}
                                                        {f.kind ? ` · ${f.kind}` : ''}
                                                    </span>
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        ) : (
                            <div key={`${m.role}-${i}`} className="gelia-ai-msg gelia-ai-msg--assistant">
                                <GeliaAiMarkdown text={m.content} reveal={Boolean(m.reveal)} />
                                {m.propuesta && !m.propuesta_done && (
                                    <div className="gelia-ai-confirm">
                                        <p className="gelia-ai-confirm-text">
                                            {m.propuesta.resumen_corto || `Confirmar ${m.propuesta.accion}`}
                                        </p>
                                        {!m.propuesta.puede && (
                                            <p className="gelia-ai-confirm-warn">
                                                Sin permiso ({m.propuesta.permiso}).
                                            </p>
                                        )}
                                        <div className="gelia-ai-confirm-actions">
                                            <button
                                                type="button"
                                                className="gelia-ai-confirm-btn gelia-ai-confirm-btn--ok"
                                                disabled={!m.propuesta.puede || state === 'thinking'}
                                                onClick={() => confirmarAccion(i, m.propuesta)}
                                            >
                                                Confirmar
                                            </button>
                                            <button
                                                type="button"
                                                className="gelia-ai-confirm-btn"
                                                disabled={state === 'thinking'}
                                                onClick={() => cancelarPropuesta(i)}
                                            >
                                                Cancelar
                                            </button>
                                        </div>
                                    </div>
                                )}
                                {m.reporte?.download_url && (
                                    <a
                                        href={m.reporte.download_url}
                                        className="gelia-ai-download"
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <Download className="w-3.5 h-3.5" />
                                        Descargar listado
                                    </a>
                                )}
                            </div>
                        )
                    ))}
                    {state === 'thinking' && (
                        <div className="gelia-ai-msg gelia-ai-msg--thinking">Pensando…</div>
                    )}
                    <div ref={bottomRef} />
                </div>

                {error && <p className="gelia-ai-error">{error}</p>}

                <div className="gelia-ai-composer-wrap">
                    <form
                        className="gelia-ai-composer"
                        data-plain={adjuntos.length === 0 ? 'true' : 'false'}
                        onSubmit={enviar}
                    >
                        <input
                            ref={fileRef}
                            type="file"
                            className="hidden"
                            accept=".csv,.xlsx,.xls,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                            multiple
                            onChange={onPickFiles}
                        />
                        {adjuntos.length > 0 && (
                            <div className="gelia-ai-attach-row">
                                {adjuntos.map((f) => (
                                    <div key={f.file_id} className="gelia-ai-file-card">
                                        <span className="gelia-ai-file-icon" aria-hidden>
                                            <FileSpreadsheet className="w-4 h-4" />
                                        </span>
                                        <span className="gelia-ai-file-meta">
                                            <span className="gelia-ai-file-name" title={f.original_name}>
                                                {f.original_name}
                                            </span>
                                            <span className="gelia-ai-file-sub">
                                                {etiquetaArchivo(f.kind, f.original_name)}
                                                {f.kind ? ` · ${f.kind}` : ''}
                                            </span>
                                        </span>
                                        <button
                                            type="button"
                                            className="gelia-ai-file-x"
                                            onClick={() => quitarAdjunto(f.file_id)}
                                            aria-label="Quitar archivo"
                                        >
                                            <X className="w-3 h-3" />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        )}
                        <div className="gelia-ai-composer-row">
                            <button
                                type="button"
                                className="gelia-ai-attach"
                                disabled={subiendo || state === 'thinking' || adjuntos.length >= MAX_FILES}
                                onClick={() => fileRef.current?.click()}
                                aria-label="Adjuntar archivos"
                                title="Adjuntar CSV/XLSX (máx. 10)"
                            >
                                <Paperclip className="w-4 h-4" />
                            </button>
                            <textarea
                                value={input}
                                onChange={(ev) => setInput(ev.target.value)}
                                onKeyDown={onKeyDown}
                                placeholder="Pregunta a GELIA…"
                                rows={1}
                                disabled={state === 'thinking'}
                                maxLength={2000}
                                aria-label="Mensaje"
                            />
                            <button
                                type="submit"
                                className="gelia-ai-send"
                                disabled={state === 'thinking' || (!input.trim() && adjuntos.length === 0)}
                                aria-label="Enviar"
                            >
                                <Send className="w-4 h-4" />
                            </button>
                        </div>
                    </form>
                </div>

                {drawerMounted && (
                    <div
                        className="gelia-ai-drawer"
                        data-open={drawerOpen ? 'true' : 'false'}
                        role="dialog"
                        aria-label="Historial"
                    >
                        <button
                            type="button"
                            className="gelia-ai-drawer-backdrop"
                            aria-label="Cerrar historial"
                            onClick={cerrarDrawer}
                        />
                        <aside className="gelia-ai-drawer-panel">
                            <div className="flex items-center justify-between gap-2">
                                <p className="m-0 text-xs font-black uppercase tracking-widest theme-text-muted">
                                    Chats
                                </p>
                                <button
                                    type="button"
                                    className="gelia-ai-icon-btn"
                                    onClick={cerrarDrawer}
                                    aria-label="Cerrar"
                                >
                                    <X className="w-4 h-4" />
                                </button>
                            </div>
                            <button
                                type="button"
                                className={`${THEME_BTN_PRIMARY} w-full !py-2.5 text-[11px] inline-flex items-center justify-center gap-2`}
                                onClick={nuevoChat}
                            >
                                <Plus className="w-4 h-4" />
                                Chat temporal nuevo
                            </button>
                            <div className="gelia-ai-drawer-list custom-scrollbar">
                                {conversaciones.length === 0 && (
                                    <p className="text-xs theme-text-muted italic m-0 px-1">
                                        Aún no hay chats guardados.
                                    </p>
                                )}
                                {conversaciones.map((c, idx) => (
                                    <div
                                        key={c.id}
                                        className="gelia-ai-hist-item"
                                        data-active={conversacionId === c.id}
                                        style={{ animationDelay: `${Math.min(idx * 35, 280)}ms` }}
                                        role="button"
                                        tabIndex={0}
                                        onClick={() => cargarConversacion(c.id)}
                                        onKeyDown={(ev) => {
                                            if (ev.key === 'Enter') cargarConversacion(c.id);
                                        }}
                                    >
                                        <span className="gelia-ai-hist-title">{c.titulo}</span>
                                        <button
                                            type="button"
                                            className="gelia-ai-icon-btn !w-8 !h-8"
                                            onClick={(ev) => eliminarConversacion(c.id, ev)}
                                            aria-label="Eliminar chat"
                                        >
                                            <Trash2 className="w-3.5 h-3.5" />
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
