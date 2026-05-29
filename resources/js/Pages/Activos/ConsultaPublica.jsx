import React, { useEffect, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Edit2, User } from 'lucide-react';
import GeliaLogo from '../../Components/GeliaLogo';
import DynamicActivoFields from './Partials/DynamicActivoFields';
import LightboxFotos from './Partials/LightboxFotos';
import { ESTADO_BADGE, ESTADO_LABELS, METADATA_BADGE, CHIP_BADGE, getActivosCardClass } from './Partials/activosFormStyles';

function IdentificacionChips({ atributos = {} }) {
    const chips = [
        atributos.marca && { label: 'Marca', value: atributos.marca },
        atributos.modelo && { label: 'Modelo', value: atributos.modelo },
        atributos.serial && { label: 'Serial', value: atributos.serial },
    ].filter(Boolean);

    if (!chips.length) return null;

    return (
        <div className="flex flex-wrap gap-2 mt-3">
            {chips.map((chip) => (
                <span key={chip.label} className={CHIP_BADGE}>
                    <span className="opacity-70">{chip.label}: </span>
                    {chip.value}
                </span>
            ))}
        </div>
    );
}

export default function ConsultaPublica({ activo, puedeEditar = false, urlEditar = null }) {
    const [lightboxIndex, setLightboxIndex] = useState(null);

    const fotos = (activo.fotos || [])
        .map((f) => f.url)
        .filter(Boolean);

    const fotoPrincipal = activo.foto_principal?.url || fotos[0] || null;

    useEffect(() => {
        const savedTheme = localStorage.getItem('theme');
        const systemPrefersDark = window.matchMedia?.('(prefers-color-scheme: dark)')?.matches;
        const isDark = savedTheme === 'dark' || (!savedTheme && systemPrefersDark);
        document.documentElement.classList.toggle('dark', isDark);
    }, []);

    const abrirLightbox = (index = 0) => {
        if (!fotos.length) return;
        setLightboxIndex(index);
    };

    return (
        <>
            <Head title={`${activo.folio} | Consulta`} />

            <div className="min-h-dvh theme-bg flex flex-col items-center p-4 sm:p-8">
                <div className="w-full max-w-lg space-y-6 animate-page-reveal">
                    <div className="flex justify-center pt-2">
                        <GeliaLogo variant="sparkle" className="w-14 h-14" />
                    </div>

                    <div className={getActivosCardClass('p-5 sm:p-6 space-y-4')}>
                        {fotoPrincipal ? (
                            <button
                                type="button"
                                onClick={() => abrirLightbox(Math.max(0, fotos.indexOf(fotoPrincipal)))}
                                className="w-full aspect-video rounded-2xl overflow-hidden border theme-border bg-black/5"
                            >
                                <img src={fotoPrincipal} alt={activo.nombre} className="w-full h-full object-cover" />
                            </button>
                        ) : (
                            <div className="w-full aspect-video rounded-2xl border-2 border-dashed theme-border flex items-center justify-center theme-text-muted text-xs font-black uppercase">
                                Sin fotografía
                            </div>
                        )}

                        <div>
                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">{activo.folio}</p>
                            <h1 className="text-xl sm:text-2xl font-black italic uppercase theme-text-main mt-1">{activo.nombre}</h1>
                            <div className="flex flex-wrap gap-2 mt-3">
                                <span className={`px-2 py-1 rounded-lg text-[10px] font-black uppercase ${ESTADO_BADGE[activo.estado] || ''}`}>
                                    {ESTADO_LABELS[activo.estado] || activo.estado}
                                </span>
                                {activo.tipo?.nombre && (
                                    <span className={METADATA_BADGE}>{activo.tipo.nombre}</span>
                                )}
                                {activo.departamento?.nombre && (
                                    <span className={METADATA_BADGE}>{activo.departamento.nombre}</span>
                                )}
                            </div>
                            <IdentificacionChips atributos={activo.atributos} />
                        </div>

                        <div className="border-t theme-border pt-4">
                            <h2 className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Pertenece a</h2>
                            {activo.responsable ? (
                                <div className="flex items-center gap-3 rounded-xl p-3 theme-element border theme-border">
                                    <User className="w-6 h-6 shrink-0" style={{ color: 'var(--color-primario)' }} />
                                    <p className="text-sm font-black theme-text-main m-0">{activo.responsable.name}</p>
                                </div>
                            ) : (
                                <p className="text-sm theme-text-muted italic m-0">Sin asignar</p>
                            )}
                        </div>

                        {activo.esquema_atributos?.fields?.length > 0 && (
                            <div className="border-t theme-border pt-4">
                                <h2 className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-3">Detalle</h2>
                                <DynamicActivoFields
                                    fields={activo.esquema_atributos.fields}
                                    values={activo.atributos || {}}
                                    onChange={() => {}}
                                    readOnly
                                />
                            </div>
                        )}
                    </div>

                    <p className="text-center text-[10px] theme-text-muted font-bold uppercase tracking-wider">
                        Consulta pública · Solo lectura
                    </p>
                </div>
            </div>

            {puedeEditar && urlEditar && (
                <div className="fixed bottom-0 inset-x-0 p-4 pb-[max(1rem,env(safe-area-inset-bottom))] z-50 pointer-events-none">
                    <Link
                        href={urlEditar}
                        className="pointer-events-auto flex items-center justify-center gap-2 w-full max-w-lg mx-auto min-h-[48px] rounded-2xl text-white font-black uppercase text-xs shadow-lg"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        <Edit2 className="w-4 h-4" />
                        Ir a editar
                    </Link>
                </div>
            )}

            {lightboxIndex !== null && fotos.length > 0 && (
                <LightboxFotos
                    fotos={fotos}
                    indiceInicial={lightboxIndex}
                    onCerrar={() => setLightboxIndex(null)}
                />
            )}
        </>
    );
}
