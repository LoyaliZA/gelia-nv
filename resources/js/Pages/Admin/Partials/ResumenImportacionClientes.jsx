import { AlertTriangle, CheckCircle2, FileSpreadsheet } from 'lucide-react';

export default function ResumenImportacionClientes({ resultado }) {
    if (!resultado) return null;
    const { stats, estado, errores_detalle = [], detalle_limitado } = resultado;
    const Icono = estado === 'completo' ? CheckCircle2 : AlertTriangle;
    const metricas = [
        ['Leídas', stats.leidas], ['Creados', stats.creados], ['Actualizados', stats.actualizados],
        ['Sin cambios', stats.sin_cambios], ['Omitidas', stats.omitidas], ['Fallidas', stats.errores],
        ['Activados', stats.activados], ['Inactivados', stats.clientes_marcados_inactivos], ['Cambios de lista', stats.cambios_lista],
    ];
    return (
        <section aria-label="Resultado de importación" role="status" className="theme-surface-solid theme-no-blur border theme-border rounded-xl p-4 mb-4 space-y-4">
            <div className="flex items-center gap-3">
                <FileSpreadsheet className="w-5 h-5 theme-text-muted shrink-0" />
                <h2 className="font-bold theme-text-main m-0 flex-1">Resultado de importación</h2>
                <span className="flex items-center gap-2 text-sm theme-text-main"><Icono className="w-4 h-4 shrink-0" />{estado === 'completo' ? 'Completa' : estado === 'parcial' ? 'Parcial' : 'Fallida'}</span>
            </div>
            <dl className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 m-0">
                {metricas.map(([etiqueta, valor]) => (
                    <div key={etiqueta} className="theme-element border theme-border rounded-lg p-3">
                        <dt className="text-xs theme-text-muted">{etiqueta}</dt>
                        <dd className="text-lg font-bold theme-text-main m-0">{valor ?? 0}</dd>
                    </div>
                ))}
            </dl>
            {errores_detalle.length > 0 && (
                <div className="border-t theme-border pt-3 space-y-2">
                    <p className="text-sm font-bold theme-text-main">Filas sin aplicar</p>
                    {errores_detalle.map((error, i) => (
                        <p key={i} className="text-sm theme-text-main break-words m-0">
                            <strong>Fila {error.numero_fila}</strong> · Cliente {error.numero_cliente || 'sin número'} · {error.campo}: {error.mensaje}
                        </p>
                    ))}
                    {detalle_limitado && <p className="text-xs theme-text-muted">Se muestran los primeros 100 errores. El detalle completo está en la auditoría de la carga.</p>}
                </div>
            )}
        </section>
    );
}
