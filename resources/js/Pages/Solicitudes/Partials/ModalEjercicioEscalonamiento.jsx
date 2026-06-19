import React from 'react';
import { createPortal } from 'react-dom';
import { Link } from '@inertiajs/react';
import { X, Calculator, ExternalLink } from 'lucide-react';
import { geliaCardClass } from '@/utils/geliaTheme';
import EjercicioEscalonamientoPanel from '@/Components/EjercicioEscalonamiento/EjercicioEscalonamientoPanel';

export default function ModalEjercicioEscalonamiento({ onClose, listas }) {
    return createPortal(
        <div
            className="fixed inset-0 z-[100] flex items-center justify-center p-4 md:p-8 bg-black/60 backdrop-blur-md animate-fade-in"
            onClick={onClose}
        >
            <div
                className={`${geliaCardClass('w-full max-w-6xl max-h-[90vh] flex flex-col overflow-hidden shadow-2xl')} animate-fade-in`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-center justify-between gap-4 p-5 md:p-6 border-b theme-border shrink-0">
                    <div className="flex items-center gap-3 min-w-0">
                        <div className="w-10 h-10 rounded-xl flex items-center justify-center bg-violet-500/10 border border-violet-500/20 shrink-0">
                            <Calculator className="w-5 h-5 text-violet-500" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-[10px] font-black uppercase tracking-[0.25em] theme-text-muted m-0">Herramientas</p>
                            <h2 className="text-lg md:text-xl font-black theme-text-main uppercase tracking-tight m-0 truncate">
                                Ejercicio Escalonamiento
                            </h2>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="p-2.5 rounded-xl border theme-border theme-element theme-text-muted hover:text-red-500 hover:border-red-500/40 transition-all shrink-0"
                        aria-label="Cerrar"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <div className="flex-1 min-h-0 overflow-y-auto custom-scrollbar p-5 md:p-6">
                    <EjercicioEscalonamientoPanel listas={listas} variant="modal" />
                </div>

                <div className="flex items-center justify-end gap-3 p-4 md:p-5 border-t theme-border shrink-0">
                    <Link
                        href={route('ejercicio_escalonamiento.index')}
                        className="inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl border theme-border theme-element theme-text-main text-[10px] font-black uppercase tracking-widest hover:border-[var(--color-primario)] hover:text-[var(--color-primario)] transition-all"
                    >
                        <ExternalLink className="w-4 h-4 shrink-0" />
                        Abrir vista completa
                    </Link>
                </div>
            </div>
        </div>,
        document.body
    );
}
