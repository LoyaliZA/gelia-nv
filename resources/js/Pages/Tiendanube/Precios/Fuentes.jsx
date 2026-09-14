import React, { useEffect, useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import { Archive, CircleDollarSign, Search, Upload } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import { GELIA_BADGE, GELIA_BTN_OUTLINE, geliaCardClass } from '../../../utils/geliaTheme';
import PreciosNav from './Partials/PreciosNav';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const IMPORT_ESTADO_BADGE = {
    valido: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20',
    confirmado: 'bg-sky-500/10 text-sky-700 dark:text-sky-400 border-sky-500/20',
    error: 'bg-red-500/10 text-red-700 dark:text-red-400 border-red-500/20',
};

const MOTIVO_LABELS = {
    no_encontrado: 'Sin coincidencia',
    ambiguo: 'Coincidencia ambigua',
    duplicado: 'Fila duplicada',
    numero_ambiguo: 'Número ambiguo',
    negativo: 'Importe negativo',
    escala: 'Demasiados decimales',
    rango: 'Fuera de rango',
    moneda_incompatible: 'Moneda incompatible',
    moneda_invalida: 'Moneda inválida',
    sin_importe: 'Sin importe',
    destino_ambiguo: 'Destino no explícito',
    lista_inexistente: 'Lista desconocida',
    numero_invalido: 'Número inválido',
};

export default function Fuentes({ auth, configuracion, listas: listasIniciales = [], permisos, variante_id_inicial: varianteIdInicial }) {
    const [listas, setListas] = useState(listasIniciales);
    const [sku, setSku] = useState('');
    const [varianteId, setVarianteId] = useState(varianteIdInicial ? String(varianteIdInicial) : '');
    const [valor, setValor] = useState('');
    const [moneda, setMoneda] = useState('MXN');
    const [motivo, setMotivo] = useState('');
    const [tipoDestino, setTipoDestino] = useState('costo_local');
    const [listaId, setListaId] = useState('');
    const [resolucion, setResolucion] = useState(null);
    const [mensaje, setMensaje] = useState(null);
    const [nombreLista, setNombreLista] = useState('');
    const [archivo, setArchivo] = useState(null);
    const [delimiter, setDelimiter] = useState(',');
    const [decimalSep, setDecimalSep] = useState('.');
    const [colIdent, setColIdent] = useState('sku');
    const [identTipo, setIdentTipo] = useState('sku');
    const [colCosto, setColCosto] = useState('costo');
    const [colLista, setColLista] = useState('');
    const [listaImportId, setListaImportId] = useState('');
    const [revision, setRevision] = useState(null);
    const [seleccion, setSeleccion] = useState({});
    const [busy, setBusy] = useState(false);
    const [csvPerfil, setCsvPerfil] = useState(null);
    const [csvPlantilla, setCsvPlantilla] = useState(null);

    const listasActivas = useMemo(() => listas.filter((l) => !l.archived_at), [listas]);
    const puedeEditar = !!permisos?.precios_editar;
    const puedeImportar = !!permisos?.precios_importar;
    const puedeVerValores = !!permisos?.precios_ver;
    const puedeConfigurarCsv = !!permisos?.configurar;

    const jsonHeaders = { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() };

    const buscar = async (e) => {
        e?.preventDefault?.();
        setMensaje(null);
        const params = new URLSearchParams();
        if (varianteId) params.set('variante_id', varianteId);
        else params.set('sku', sku);
        const res = await fetch(`${route('tiendanube.precios.variantes.resolver')}?${params}`, { headers: jsonHeaders });
        const data = await res.json();
        setResolucion(data);
        if (!res.ok) setMensaje(data.message || 'No se pudo resolver la variante.');
    };

    useEffect(() => {
        if (varianteIdInicial) {
            buscar();
        }
        // ponytail: resolución inicial desde el catálogo de precios; no reejecutar en cada tecla.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [varianteIdInicial]);

    useEffect(() => {
        fetch(route('tiendanube.precios.csv_perfil.show'), { headers: { Accept: 'application/json' } })
            .then((res) => res.ok ? res.json() : null)
            .then((data) => { if (data) setCsvPerfil(data); })
            .catch(() => {});
    }, []);

    const capturar = async (e) => {
        e.preventDefault();
        if (!resolucion?.variante_id) {
            setMensaje('Resuelva primero una variante única.');
            return;
        }
        setBusy(true);
        setMensaje(null);
        try {
            const res = await fetch(route('tiendanube.precios.costos.store'), {
                method: 'POST',
                headers: { ...jsonHeaders, 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    variante_id: resolucion.variante_id,
                    valor,
                    moneda,
                    motivo: motivo || null,
                    tipo: tipoDestino,
                    lista_id: tipoDestino === 'lista_referencia' ? listaId || null : null,
                }),
            });
            const data = await res.json();
            if (!res.ok) {
                setMensaje(data.message || 'No se pudo guardar.');
                return;
            }
            setMensaje(`Versión ${data.version.version} guardada: ${data.version.valor_decimal} ${data.version.moneda}.`);
            setValor('');
            setMotivo('');
        } finally {
            setBusy(false);
        }
    };

    const crearLista = async (e) => {
        e.preventDefault();
        setBusy(true);
        setMensaje(null);
        try {
            const res = await fetch(route('tiendanube.precios.listas.store'), {
                method: 'POST',
                headers: { ...jsonHeaders, 'Content-Type': 'application/json' },
                body: JSON.stringify({ nombre: nombreLista }),
            });
            const data = await res.json();
            if (!res.ok) {
                setMensaje(data.message || 'No se pudo crear la lista.');
                return;
            }
            setListas((prev) => [...prev, data]);
            setNombreLista('');
        } finally {
            setBusy(false);
        }
    };

    const renombrarLista = async (lista, nombre) => {
        const res = await fetch(route('tiendanube.precios.listas.update', lista.id), {
            method: 'PUT',
            headers: { ...jsonHeaders, 'Content-Type': 'application/json' },
            body: JSON.stringify({ nombre }),
        });
        const data = await res.json();
        if (!res.ok) {
            setMensaje(data.message || 'No se pudo renombrar.');
            return;
        }
        setListas((prev) => prev.map((l) => (l.id === data.id ? { ...l, nombre: data.nombre } : l)));
    };

    const archivarLista = async (lista) => {
        const res = await fetch(route('tiendanube.precios.listas.update', lista.id), {
            method: 'PUT',
            headers: { ...jsonHeaders, 'Content-Type': 'application/json' },
            body: JSON.stringify({ archivar: true }),
        });
        const data = await res.json();
        if (!res.ok) {
            setMensaje(data.message || 'No se pudo archivar.');
            return;
        }
        setListas((prev) => prev.map((l) => (l.id === data.id ? data : l)));
    };

    const previsualizar = async (e) => {
        e.preventDefault();
        if (!archivo) {
            setMensaje('Seleccione un archivo CSV.');
            return;
        }
        setBusy(true);
        setMensaje(null);
        try {
            const form = new FormData();
            form.append('archivo', archivo);
            form.append('delimiter', delimiter);
            form.append('decimal_sep', decimalSep);
            form.append('identificador', identTipo);
            form.append('columna_identificador', colIdent);
            form.append('columnas_importes[costo_local]', colCosto);
            if (colLista && listaImportId) {
                form.append(`columnas_importes[lista:${listaImportId}]`, colLista);
            }
            form.append('moneda_fija', moneda);
            const res = await fetch(route('tiendanube.precios.importar'), {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: form,
            });
            const data = await res.json();
            if (!res.ok) {
                setMensaje(data.message || 'No se pudo leer el archivo.');
                return;
            }
            setRevision(data);
            const sel = {};
            (data.items || []).forEach((item) => {
                sel[item.id] = item.estado === 'valido';
            });
            setSeleccion(sel);
        } finally {
            setBusy(false);
        }
    };

    const confirmarImport = async () => {
        if (!revision?.id) return;
        const ids = Object.entries(seleccion)
            .filter(([, on]) => on)
            .map(([id]) => Number(id));
        setBusy(true);
        setMensaje(null);
        try {
            const res = await fetch(route('tiendanube.precios.importar.confirmar', revision.id), {
                method: 'POST',
                headers: { ...jsonHeaders, 'Content-Type': 'application/json' },
                body: JSON.stringify({ item_ids: ids }),
            });
            const data = await res.json();
            if (!res.ok) {
                setMensaje(data.message || 'No se pudo confirmar.');
                return;
            }
            setRevision(data);
            setMensaje('Lote confirmado. Solo se guardaron las filas válidas seleccionadas.');
        } finally {
            setBusy(false);
        }
    };

    const validarCsvPerfil = async (e) => {
        e.preventDefault();
        if (!csvPlantilla) {
            setMensaje('Seleccione la plantilla CSV exportada del panel de TiendaNube.');
            return;
        }
        setBusy(true);
        setMensaje(null);
        try {
            const form = new FormData();
            form.append('plantilla', csvPlantilla);
            const res = await fetch(route('tiendanube.precios.csv_perfil.validar'), {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: form,
            });
            const data = await res.json();
            if (!res.ok) {
                setMensaje(data.message || 'No se pudo validar la plantilla.');
                return;
            }
            setCsvPerfil((prev) => ({ ...(prev || {}), perfil: data, validado: true, mensaje_sin_perfil: null }));
            setMensaje('Plantilla validada. Ya se puede exportar CSV nativo.');
        } finally {
            setBusy(false);
        }
    };

    const fuenteCosto = resolucion?.fuentes?.fuentes?.find((f) => f.tipo === 'costo_local');
    const fuenteRemota = resolucion?.fuentes?.fuentes?.find((f) => f.tipo === 'costo_remoto_actual');

    return (
        <AppLayout auth={auth}>
            <Head title="Costos y listas Tiendanube" />
            <div className="max-w-[1440px] mx-auto p-3 md:p-4 space-y-4">
                <header className={`${geliaCardClass()} p-3 md:p-4`}>
                    <PreciosNav activa="fuentes" />
                    <div className="flex items-center gap-3">
                        <CircleDollarSign className="w-6 h-6" style={{ color: 'var(--color-primario)' }} />
                        <div>
                            <h1 className="text-xl font-black italic uppercase theme-text-main m-0">
                                Costos y listas
                            </h1>
                            <p className="text-xs theme-text-muted mt-1">
                                Captura local por variante. No publica a Tiendanube.
                                {configuracion?.store_name ? ` · ${configuracion.store_name}` : ''}
                            </p>
                        </div>
                    </div>
                </header>

                {mensaje && (
                    <div className={`${geliaCardClass()} p-4 text-sm theme-text-main`}>{mensaje}</div>
                )}

                <section className={`${geliaCardClass()} p-4 md:p-6 space-y-4`}>
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main">Captura manual</h2>
                    {!puedeEditar && (
                        <p className="text-xs theme-text-muted">Se requiere permiso para editar costos.</p>
                    )}
                    <form onSubmit={buscar} className="flex flex-col md:flex-row gap-3">
                        <div className="flex-1">
                            <label htmlFor="tn-fuente-sku" className="theme-label">SKU</label>
                            <input
                                id="tn-fuente-sku"
                                value={sku}
                                onChange={(e) => setSku(e.target.value)}
                                className="w-full rounded-xl border theme-border px-3 py-2 text-sm"
                                disabled={!puedeEditar}
                            />
                        </div>
                        <div className="w-full md:w-40">
                            <label htmlFor="tn-fuente-variante" className="theme-label">ID de variante</label>
                            <input
                                id="tn-fuente-variante"
                                value={varianteId}
                                onChange={(e) => setVarianteId(e.target.value)}
                                className="w-full rounded-xl border theme-border px-3 py-2 text-sm"
                                disabled={!puedeEditar}
                            />
                        </div>
                        <button
                            type="submit"
                            disabled={!puedeEditar}
                            className={`${GELIA_BTN_OUTLINE} px-4 py-2 md:self-end`}
                        >
                            <Search className="w-4 h-4" /> Buscar
                        </button>
                    </form>
                    {resolucion && (
                        <div className="text-sm theme-text-main space-y-1">
                            <p className="flex flex-wrap items-center gap-2">
                                <span className={`${GELIA_BADGE} theme-text-main border theme-border`}>{resolucion.estado}</span>
                                {resolucion.variante_id ? <span>variante {resolucion.variante_id}</span> : null}
                                {resolucion.sku ? <span>SKU {resolucion.sku}</span> : null}
                            </p>
                            {resolucion.estado === 'ambiguo' && (
                                <p className="text-amber-700">Hay varias coincidencias. Indique el ID de variante; no se elige la primera.</p>
                            )}
                            {puedeVerValores && fuenteCosto && (
                                <p>
                                    Costo local: {fuenteCosto.faltante ? 'no informado' : `${fuenteCosto.valor_decimal} ${fuenteCosto.moneda} (v${fuenteCosto.version})`}
                                </p>
                            )}
                            {puedeVerValores && fuenteRemota && (
                                <p>
                                    Costo remoto (espejo): {fuenteRemota.faltante ? 'ausente' : `${fuenteRemota.valor_decimal} ${fuenteRemota.moneda}`}
                                </p>
                            )}
                        </div>
                    )}
                    {puedeEditar && (
                        <form onSubmit={capturar} className="grid grid-cols-1 md:grid-cols-5 gap-3">
                            <div>
                                <label htmlFor="tn-fuente-importe" className="theme-label">Importe</label>
                                <input
                                    id="tn-fuente-importe"
                                    value={valor}
                                    onChange={(e) => setValor(e.target.value)}
                                    className="w-full rounded-xl border theme-border px-3 py-2 text-sm"
                                />
                                <p className="text-[10px] theme-text-muted mt-1">Ejemplo: 330.00</p>
                            </div>
                            <div>
                                <label htmlFor="tn-fuente-moneda" className="theme-label">Moneda</label>
                                <input
                                    id="tn-fuente-moneda"
                                    value={moneda}
                                    onChange={(e) => setMoneda(e.target.value.toUpperCase())}
                                    maxLength={3}
                                    className="w-full rounded-xl border theme-border px-3 py-2 text-sm uppercase"
                                />
                            </div>
                            <div>
                                <label htmlFor="tn-fuente-destino" className="theme-label">Guardar en</label>
                                <select
                                    id="tn-fuente-destino"
                                    value={tipoDestino}
                                    onChange={(e) => setTipoDestino(e.target.value)}
                                    className="w-full rounded-xl border theme-border px-3 py-2 text-sm"
                                >
                                    <option value="costo_local">Costo local</option>
                                    <option value="lista_referencia">Lista de referencia</option>
                                </select>
                            </div>
                            {tipoDestino === 'lista_referencia' && (
                                <div>
                                    <label htmlFor="tn-fuente-lista" className="theme-label">Lista</label>
                                    <select
                                        id="tn-fuente-lista"
                                        value={listaId}
                                        onChange={(e) => setListaId(e.target.value)}
                                        className="w-full rounded-xl border theme-border px-3 py-2 text-sm"
                                    >
                                        <option value="">Seleccione lista</option>
                                        {listasActivas.map((l) => (
                                            <option key={l.id} value={l.id}>{l.nombre}</option>
                                        ))}
                                    </select>
                                </div>
                            )}
                            <div className="md:col-span-2">
                                <label htmlFor="tn-fuente-motivo" className="theme-label">Motivo (opcional)</label>
                                <input
                                    id="tn-fuente-motivo"
                                    value={motivo}
                                    onChange={(e) => setMotivo(e.target.value)}
                                    className="w-full rounded-xl border theme-border px-3 py-2 text-sm"
                                />
                            </div>
                            <button
                                type="submit"
                                disabled={busy}
                                className={`${GELIA_BTN_OUTLINE} px-4 py-2 md:self-end`}
                            >
                                Guardar costo
                            </button>
                            <p className="text-xs theme-text-muted md:col-span-5">
                                Guarda el costo local en Gelia. No publica el costo remoto de TiendaNube.
                            </p>
                        </form>
                    )}
                </section>

                <section className={`${geliaCardClass()} p-4 md:p-6 space-y-4`}>
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main">Listas de referencia</h2>
                    {puedeEditar && (
                        <form onSubmit={crearLista} className="flex flex-col sm:flex-row gap-3">
                            <input
                                value={nombreLista}
                                onChange={(e) => setNombreLista(e.target.value)}
                                placeholder="Nombre libre"
                                className="flex-1 rounded-xl border theme-border px-3 py-2 text-sm"
                            />
                            <button type="submit" disabled={busy} className={`${GELIA_BTN_OUTLINE} px-4 py-2`}>
                                Crear lista
                            </button>
                        </form>
                    )}
                    <ul className="space-y-2">
                        {listas.map((lista) => (
                            <li key={lista.id} className="flex flex-wrap items-center gap-2 text-sm">
                                <span className="theme-text-muted">#{lista.id}</span>
                                {puedeEditar && !lista.archived_at ? (
                                    <input
                                        defaultValue={lista.nombre}
                                        onBlur={(e) => {
                                            const next = e.target.value.trim();
                                            if (next && next !== lista.nombre) renombrarLista(lista, next);
                                        }}
                                        className="rounded-lg border theme-border px-2 py-1"
                                    />
                                ) : (
                                    <span className="theme-text-main">{lista.nombre}</span>
                                )}
                                {lista.archived_at && (
                                    <span className={`${GELIA_BADGE} theme-text-muted border theme-border`}>
                                        Archivada
                                    </span>
                                )}
                                {puedeEditar && !lista.archived_at && (
                                    <button type="button" onClick={() => archivarLista(lista)} className="inline-flex items-center gap-1 text-[10px] uppercase tracking-widest theme-text-muted">
                                        <Archive className="w-3 h-3" /> Archivar
                                    </button>
                                )}
                            </li>
                        ))}
                        {listas.length === 0 && <li className="text-xs theme-text-muted">No hay listas.</li>}
                    </ul>
                </section>

                <section className={`${geliaCardClass()} p-4 md:p-6 space-y-4`}>
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main">Importación de costos</h2>
                    <p className="text-xs theme-text-muted">Nombre de archivo, errores y vista previa. No es la exportación hacia TiendaNube.</p>
                    {!puedeImportar && <p className="text-xs theme-text-muted">Se requiere permiso para importar.</p>}
                    {puedeImportar && (
                        <form onSubmit={previsualizar} className="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <input type="file" accept=".csv,text/csv" onChange={(e) => setArchivo(e.target.files?.[0] || null)} />
                            <select value={delimiter} onChange={(e) => setDelimiter(e.target.value)} className="rounded-xl border theme-border px-3 py-2 text-sm">
                                <option value=",">Delimitador ,</option>
                                <option value=";">Delimitador ;</option>
                                <option value="tab">Delimitador tab</option>
                            </select>
                            <select value={decimalSep} onChange={(e) => setDecimalSep(e.target.value)} className="rounded-xl border theme-border px-3 py-2 text-sm">
                                <option value=".">Decimal .</option>
                                <option value=",">Decimal ,</option>
                            </select>
                            <select value={identTipo} onChange={(e) => setIdentTipo(e.target.value)} className="rounded-xl border theme-border px-3 py-2 text-sm">
                                <option value="sku">Identificador SKU</option>
                                <option value="variante_id">Identificador ID variante</option>
                            </select>
                            <input value={colIdent} onChange={(e) => setColIdent(e.target.value)} placeholder="Columna identificador" className="rounded-xl border theme-border px-3 py-2 text-sm" />
                            <input value={colCosto} onChange={(e) => setColCosto(e.target.value)} placeholder="Columna costo" className="rounded-xl border theme-border px-3 py-2 text-sm" />
                            <select value={listaImportId} onChange={(e) => setListaImportId(e.target.value)} className="rounded-xl border theme-border px-3 py-2 text-sm">
                                <option value="">Lista opcional</option>
                                {listasActivas.map((l) => (
                                    <option key={l.id} value={l.id}>{l.nombre}</option>
                                ))}
                            </select>
                            <input value={colLista} onChange={(e) => setColLista(e.target.value)} placeholder="Columna lista (opcional)" className="rounded-xl border theme-border px-3 py-2 text-sm" />
                            <button type="submit" disabled={busy} className={`${GELIA_BTN_OUTLINE} px-4 py-2`}>
                                <Upload className="w-4 h-4" /> Vista previa
                            </button>
                        </form>
                    )}
                    {revision && (
                        <div className="space-y-3 overflow-x-auto">
                            <p className="text-xs theme-text-muted">
                                Filas {revision.total_filas} · válidas {revision.validas} · con error {revision.errores}
                            </p>
                            <table className="w-full text-xs">
                                <thead>
                                    <tr className="text-left theme-text-muted">
                                        <th className="p-2">OK</th>
                                        <th className="p-2">Fila</th>
                                        <th className="p-2">SKU</th>
                                        <th className="p-2">Variante</th>
                                        <th className="p-2">Anterior</th>
                                        <th className="p-2">Nuevo</th>
                                        <th className="p-2">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(revision.items || []).map((item) => (
                                        <tr key={item.id} className="border-t theme-border">
                                            <td className="p-2">
                                                <input
                                                    type="checkbox"
                                                    disabled={item.estado !== 'valido' && item.estado !== 'confirmado'}
                                                    checked={!!seleccion[item.id]}
                                                    onChange={(e) => setSeleccion((s) => ({ ...s, [item.id]: e.target.checked }))}
                                                />
                                            </td>
                                            <td className="p-2">{item.fila}</td>
                                            <td className="p-2 font-mono">{item.sku || '—'}</td>
                                            <td className="p-2">{item.variante_id || '—'}</td>
                                            <td className="p-2">{item.valor_anterior ?? '—'}</td>
                                            <td className="p-2">{item.valor_decimal ?? item.valor_raw ?? '—'}</td>
                                            <td className="p-2">
                                                <span className={`${GELIA_BADGE} ${IMPORT_ESTADO_BADGE[item.estado] || 'theme-text-main border theme-border'}`}>
                                                    {item.estado}
                                                </span>
                                                {item.motivo ? (
                                                    <span className="block mt-1 text-[10px] theme-text-muted uppercase tracking-widest">
                                                        {MOTIVO_LABELS[item.motivo] || item.motivo}
                                                    </span>
                                                ) : null}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            {revision.estado === 'revision' && (
                                <button type="button" onClick={confirmarImport} disabled={busy} className={`${GELIA_BTN_OUTLINE} px-4 py-2`}>
                                    Confirmar seleccionadas
                                </button>
                            )}
                        </div>
                    )}
                </section>

                <section className={`${geliaCardClass()} p-4 md:p-6 space-y-3`}>
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main">Exportación TiendaNube</h2>
                    <p className="text-xs theme-text-muted m-0">
                        Suba una exportación reciente del panel (producto simple y, si aplica, multivariante). El delimitador y las columnas quedan fijos; no se eligen en cada descarga.
                    </p>
                    {csvPerfil?.validado ? (
                        <p className="text-sm theme-text-main m-0">
                            Plantilla validada · versión {csvPerfil.perfil?.version} · {csvPerfil.perfil?.plantilla_fecha || csvPerfil.perfil?.validado_at}
                        </p>
                    ) : (
                        <p className="text-sm theme-text-main m-0">{csvPerfil?.mensaje_sin_perfil || 'Configura una exportación de ejemplo de tu tienda'}</p>
                    )}
                    {puedeConfigurarCsv && (
                        <form onSubmit={validarCsvPerfil} className="flex flex-col sm:flex-row gap-2 items-start">
                            <input
                                type="file"
                                accept=".csv,text/csv"
                                onChange={(e) => setCsvPlantilla(e.target.files?.[0] || null)}
                                className="text-sm"
                            />
                            <button type="submit" disabled={busy} className={`${GELIA_BTN_OUTLINE} px-4 py-2`}>
                                Validar plantilla
                            </button>
                        </form>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
