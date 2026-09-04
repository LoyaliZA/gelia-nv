import React, { useMemo, useRef, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, CheckCircle2, AlertTriangle, Truck } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass, THEME_BTN_PRIMARY } from '../../../utils/geliaTheme';
import ModalConfirmarAccion from '../../ControlPedidos/Partials/ModalConfirmarAccion';
import { BTN_SECONDARY } from './Partials/resguardosStyles';
import { PasoEvidencia, PasoReceptor } from './Partials/FormularioEntregaResguardo';
import useEntregaMultiple from './Partials/useEntregaMultiple';
import { validarPasoBultos, validarPasoEvidencia, validarPasoReceptor } from './Partials/entregaResguardoUtils';

function borradorInicial(resguardo) {
    const bultos = (resguardo.bultos || []).filter((bulto) => bulto.estado === 'recibido');
    return {
        relacion: 'titular',
        nombreQuienRetira: '',
        observaciones: '',
        firmaDataUrl: null,
        evidencias: [],
        bultoIds: bultos.map((bulto) => bulto.id),
    };
}

export default function EntregaMultiple({
    auth,
    resguardos = [],
    catalogos = {},
    puede_entregar: puedeEntregar = false,
    motivo_no_entregable: motivoNoEntregable = null,
}) {
    const {
        enviar,
        enviando,
        progreso,
        error,
        exito,
        resultados,
        irABandeja,
    } = useEntregaMultiple({
        resguardos,
        metodoValidacion: catalogos.metodo_validacion || 'firma',
    });

    const [indice, setIndice] = useState(0);
    const [fase, setFase] = useState('pedido');
    const [borradores, setBorradores] = useState(() => Object.fromEntries(
        resguardos.map((resguardo) => [resguardo.id, borradorInicial(resguardo)]),
    ));
    const [erroresPaso, setErroresPaso] = useState({});
    const [confirmar, setConfirmar] = useState(false);
    const firmaRef = useRef(null);

    const actual = resguardos[indice];
    const datos = actual ? borradores[actual.id] : null;
    const relaciones = catalogos.relaciones || {};

    const actualizar = (campo, valor) => {
        if (!actual) return;
        setBorradores((prev) => ({
            ...prev,
            [actual.id]: { ...prev[actual.id], [campo]: valor },
        }));
    };

    const previews = useMemo(
        () => (datos?.evidencias || []).map((archivo) => ({ archivo, url: URL.createObjectURL(archivo) })),
        [datos?.evidencias],
    );

    const persistirFirmaActual = () => {
        if (!actual || !firmaRef.current?.hasStroke?.()) return;
        actualizar('firmaDataUrl', firmaRef.current.getDataUrl());
    };

    const validarActual = () => {
        if (!datos) return false;
        const errores = {
            ...validarPasoBultos({ bultoIds: datos.bultoIds }),
            ...validarPasoReceptor({ relacion: datos.relacion, nombreQuienRetira: datos.nombreQuienRetira }),
            ...validarPasoEvidencia({ tieneFirma: Boolean(datos.firmaDataUrl) || Boolean(firmaRef.current?.hasStroke?.()) }),
        };
        setErroresPaso(errores);
        return Object.keys(errores).length === 0;
    };

    const siguiente = () => {
        persistirFirmaActual();
        if (!validarActual()) return;
        if (indice + 1 < resguardos.length) {
            setIndice(indice + 1);
            setErroresPaso({});
            return;
        }
        setFase('confirmar');
    };

    const anterior = () => {
        persistirFirmaActual();
        setErroresPaso({});
        if (fase === 'confirmar') {
            setFase('pedido');
            return;
        }
        if (indice > 0) setIndice(indice - 1);
    };

    const toggleBulto = (bultoId) => {
        if (!datos) return;
        const ids = datos.bultoIds.includes(bultoId)
            ? datos.bultoIds.filter((id) => id !== bultoId)
            : [...datos.bultoIds, bultoId];
        actualizar('bultoIds', ids);
    };

    const confirmarEnvio = async () => {
        setConfirmar(false);
        persistirFirmaActual();
        const conFirmas = { ...borradores };
        if (actual && firmaRef.current?.hasStroke?.()) {
            conFirmas[actual.id] = {
                ...conFirmas[actual.id],
                firmaDataUrl: firmaRef.current.getDataUrl(),
            };
        }
        await enviar(conFirmas);
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Entrega múltiple | Resguardos PDV" />
            <GeliaPageShell className="max-w-[720px] space-y-6">
                <Link
                    href={route('punto_venta.resguardos.index', { bandeja: 'en_custodia' })}
                    className="inline-flex items-center gap-2 text-[10px] font-black uppercase theme-text-muted hover:theme-text-main"
                >
                    <ArrowLeft className="w-4 h-4" /> Volver a en custodia
                </Link>

                <div className={`${geliaCardClass()} p-5 space-y-3`}>
                    <div className="flex items-center gap-2 min-w-0">
                        <Truck className="w-5 h-5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                        <div className="min-w-0">
                            <h1 className="text-xl font-black italic uppercase theme-text-main m-0 truncate">
                                Entrega múltiple
                            </h1>
                            <p className="text-sm font-bold theme-text-muted m-0">
                                {resguardos.length} pedidos · una firma por pedido
                            </p>
                        </div>
                    </div>
                </div>

                {exito ? (
                    <div className={`${geliaCardClass()} p-8 text-center space-y-4`}>
                        <CheckCircle2 className="w-12 h-12 mx-auto text-emerald-500" />
                        <h2 className="text-lg font-black uppercase theme-text-main m-0">Operación registrada</h2>
                        <p className="text-sm theme-text-muted m-0">
                            Se registró la entrega de {resultados.length || resguardos.length} pedido(s). Cada pedido conserva su propia firma.
                        </p>
                        <button type="button" onClick={irABandeja} className={`${THEME_BTN_PRIMARY} min-h-[48px] px-6`}>
                            Ir a en custodia
                        </button>
                    </div>
                ) : !puedeEntregar ? (
                    <div className={`${geliaCardClass()} p-5 space-y-3 border border-amber-500/30`}>
                        <div className="flex items-start gap-3">
                            <AlertTriangle className="w-5 h-5 text-amber-500 shrink-0" />
                            <p className="text-sm font-black theme-text-main m-0">
                                {motivoNoEntregable || 'No se puede completar la entrega múltiple con la selección actual.'}
                            </p>
                        </div>
                    </div>
                ) : actual && datos && fase === 'pedido' ? (
                    <div className="space-y-4">
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">
                            Pedido {indice + 1} de {resguardos.length}
                        </p>
                        <div className={`${geliaCardClass()} p-5 space-y-2`}>
                            <p className="text-sm font-black theme-text-main m-0">{actual.snapshot_folio || `#${actual.id}`}</p>
                            <p className="text-sm theme-text-muted m-0">Cliente {actual.referencia_cliente}</p>
                        </div>
                        <div className={`${geliaCardClass()} p-5 space-y-3`}>
                            <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Bultos</h2>
                            {(actual.bultos || []).filter((bulto) => bulto.estado === 'recibido').map((bulto) => (
                                <label key={bulto.id} className="flex items-center gap-3 min-h-[48px] rounded-2xl border theme-border p-3">
                                    <input
                                        type="checkbox"
                                        className="h-5 w-5 shrink-0"
                                        checked={datos.bultoIds.includes(bulto.id)}
                                        disabled={enviando}
                                        onChange={() => toggleBulto(bulto.id)}
                                    />
                                    <span className="text-sm font-bold theme-text-main">{bulto.folio || `#${bulto.id}`}</span>
                                </label>
                            ))}
                            {erroresPaso.bulto_ids && (
                                <p className="text-xs font-bold text-red-600 dark:text-red-300 m-0">{erroresPaso.bulto_ids}</p>
                            )}
                        </div>
                        <PasoReceptor
                            relacion={datos.relacion}
                            onRelacion={(valor) => actualizar('relacion', valor)}
                            nombreQuienRetira={datos.nombreQuienRetira}
                            onNombre={(valor) => actualizar('nombreQuienRetira', valor)}
                            observaciones={datos.observaciones}
                            onObservaciones={(valor) => actualizar('observaciones', valor)}
                            relaciones={relaciones}
                            errores={erroresPaso}
                            deshabilitado={enviando}
                        />
                        <PasoEvidencia
                            key={actual.id}
                            firmaRef={firmaRef}
                            previews={previews}
                            onAgregar={(archivos) => {
                                const imagenes = Array.from(archivos || []).filter((f) => f.type.startsWith('image/'));
                                actualizar('evidencias', [...(datos.evidencias || []), ...imagenes]);
                            }}
                            onQuitar={(i) => actualizar('evidencias', datos.evidencias.filter((_, idx) => idx !== i))}
                            errores={erroresPaso}
                            deshabilitado={enviando}
                        />
                    </div>
                ) : (
                    <div className={`${geliaCardClass()} p-5 space-y-3`}>
                        <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Confirmar operación</h2>
                        {resguardos.map((resguardo) => {
                            const item = borradores[resguardo.id];
                            return (
                                <div key={resguardo.id} className="rounded-2xl border theme-border p-3 space-y-1">
                                    <p className="text-sm font-black theme-text-main m-0">
                                        {resguardo.snapshot_folio || `#${resguardo.id}`}
                                    </p>
                                    <p className="text-[10px] theme-text-muted m-0">
                                        {item?.nombreQuienRetira} · {relaciones[item?.relacion] || item?.relacion} · {item?.bultoIds?.length || 0} bulto(s)
                                    </p>
                                </div>
                            );
                        })}
                    </div>
                )}

                {error && (
                    <div className={`${geliaCardClass()} p-4 border border-red-500/30`}>
                        <p className="text-sm font-semibold text-red-600 dark:text-red-300 m-0">{error}</p>
                    </div>
                )}

                {enviando && (
                    <p className="text-[10px] font-black uppercase theme-text-muted m-0">Registrando… {progreso}%</p>
                )}

                {!exito && puedeEntregar && (
                    <div className="flex flex-col sm:flex-row gap-2">
                        {(indice > 0 || fase === 'confirmar') && !enviando && (
                            <button type="button" onClick={anterior} className={`${BTN_SECONDARY} min-h-[48px] flex-1`}>
                                <ArrowLeft className="w-4 h-4 inline mr-2" />
                                Anterior
                            </button>
                        )}
                        {fase === 'pedido' && (
                            <button
                                type="button"
                                onClick={siguiente}
                                disabled={enviando}
                                className={`${THEME_BTN_PRIMARY} min-h-[48px] flex-1 text-[10px] font-black uppercase tracking-widest disabled:opacity-50`}
                            >
                                {indice + 1 < resguardos.length ? 'Siguiente pedido' : 'Revisar operación'}
                                <ArrowRight className="w-4 h-4 inline ml-2" />
                            </button>
                        )}
                        {fase === 'confirmar' && (
                            <button
                                type="button"
                                onClick={() => setConfirmar(true)}
                                disabled={enviando}
                                className={`${THEME_BTN_PRIMARY} min-h-[48px] flex-1 text-[10px] font-black uppercase tracking-widest disabled:opacity-50`}
                            >
                                Confirmar entregas
                            </button>
                        )}
                    </div>
                )}

                <ModalConfirmarAccion
                    abierto={confirmar}
                    titulo="Confirmar entrega múltiple"
                    mensaje={`Se registrarán ${resguardos.length} entregas en una sola operación, cada una con su firma.`}
                    etiquetaConfirmar="Sí, registrar"
                    variante="primary"
                    onClose={() => setConfirmar(false)}
                    onConfirm={confirmarEnvio}
                />
            </GeliaPageShell>
        </AppLayout>
    );
}
