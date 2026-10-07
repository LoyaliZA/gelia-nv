import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { ChevronDown, FileSpreadsheet, HelpCircle, UploadCloud, X } from 'lucide-react';
import EscalonamientoModal from './EscalonamientoModal';
import {
    GELIA_BTN_OUTLINE,
    GELIA_ESTADO_VIVO_TONO,
    GELIA_MODAL_TITLE,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_INPUT,
    THEME_LABEL,
    geliaCardClass,
    geliaToggleBtnClass,
} from '../../../utils/geliaTheme';
import {
    dinero,
    etiquetaTipoDocumento,
    MESES,
    RESULTADOS_IMPORTACION,
    tonoResultadoImportacion,
} from './escalonamientoUi';

const PASOS = ['Archivo', 'Revisar', 'Confirmar'];

export default function DialogosDocumentosEscalonamiento({
    importar,
    onCerrarImportar,
    previsualizacion,
    capturar,
    onCerrarCaptura,
    periodo,
    periodoOperativo,
}) {
    const [paso, setPaso] = useState(0);
    const [ayuda, setAyuda] = useState(false);
    const [filtroResultado, setFiltroResultado] = useState('');
    const [filtroProblemas, setFiltroProblemas] = useState(false);
    const [busquedaFila, setBusquedaFila] = useState('');
    const [arrastrandoArchivo, setArrastrandoArchivo] = useState(false);
    const inputArchivoRef = useRef(null);
    const cuerpoModalRef = useRef(null);

    const archivo = useForm({ archivo: null, tipo: 'remision' });
    const confirmar = useForm({ importacion_id: previsualizacion?.id || '' });
    const captura = useForm({
        tipo: 'remision',
        folio: '',
        serie: '',
        sucursal: '',
        numero_cliente: '',
        moneda: 'MXN',
        total: '',
        fecha_emision: periodo ? `${periodo.anio}-${String(periodo.mes).padStart(2, '0')}-01` : '',
        estado: 'activo',
        remision_original: '',
    });

    useEffect(() => {
        if (previsualizacion?.id) {
            confirmar.setData('importacion_id', previsualizacion.id);
            setPaso(1);
        } else {
            setPaso(0);
        }
    }, [previsualizacion?.id]);

    useEffect(() => {
        if (!importar) {
            setPaso(0);
            setFiltroResultado('');
            setFiltroProblemas(false);
            setBusquedaFila('');
            setArrastrandoArchivo(false);
            archivo.setData('archivo', null);
            if (inputArchivoRef.current) {
                inputArchivoRef.current.value = '';
            }
        }
    }, [importar]);

    useEffect(() => {
        if (importar && cuerpoModalRef.current) {
            cuerpoModalRef.current.scrollTop = 0;
        }
    }, [importar, previsualizacion?.id]);

    const limpiarSeleccionArchivo = useCallback(() => {
        archivo.setData('archivo', null);
        if (inputArchivoRef.current) {
            inputArchivoRef.current.value = '';
        }
    }, [archivo]);

    const cerrarImportacion = useCallback(() => {
        archivo.reset();
        limpiarSeleccionArchivo();
        setPaso(0);
        setFiltroResultado('');
        setFiltroProblemas(false);
        setBusquedaFila('');
        onCerrarImportar?.();
        if (previsualizacion) {
            router.get(
                route('escalonamiento.index'),
                periodo?.id ? { periodo_id: periodo.id } : {},
                { preserveScroll: true, replace: true },
            );
        }
    }, [archivo, limpiarSeleccionArchivo, onCerrarImportar, previsualizacion, periodo?.id]);

    const asignarArchivo = (file) => {
        if (!file) return;
        archivo.setData('archivo', file);
    };

    const enviarArchivo = (event) => {
        event.preventDefault();
        archivo.post(route('escalonamiento.importaciones.previsualizar'), { forceFormData: true });
    };

    const enviarConfirmacion = (event) => {
        event.preventDefault();
        confirmar.post(route('escalonamiento.importaciones.confirmar'), {
            onSuccess: () => {
                setPaso(2);
                onCerrarImportar?.();
            },
        });
    };

    const cerrarCaptura = useCallback(() => {
        captura.reset();
        captura.clearErrors();
        onCerrarCaptura?.();
    }, [captura, onCerrarCaptura]);

    const enviarCaptura = (event) => {
        event.preventDefault();
        captura.post(route('escalonamiento.capturas.store'), {
            onSuccess: cerrarCaptura,
        });
    };

    const filasVisibles = useMemo(() => {
        if (!Array.isArray(previsualizacion?.filas)) return [];
        const q = busquedaFila.trim().toLowerCase();

        return previsualizacion.filas.filter((fila) => {
            if (filtroResultado && fila.resultado !== filtroResultado) return false;
            if (filtroProblemas && !esResultadoProblematico(fila.resultado)) return false;
            if (!q) return true;

            const texto = [fila.folio, fila.numero_cliente, fila.nombre]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();

            return texto.includes(q);
        });
    }, [previsualizacion?.filas, filtroResultado, filtroProblemas, busquedaFila]);

    const periodoDestino = periodoOperativo || periodo;
    const puedeConfirmar = Boolean(previsualizacion?.puede_confirmar);

    return (
        <>
            <EscalonamientoModal
                abierto={importar}
                onClose={cerrarImportacion}
                labelledBy="titulo-importar-documentos"
                maxWidth="max-w-5xl"
            >
                <div className="flex min-h-0 flex-1 flex-col overflow-hidden w-full">
                    <header className="p-6 md:p-8 pb-5 border-b theme-border shrink-0 flex flex-col gap-5">
                        <div className="flex flex-col gap-4">
                            <h2 id="titulo-importar-documentos" className={GELIA_MODAL_TITLE}>Importar documentos</h2>
                            <p className="text-sm theme-text-muted m-0 leading-relaxed max-w-3xl">
                                Carga remisiones y devoluciones del ERP. Las devoluciones quedan pendientes hasta vincular la compra nueva.
                            </p>
                            <button
                                type="button"
                                className="text-sm font-semibold theme-text-main inline-flex items-center gap-2 self-start py-1"
                                onClick={() => setAyuda((v) => !v)}
                                aria-expanded={ayuda}
                            >
                                <HelpCircle className="w-4 h-4" aria-hidden />
                                Ayuda sobre duplicados y períodos
                                <ChevronDown className={`w-4 h-4 transition-transform ${ayuda ? 'rotate-180' : ''}`} aria-hidden />
                            </button>
                            {ayuda && (
                                <p className="text-sm theme-text-muted m-0 leading-relaxed max-w-3xl pl-0">
                                    Indica si el archivo trae remisiones o devoluciones. Una carga repetida no cambia el acumulado.
                                    Las fechas definen el período destino: meses anteriores van a historial; meses posteriores quedan pendientes de revisión administrativa.
                                </p>
                            )}
                        </div>
                        <div className={`${geliaCardClass('p-5 md:p-6')} flex flex-col gap-5`}>
                            {periodoDestino && (
                                <p className="text-sm theme-text-main m-0 font-semibold leading-snug">
                                    Período destino: {MESES[periodoDestino.mes - 1]} {periodoDestino.anio} ({periodoDestino.estado})
                                </p>
                            )}
                            <ol className="flex flex-wrap gap-3 m-0 p-0 list-none text-xs font-semibold">
                                {PASOS.map((nombre, i) => (
                                    <li
                                        key={nombre}
                                        className={geliaToggleBtnClass(i <= paso)}
                                    >
                                        {i + 1}. {nombre}
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </header>

                    <div ref={cuerpoModalRef} className="gelia-modal-body p-6 md:p-8 pb-8 overscroll-contain">
                        {!previsualizacion && (
                            <form id="form-archivo-escalonamiento" onSubmit={enviarArchivo} className="flex flex-col gap-8 max-w-2xl">
                                <fieldset className={`${geliaCardClass('p-6 md:p-7')} border-0 m-0 min-w-0 flex flex-col gap-0`}>
                                    <legend className={`${THEME_LABEL} !mb-0 block w-full`}>Tipo de documento en este archivo</legend>
                                    <p className="text-sm theme-text-muted m-0 leading-relaxed mt-4">
                                        El reporte del ERP no incluye columna de tipo; indica si cargas remisiones o devoluciones.
                                    </p>
                                    <div className="flex flex-wrap gap-8 pt-6 mt-6 border-t theme-border text-sm font-semibold theme-text-main">
                                        <label className="inline-flex items-center gap-3 cursor-pointer py-1">
                                            <input
                                                type="radio"
                                                name="tipo-importacion"
                                                value="remision"
                                                checked={archivo.data.tipo === 'remision'}
                                                onChange={() => archivo.setData('tipo', 'remision')}
                                            />
                                            Remisiones
                                        </label>
                                        <label className="inline-flex items-center gap-3 cursor-pointer py-1">
                                            <input
                                                type="radio"
                                                name="tipo-importacion"
                                                value="devolucion"
                                                checked={archivo.data.tipo === 'devolucion'}
                                                onChange={() => archivo.setData('tipo', 'devolucion')}
                                            />
                                            Devoluciones
                                        </label>
                                    </div>
                                </fieldset>

                                <fieldset className={`${geliaCardClass('p-6 md:p-7')} border-0 m-0 min-w-0 flex flex-col gap-0`}>
                                    <legend className={`${THEME_LABEL} !mb-0 block w-full`}>Archivo CSV o Excel</legend>
                                    <div
                                        className={`theme-form-zone p-8 md:p-10 flex flex-col items-center text-center gap-5 mt-6 transition-colors ${
                                            arrastrandoArchivo ? 'border-[var(--color-primario)]' : ''
                                        }`}
                                        onDragOver={(event) => {
                                            event.preventDefault();
                                            setArrastrandoArchivo(true);
                                        }}
                                        onDragLeave={() => setArrastrandoArchivo(false)}
                                        onDrop={(event) => {
                                            event.preventDefault();
                                            setArrastrandoArchivo(false);
                                            asignarArchivo(event.dataTransfer.files?.[0] || null);
                                        }}
                                    >
                                        <UploadCloud className="w-10 h-10 theme-text-muted shrink-0" aria-hidden />
                                        <p className="text-sm font-semibold theme-text-main m-0">
                                            Arrastra el archivo aquí o elige uno desde tu equipo
                                        </p>
                                        <p className="text-xs theme-text-muted m-0 leading-relaxed">Formatos: .csv, .txt, .xlsx, .xls</p>
                                        <button
                                            type="button"
                                            className={`${GELIA_BTN_OUTLINE} mt-2`}
                                            onClick={() => inputArchivoRef.current?.click()}
                                        >
                                            <FileSpreadsheet className="w-4 h-4 shrink-0" aria-hidden />
                                            Seleccionar archivo
                                        </button>
                                        <input
                                            ref={inputArchivoRef}
                                            type="file"
                                            accept=".csv,.txt,.xlsx,.xls"
                                            className="hidden"
                                            onChange={(event) => asignarArchivo(event.target.files?.[0] || null)}
                                        />
                                    </div>
                                    {archivo.data.archivo ? (
                                        <div className={`${geliaCardClass('p-5')} flex items-start gap-4 text-left mt-6`}>
                                            <FileSpreadsheet className="w-5 h-5 shrink-0 theme-text-primario" aria-hidden />
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-semibold theme-text-main m-0 truncate">{archivo.data.archivo.name}</p>
                                                <p className="text-xs theme-text-muted m-0 mt-1 tabular-nums">
                                                    {(archivo.data.archivo.size / 1024).toFixed(1)} KB
                                                </p>
                                            </div>
                                            <button
                                                type="button"
                                                className="p-1.5 rounded-lg theme-element border theme-border hover:border-[var(--color-primario)] transition-colors shrink-0"
                                                onClick={limpiarSeleccionArchivo}
                                                aria-label="Quitar archivo seleccionado"
                                            >
                                                <X className="w-4 h-4 theme-text-muted" aria-hidden />
                                            </button>
                                        </div>
                                    ) : (
                                        <p className="text-sm theme-text-muted m-0 mt-6">Ningún archivo seleccionado</p>
                                    )}
                                </fieldset>

                                {(archivo.errors.archivo || archivo.errors.tipo) && (
                                    <div className="space-y-3 px-1">
                                        {archivo.errors.archivo && <p className="text-sm theme-text-peligro m-0">{archivo.errors.archivo}</p>}
                                        {archivo.errors.tipo && <p className="text-sm theme-text-peligro m-0">{archivo.errors.tipo}</p>}
                                    </div>
                                )}
                            </form>
                        )}

                        {previsualizacion && (
                            <>
                                {previsualizacion.tipo_documento && (
                                    <p className="text-sm theme-text-main m-0">
                                        Tipo de carga: <strong>{etiquetaTipoDocumento(previsualizacion.tipo_documento)}</strong>
                                    </p>
                                )}
                                {previsualizacion.alerta_periodo && (
                                    <div className={`${geliaCardClass('p-4')} text-sm theme-text-main`} role="alert">
                                        <p className="m-0 font-semibold">{previsualizacion.alerta_periodo.mensaje}</p>
                                        {previsualizacion.alerta_periodo.rango_fechas && (
                                            <p className="m-0 mt-2 theme-text-muted">
                                                Rango detectado: {previsualizacion.alerta_periodo.rango_fechas.min} — {previsualizacion.alerta_periodo.rango_fechas.max}
                                            </p>
                                        )}
                                    </div>
                                )}

                                <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 text-sm">
                                    <Contador
                                        etiqueta="Leídas"
                                        valor={previsualizacion.resumen?.leidas}
                                        activo={!filtroResultado && !filtroProblemas}
                                        onClick={() => {
                                            setFiltroResultado('');
                                            setFiltroProblemas(false);
                                        }}
                                    />
                                    <Contador
                                        etiqueta="Nuevas"
                                        valor={previsualizacion.resumen?.nuevas}
                                        activo={filtroResultado === 'alta' && !filtroProblemas}
                                        onClick={() => {
                                            setFiltroResultado('alta');
                                            setFiltroProblemas(false);
                                        }}
                                    />
                                    <Contador
                                        etiqueta="Sin cambios"
                                        valor={previsualizacion.resumen?.sin_cambio}
                                        activo={filtroResultado === 'identico' && !filtroProblemas}
                                        onClick={() => {
                                            setFiltroResultado('identico');
                                            setFiltroProblemas(false);
                                        }}
                                    />
                                    <Contador
                                        etiqueta="Incidencias"
                                        valor={previsualizacion.resumen?.incidencia}
                                        activo={filtroResultado === 'incidencia' && !filtroProblemas}
                                        onClick={() => {
                                            setFiltroResultado('incidencia');
                                            setFiltroProblemas(false);
                                        }}
                                    />
                                </div>

                                <div className="flex flex-col sm:flex-row gap-2">
                                    <input
                                        type="search"
                                        className={THEME_INPUT}
                                        placeholder="Buscar folio o cliente"
                                        aria-label="Buscar por folio o cliente"
                                        value={busquedaFila}
                                        onChange={(e) => setBusquedaFila(e.target.value)}
                                    />
                                    <button
                                        type="button"
                                        className={GELIA_BTN_OUTLINE}
                                        onClick={() => { setFiltroProblemas((v) => !v); setFiltroResultado(''); }}
                                    >
                                        {filtroProblemas ? 'Todas las filas' : 'Solo problemas'}
                                    </button>
                                </div>

                                <div className="overflow-x-auto border theme-border rounded-xl max-h-72">
                                    <table className="w-full text-sm min-w-[800px]">
                                        <thead className="sticky top-0 theme-surface border-b theme-border">
                                            <tr className="text-left theme-text-muted">
                                                <th className="py-2 px-3 font-semibold">Fila</th>
                                                <th className="py-2 px-3 font-semibold">Resultado</th>
                                                <th className="py-2 px-3 font-semibold">Tipo</th>
                                                <th className="py-2 px-3 font-semibold">Folio</th>
                                                <th className="py-2 px-3 font-semibold">Fecha</th>
                                                <th className="py-2 px-3 font-semibold">Cliente</th>
                                                <th className="py-2 px-3 font-semibold text-right">Total</th>
                                                <th className="py-2 px-3 font-semibold">Motivo</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {filasVisibles.map((fila) => (
                                                <tr key={fila.numero_fila} className="border-t theme-border">
                                                    <td className="py-2 px-3">{fila.numero_fila}</td>
                                                    <td className="py-2 px-3">
                                                        <EstadoResultadoImportacion codigo={fila.resultado} />
                                                    </td>
                                                    <td className="py-2 px-3">{etiquetaTipoDocumento(fila.tipo)}</td>
                                                    <td className="py-2 px-3">{fila.folio || '—'}</td>
                                                    <td className="py-2 px-3">{fila.fecha || '—'}</td>
                                                    <td className="py-2 px-3">{[fila.numero_cliente, fila.nombre].filter(Boolean).join(' · ') || '—'}</td>
                                                    <td className="py-2 px-3 text-right tabular-nums">{dinero(fila.total)}</td>
                                                    <td className={`py-2 px-3 max-w-xs ${claseMotivoImportacion(fila.resultado)}`}>
                                                        {fila.motivo || '—'}
                                                    </td>
                                                </tr>
                                            ))}
                                            {filasVisibles.length === 0 && (
                                                <tr className="border-t theme-border">
                                                    <td colSpan={8} className="py-6 px-3 text-center theme-text-muted">
                                                        No hay filas que coincidan con los filtros actuales.
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </>
                        )}
                    </div>

                    <footer className="gelia-modal-footer p-6 md:p-8 pt-5 shrink-0 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div className="text-sm theme-text-muted">
                            {previsualizacion?.motivo_confirmacion && (
                                <span className="block theme-text-main">{previsualizacion.motivo_confirmacion}</span>
                            )}
                            {previsualizacion?.alerta_periodo && !previsualizacion.alerta_periodo.informativa && !puedeConfirmar && (
                                <span className="block mt-1">
                                    <Link href={route('escalonamiento.index')} className="underline">Cambiar archivo</Link>
                                    {' '}o revisar el período destino.
                                </span>
                            )}
                        </div>
                        <div className="flex justify-end gap-2 flex-wrap">
                            <button type="button" className={THEME_BTN_SECONDARY} onClick={cerrarImportacion}>Cancelar</button>
                            {!previsualizacion ? (
                                <button
                                    type="submit"
                                    form="form-archivo-escalonamiento"
                                    className={THEME_BTN_PRIMARY}
                                    disabled={archivo.processing || !archivo.data.archivo}
                                >
                                    Previsualizar
                                </button>
                            ) : (
                                <button
                                    type="button"
                                    className={THEME_BTN_PRIMARY}
                                    disabled={!puedeConfirmar || confirmar.processing}
                                    onClick={enviarConfirmacion}
                                >
                                    {previsualizacion.etiqueta_confirmar || 'Confirmar carga'}
                                </button>
                            )}
                        </div>
                    </footer>
                </div>
            </EscalonamientoModal>

            <EscalonamientoModal abierto={capturar} onClose={cerrarCaptura} labelledBy="titulo-captura-documento">
                <form onSubmit={enviarCaptura} className="p-8 md:p-10 space-y-5">
                    <h2 id="titulo-captura-documento" className={GELIA_MODAL_TITLE}>Registrar documento</h2>
                    <p className="text-sm theme-text-muted m-0">
                        La remisión activa en MXN suma al período si la lista participa. La devolución se guarda sin descontar hasta confirmar su vínculo.
                    </p>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <Campo etiqueta="Tipo" error={captura.errors.tipo}>
                            <select className={THEME_INPUT} value={captura.data.tipo} onChange={(e) => captura.setData('tipo', e.target.value)}>
                                <option value="remision">Remisión</option>
                                <option value="devolucion">Devolución</option>
                            </select>
                        </Campo>
                        <Campo etiqueta="Estado" error={captura.errors.estado}>
                            <select className={THEME_INPUT} value={captura.data.estado} onChange={(e) => captura.setData('estado', e.target.value)}>
                                <option value="activo">Activo</option>
                                <option value="cancelado">Cancelado</option>
                            </select>
                        </Campo>
                        <Campo etiqueta="Folio" error={captura.errors.folio}>
                            <input className={THEME_INPUT} value={captura.data.folio} onChange={(e) => captura.setData('folio', e.target.value)} />
                        </Campo>
                        <Campo etiqueta="Número de cliente" error={captura.errors.numero_cliente}>
                            <input className={THEME_INPUT} value={captura.data.numero_cliente} onChange={(e) => captura.setData('numero_cliente', e.target.value)} />
                        </Campo>
                        <Campo etiqueta="Serie" error={captura.errors.serie}>
                            <input className={THEME_INPUT} value={captura.data.serie} onChange={(e) => captura.setData('serie', e.target.value)} />
                        </Campo>
                        <Campo etiqueta="Sucursal" error={captura.errors.sucursal}>
                            <input className={THEME_INPUT} value={captura.data.sucursal} onChange={(e) => captura.setData('sucursal', e.target.value)} />
                        </Campo>
                        <Campo etiqueta="Total" error={captura.errors.total}>
                            <input className={THEME_INPUT} inputMode="decimal" value={captura.data.total} onChange={(e) => captura.setData('total', e.target.value)} />
                        </Campo>
                        <Campo etiqueta="Moneda" error={captura.errors.moneda}>
                            <input className={THEME_INPUT} value={captura.data.moneda} onChange={(e) => captura.setData('moneda', e.target.value.toUpperCase())} />
                        </Campo>
                        <Campo etiqueta="Fecha" error={captura.errors.fecha_emision}>
                            <input type="date" className={THEME_INPUT} value={captura.data.fecha_emision} onChange={(e) => captura.setData('fecha_emision', e.target.value)} />
                        </Campo>
                        <Campo etiqueta="Remisión original" error={captura.errors.remision_original}>
                            <input className={THEME_INPUT} value={captura.data.remision_original} onChange={(e) => captura.setData('remision_original', e.target.value)} />
                        </Campo>
                    </div>
                    <div className="flex justify-end gap-2">
                        <button type="button" className={THEME_BTN_SECONDARY} onClick={cerrarCaptura}>Cerrar</button>
                        <button type="submit" className={THEME_BTN_PRIMARY} disabled={captura.processing}>Guardar</button>
                    </div>
                </form>
            </EscalonamientoModal>
        </>
    );
}

function EstadoResultadoImportacion({ codigo }) {
    const texto = RESULTADOS_IMPORTACION[codigo] || codigo || '—';
    const tono = GELIA_ESTADO_VIVO_TONO[tonoResultadoImportacion(codigo)] || '';

    return (
        <span className={`gelia-estado-vivo gelia-estado-vivo--compacto inline-flex text-xs font-semibold ${tono}`}>
            {texto}
        </span>
    );
}

function esResultadoProblematico(resultado) {
    if (resultado === 'incidencia') return true;
    if (typeof resultado !== 'string') return false;

    return resultado.includes('revision') || resultado.includes('pendiente');
}

function claseMotivoImportacion(resultado) {
    if (resultado === 'incidencia') return 'theme-text-peligro font-medium';
    if (esResultadoProblematico(resultado)) return 'theme-text-aviso';
    return 'theme-text-muted';
}

function Contador({ etiqueta, valor, activo, onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={activo}
            className={`${geliaCardClass('p-3 text-left')} ${activo ? 'ring-2 ring-[var(--color-primario)]' : ''}`}
        >
            <span className="block text-xs theme-text-muted">{etiqueta}</span>
            <span className="block text-lg font-black theme-text-main tabular-nums">{valor ?? 0}</span>
        </button>
    );
}

function Campo({ etiqueta, error, children }) {
    return (
        <label className="block space-y-1">
            <span className={THEME_LABEL}>{etiqueta}</span>
            {children}
            {error && <span className="block text-xs theme-text-peligro">{error}</span>}
        </label>
    );
}
