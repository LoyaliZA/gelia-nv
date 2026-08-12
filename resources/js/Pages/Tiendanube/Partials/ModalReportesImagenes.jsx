import React, { useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { Download, FileSpreadsheet, X } from 'lucide-react';

function Check({ checked, onChange, children }) {
    return (
        <label className="flex items-center gap-2 text-xs font-bold theme-text-main cursor-pointer">
            <input
                type="checkbox"
                checked={checked}
                onChange={(e) => onChange(e.target.checked)}
                className="rounded border theme-border"
            />
            {children}
        </label>
    );
}

function Seccion({ titulo, descripcion, children }) {
    return (
        <section className="space-y-3 rounded-2xl border theme-border p-4 bg-black/[0.02] dark:bg-white/[0.02]">
            <div>
                <h3 className="text-[11px] font-black uppercase tracking-widest theme-text-main m-0">{titulo}</h3>
                {descripcion && <p className="text-[11px] theme-text-muted mt-1 mb-0">{descripcion}</p>}
            </div>
            {children}
        </section>
    );
}

function BtnDescarga({ href, disabled, label = 'Descargar CSV' }) {
    if (disabled || !href) {
        return (
            <span className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest theme-text-muted opacity-50 border theme-border">
                <Download className="w-3.5 h-3.5" /> {label}
            </span>
        );
    }

    return (
        <a
            href={href}
            className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest text-white"
            style={{ backgroundColor: 'var(--color-primario)' }}
        >
            <Download className="w-3.5 h-3.5" /> {label}
        </a>
    );
}

export default function ModalReportesImagenes({
    open,
    onClose,
    importId = null,
    alertasDimension = 0,
    fallidos = 0,
}) {
    const [alertaPequena, setAlertaPequena] = useState(true);
    const [alertaNoCuadrada, setAlertaNoCuadrada] = useState(true);
    const [sinFotoPub, setSinFotoPub] = useState(true);
    const [sinFotoNoPub, setSinFotoNoPub] = useState(true);
    const [importPequena, setImportPequena] = useState(true);
    const [importNoCuadrada, setImportNoCuadrada] = useState(true);

    const hrefAlertas = useMemo(() => {
        const detalle = [];
        if (alertaPequena) detalle.push('pequena');
        if (alertaNoCuadrada) detalle.push('no_cuadrada');
        if (detalle.length === 0) return null;
        return route('tiendanube.imagenes.reporte_alertas', { detalle });
    }, [alertaPequena, alertaNoCuadrada]);

    const hrefSinFoto = useMemo(() => {
        if (!sinFotoPub && !sinFotoNoPub) return null;
        const params = {};
        if (sinFotoPub && !sinFotoNoPub) params.publicado = 1;
        if (!sinFotoPub && sinFotoNoPub) params.publicado = 0;
        return route('tiendanube.imagenes.reporte_sin_foto', params);
    }, [sinFotoPub, sinFotoNoPub]);

    const hrefImportDim = useMemo(() => {
        if (importId == null) return null;
        const detalle = [];
        if (importPequena) detalle.push('pequena');
        if (importNoCuadrada) detalle.push('no_cuadrada');
        if (detalle.length === 0) return null;
        return route('tiendanube.imagenes.importar.reporte_dimensiones', { id: importId, detalle });
    }, [importId, importPequena, importNoCuadrada]);

    if (!open || typeof document === 'undefined') return null;

    const mostrarImport = importId != null;

    return createPortal(
        <div
            className="fixed inset-0 z-[210] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md"
            onClick={onClose}
            role="presentation"
        >
            <div
                className="w-full max-w-lg max-h-[90vh] overflow-y-auto theme-surface border theme-border rounded-[2rem] p-6 md:p-8 space-y-5 relative shadow-2xl"
                onClick={(e) => e.stopPropagation()}
                role="dialog"
                aria-modal="true"
                aria-labelledby="modal-reportes-imagenes-titulo"
            >
                <button
                    type="button"
                    onClick={onClose}
                    className="absolute top-5 right-5 p-2 theme-text-muted hover:theme-text-main"
                    aria-label="Cerrar"
                >
                    <X className="w-5 h-5" />
                </button>

                <div className="flex items-center gap-3 pr-8">
                    <FileSpreadsheet className="w-8 h-8 shrink-0" style={{ color: 'var(--color-primario)' }} />
                    <div>
                        <h2
                            id="modal-reportes-imagenes-titulo"
                            className="text-lg font-black italic uppercase theme-text-main m-0"
                        >
                            Reportes CSV
                        </h2>
                        <p className="text-xs theme-text-muted mt-1 m-0">
                            Elegí el tipo de reporte y los detalles a incluir.
                        </p>
                    </div>
                </div>

                <Seccion
                    titulo="Imágenes a revisar"
                    descripcion="Catálogo con alertas de medida o proporción."
                >
                    <div className="flex flex-col gap-2">
                        <Check checked={alertaPequena} onChange={setAlertaPequena}>
                            lado menor &lt; 800px
                        </Check>
                        <Check checked={alertaNoCuadrada} onChange={setAlertaNoCuadrada}>
                            no cuadrada
                        </Check>
                    </div>
                    <BtnDescarga href={hrefAlertas} disabled={!hrefAlertas} />
                </Seccion>

                <Seccion titulo="Sin imagen" descripcion="Productos del catálogo sin ninguna foto.">
                    <div className="flex flex-col gap-2">
                        <Check checked={sinFotoPub} onChange={setSinFotoPub}>
                            publicado
                        </Check>
                        <Check checked={sinFotoNoPub} onChange={setSinFotoNoPub}>
                            no publicado
                        </Check>
                    </div>
                    <BtnDescarga href={hrefSinFoto} disabled={!hrefSinFoto} />
                </Seccion>

                {mostrarImport && (
                    <Seccion
                        titulo={`Importación #${importId}`}
                        descripcion="Reportes de la carga actual o reciente."
                    >
                        <div className="space-y-3">
                            <div className="space-y-2">
                                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">
                                    Dimensiones
                                    {alertasDimension > 0 ? ` · ${alertasDimension} alerta(s)` : ''}
                                </p>
                                <div className="flex flex-col gap-2">
                                    <Check checked={importPequena} onChange={setImportPequena}>
                                        lado menor &lt; 800px
                                    </Check>
                                    <Check checked={importNoCuadrada} onChange={setImportNoCuadrada}>
                                        no cuadrada
                                    </Check>
                                </div>
                                <BtnDescarga href={hrefImportDim} disabled={!hrefImportDim} />
                            </div>
                            {fallidos > 0 && (
                                <div className="space-y-2 pt-2 border-t theme-border">
                                    <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">
                                        Errores de carga · {fallidos}
                                    </p>
                                    <BtnDescarga
                                        href={route('tiendanube.imagenes.importar.reporte', importId)}
                                        label="Errores CSV"
                                    />
                                </div>
                            )}
                        </div>
                    </Seccion>
                )}
            </div>
        </div>,
        document.body
    );
}
