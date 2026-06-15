import React from 'react';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { Download, FileSpreadsheet, Info } from 'lucide-react';

export default function PanelInstruccionesExportacion() {
    return (
        <div className={`${geliaCardClass()} p-6 md:p-8 border-l-4`} style={{ borderColor: 'var(--color-primario)' }}>
            <div className="flex items-center gap-2 mb-4">
                <Info className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                <h3 className="text-sm font-black uppercase tracking-widest theme-text-main">Instrucciones de Exportación</h3>
            </div>

            <div className="space-y-6 text-xs theme-text-muted">
                <section>
                    <h4 className="font-black uppercase theme-text-main mb-2 flex items-center gap-2">
                        <span className="w-2 h-4 rounded" style={{ backgroundColor: 'var(--color-primario)' }} />
                        1. CSV de Productos WooCommerce (obligatorio inicial)
                    </h4>
                    <p className="mb-2">Exporta desde <strong className="theme-text-main">WooCommerce → Productos → Exportar</strong> con estas columnas:</p>
                    <div className="overflow-x-auto rounded-xl border theme-border">
                        <table className="w-full text-[10px]">
                            <thead><tr className="font-black uppercase theme-text-muted border-b theme-border">
                                <th className="p-2 text-left">Columna</th><th className="p-2 text-left">Woo (EN)</th><th className="p-2 text-left">Uso</th>
                            </tr></thead>
                            <tbody className="font-mono">
                                <tr className="border-b theme-border/50"><td className="p-2">ID</td><td className="p-2">ID</td><td className="p-2">ID WooCommerce</td></tr>
                                <tr className="border-b theme-border/50"><td className="p-2">SKU</td><td className="p-2">SKU</td><td className="p-2">Cruce con Wizerp</td></tr>
                                <tr className="border-b theme-border/50"><td className="p-2">Nombre</td><td className="p-2">Name</td><td className="p-2">Visualización</td></tr>
                                <tr className="border-b theme-border/50"><td className="p-2">Tipo</td><td className="p-2">Type</td><td className="p-2">simple / variation</td></tr>
                                <tr><td className="p-2">Superior</td><td className="p-2">Parent / Parent SKU</td><td className="p-2">SKU padre (variaciones)</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <a href="/templates/woocommerce-estructura-ejemplo.csv" download
                        className="inline-flex items-center gap-2 mt-3 text-[10px] font-black uppercase tracking-widest hover:underline"
                        style={{ color: 'var(--color-primario)' }}>
                        <Download className="w-3 h-3" /> Descargar plantilla CSV de ejemplo
                    </a>
                </section>

                <section>
                    <h4 className="font-black uppercase theme-text-main mb-2 flex items-center gap-2">
                        <span className="w-2 h-4 rounded bg-purple-500" />
                        2. Excel Wizerp (Plantilla de Resurtido)
                    </h4>
                    <p>No es export de WooCommerce. Debe contener cabeceras. Columna <strong className="theme-text-main">SKU</strong> y columna <strong className="theme-text-main">Plataforma</strong> (precio base).</p>
                </section>

                <section>
                    <h4 className="font-black uppercase theme-text-main mb-2 flex items-center gap-2">
                        <span className="w-2 h-4 rounded bg-emerald-500" />
                        3. CSV de precios GELIANV (opcional)
                    </h4>
                    <p>Generado con &quot;Generar CSV&quot;: columnas <strong className="theme-text-main">SKU, Precio normal, Precio rebajado</strong> para actualizar precios locales sin API.</p>
                </section>

                <section className="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-700 dark:text-amber-400 font-bold">
                    Orden: Configurar API → Importar CSV Woo → (opcional) Fetch precios → Subir Excel Wizerp → Previsualizar → Sincronizar
                </section>
            </div>
        </div>
    );
}
