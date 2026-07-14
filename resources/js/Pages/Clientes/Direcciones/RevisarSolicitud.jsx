import React, { useEffect, useRef, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import axios from 'axios';
import { ArrowLeft, FileText, Search } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass, THEME_INPUT, THEME_SELECT, THEME_TEXTAREA } from '../../../utils/geliaTheme';
import {
    labelAccionSolicitud,
    labelEstadoSolicitud,
} from '../../ControlPedidos/Partials/DireccionPedidoResumen';

const CAMPOS = [
    ['nombre_destinatario', 'Destinatario'],
    ['telefono_destinatario', 'Teléfono'],
    ['calle', 'Calle'],
    ['numero_exterior', 'Exterior'],
    ['numero_interior', 'Interior'],
    ['colonia', 'Colonia'],
    ['codigo_postal', 'CP'],
    ['municipio', 'Municipio'],
    ['ciudad', 'Ciudad'],
    ['estado', 'Estado'],
    ['pais', 'País'],
    ['referencias', 'Referencias'],
    ['indicaciones_entrega', 'Indicaciones'],
];

export default function RevisarSolicitud({
    auth,
    solicitud,
    comparacion,
    rutaBase = 'admin.clientes.direcciones.solicitudes',
}) {
    const form = useForm({
        notas: '',
        cliente_id: solicitud.cliente_coincidente_id || '',
    });
    const [accion, setAccion] = useState('aprobar');
    const [clienteQ, setClienteQ] = useState('');
    const [clientes, setClientes] = useState([]);
    const [clienteSel, setClienteSel] = useState(
        solicitud.cliente_coincidente
            ? { id: solicitud.cliente_coincidente.id, label: `${solicitud.cliente_coincidente.numero_cliente} — ${solicitud.cliente_coincidente.nombre}` }
            : null
    );
    const debounce = useRef(null);
    const esAuxiliar = String(rutaBase).startsWith('control_pedidos.');

    useEffect(() => {
        if (clienteQ.length < 2) {
            setClientes([]);
            return;
        }
        clearTimeout(debounce.current);
        debounce.current = setTimeout(() => {
            const req = esAuxiliar
                ? axios.get(route('control_pedidos.direcciones.buscar_cliente'), { params: { q: clienteQ } })
                    .then((r) => r.data?.data || [])
                : axios.get('/api/clientes', { params: { q: clienteQ } })
                    .then((r) => r.data || []);
            req.then((lista) => setClientes(lista)).catch(() => setClientes([]));
        }, 250);
        return () => clearTimeout(debounce.current);
    }, [clienteQ, esAuxiliar]);

    const existente = comparacion?.existente?.datos || {};
    const solicitada = comparacion?.solicitada || {};

    const requiereNotas = accion === 'rechazar' || accion === 'correccion';

    const enviar = (e) => {
        e.preventDefault();
        if (requiereNotas && !String(form.data.notas || '').trim()) {
            alert('Las notas son obligatorias para esta acción.');
            return;
        }
        if (accion === 'vincular' && !form.data.cliente_id) {
            alert('Seleccione un cliente para vincular.');
            return;
        }
        const ruta = {
            aprobar: `${rutaBase}.aprobar`,
            rechazar: `${rutaBase}.rechazar`,
            correccion: `${rutaBase}.correccion`,
            vincular: `${rutaBase}.vincular`,
        }[accion];
        form.post(route(ruta, solicitud.id));
    };

    return (
        <AppLayout auth={auth}>
            <Head title={`Revisar ${solicitud.folio}`} />
            <GeliaPageShell className="space-y-6">
                <div className={`${geliaCardClass('')} p-6 md:p-8`}>
                    <Link href={route(`${rutaBase}.index`)} className="inline-flex items-center gap-1 text-xs font-bold theme-text-muted mb-4">
                        <ArrowLeft className="w-3.5 h-3.5" /> Volver a bandeja
                    </Link>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div className="flex items-center gap-2 mb-2">
                                <div className="w-8 h-1.5 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }} />
                                <span className="text-[10px] font-black tracking-[0.2em] uppercase theme-text-muted">Revisión de dirección</span>
                            </div>
                            <h1 className="text-2xl font-black italic uppercase theme-text-main m-0">{solicitud.folio}</h1>
                            <p className="text-sm theme-text-muted mt-1 m-0">
                                {labelAccionSolicitud(solicitud.accion_solicitada)} · {labelEstadoSolicitud(solicitud.estado)}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <span className="px-2 py-1 rounded-md text-[10px] font-black uppercase tracking-widest theme-element border theme-border">
                                {labelEstadoSolicitud(solicitud.estado)}
                            </span>
                            {solicitud.anexa_remision && (
                                <span className="px-2 py-1 rounded-md text-[10px] font-black uppercase tracking-widest bg-amber-500/10 text-amber-700 border border-amber-500/30">
                                    Con remisión
                                </span>
                            )}
                        </div>
                    </div>

                    <div className="mt-6 grid gap-3 md:grid-cols-3 text-sm">
                        <div className="rounded-xl border theme-border p-3">
                            <p className="text-[9px] font-black uppercase theme-text-muted m-0">Declarado</p>
                            <p className="font-bold m-0 mt-1">{solicitud.nombre_declarado || '—'}</p>
                            <p className="theme-text-muted m-0 text-xs">{solicitud.telefono_declarado}</p>
                            <p className="theme-text-muted m-0 text-xs">{solicitud.correo_declarado}</p>
                        </div>
                        <div className="rounded-xl border theme-border p-3">
                            <p className="text-[9px] font-black uppercase theme-text-muted m-0">Número declarado</p>
                            <p className="font-bold m-0 mt-1">{solicitud.numero_cliente_declarado || '—'}</p>
                        </div>
                        <div className="rounded-xl border theme-border p-3">
                            <p className="text-[9px] font-black uppercase theme-text-muted m-0">Cliente vinculado</p>
                            <p className="font-bold m-0 mt-1">
                                {solicitud.cliente_coincidente
                                    ? `${solicitud.cliente_coincidente.numero_cliente} — ${solicitud.cliente_coincidente.nombre}`
                                    : 'Sin vincular'}
                            </p>
                        </div>
                    </div>
                </div>

                <div className={`${geliaCardClass('')} p-6 overflow-x-auto`}>
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4">Comparación campo a campo</h2>
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="text-left text-[9px] font-black uppercase theme-text-muted">
                                <th className="py-2 pr-3">Campo</th>
                                <th className="py-2 pr-3">Existente</th>
                                <th className="py-2">Solicitada</th>
                            </tr>
                        </thead>
                        <tbody>
                            {CAMPOS.map(([key, label]) => {
                                const a = existente[key] ?? '—';
                                const b = solicitada[key] ?? '—';
                                const difiere = String(a) !== String(b);
                                return (
                                    <tr key={key} className={`border-t theme-border ${difiere ? 'bg-amber-500/5' : ''}`}>
                                        <td className="py-2 pr-3 font-semibold">{label}</td>
                                        <td className="py-2 pr-3 theme-text-muted">{String(a)}</td>
                                        <td className={`py-2 ${difiere ? 'font-bold theme-text-main' : ''}`}>{String(b)}</td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                {solicitud.anexa_remision && solicitud.archivo_remision && (
                    <div className={`${geliaCardClass('')} p-6 flex items-center gap-3`}>
                        <FileText className="w-8 h-8 shrink-0" style={{ color: 'var(--color-primario)' }} />
                        <div className="min-w-0 flex-1">
                            <p className="font-bold m-0">Remisión anexada</p>
                            <p className="text-xs theme-text-muted m-0 truncate">{solicitud.archivo_remision}</p>
                        </div>
                        <a
                            href={route(`${rutaBase}.remision`, solicitud.id)}
                            className="text-sm font-bold underline"
                            style={{ color: 'var(--color-primario)' }}
                        >
                            Descargar
                        </a>
                    </div>
                )}

                <form onSubmit={enviar} className={`${geliaCardClass('')} p-6 space-y-4`}>
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main">Acción de revisión</h2>
                    <select className={`${THEME_SELECT} w-full py-3`} value={accion} onChange={(e) => setAccion(e.target.value)}>
                        <option value="aprobar">Aprobar</option>
                        <option value="rechazar">Rechazar</option>
                        <option value="correccion">Solicitar corrección</option>
                        <option value="vincular">Vincular cliente</option>
                    </select>

                    {accion === 'vincular' && (
                        <div className="relative">
                            <label className="block text-sm font-bold mb-1">Buscar cliente</label>
                            <div className="relative">
                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                                <input
                                    className={`${THEME_INPUT} w-full py-3 pl-10`}
                                    value={clienteQ}
                                    onChange={(e) => setClienteQ(e.target.value)}
                                    placeholder={clienteSel?.label || 'Número o nombre…'}
                                />
                            </div>
                            {clientes.length > 0 && (
                                <ul className="absolute z-20 mt-1 w-full rounded-xl border theme-border theme-surface shadow-lg max-h-48 overflow-auto">
                                    {clientes.map((c) => (
                                        <li key={c.id}>
                                            <button
                                                type="button"
                                                className="w-full text-left px-3 py-2 text-sm hover:theme-element"
                                                onClick={() => {
                                                    form.setData('cliente_id', c.id);
                                                    setClienteSel({ id: c.id, label: `${c.numero_cliente} — ${c.nombre}` });
                                                    setClienteQ('');
                                                    setClientes([]);
                                                }}
                                            >
                                                {c.numero_cliente} — {c.nombre}
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {clienteSel && (
                                <p className="text-xs theme-text-muted mt-1 m-0">Seleccionado: {clienteSel.label}</p>
                            )}
                        </div>
                    )}

                    <label className="block text-sm font-bold">
                        Notas {requiereNotas ? '(obligatorias)' : '(opcionales)'}
                        <textarea
                            className={`${THEME_TEXTAREA} w-full mt-1`}
                            value={form.data.notas}
                            onChange={(e) => form.setData('notas', e.target.value)}
                            required={requiereNotas}
                        />
                    </label>

                    <button
                        type="submit"
                        disabled={form.processing}
                        className="rounded-xl px-5 py-3 text-sm font-black uppercase tracking-widest text-white disabled:opacity-60"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        Confirmar acción
                    </button>
                </form>
            </GeliaPageShell>
        </AppLayout>
    );
}
