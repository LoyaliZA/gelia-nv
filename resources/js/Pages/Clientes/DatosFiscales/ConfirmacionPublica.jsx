import React, { useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import { Sun, Moon } from 'lucide-react';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { aplicarTemaPublico, leerTemaPublicoInicial } from '../../../utils/aplicarTemaPublico';

function mensajePorMotivo({ aplicado, ya_utilizado, enlace_invalido, motivo }) {
    if (aplicado || ya_utilizado || motivo === 'usado' || motivo === 'ok') {
        if (ya_utilizado || motivo === 'usado') {
            return {
                titulo: 'Datos ya registrados',
                texto: 'Este enlace ya recibió una respuesta y quedó cerrado. No es necesario volver a enviar. Puede cerrar esta ventana.',
            };
        }
        return {
            titulo: 'Datos fiscales guardados',
            texto: 'Su información fiscal quedó registrada. El enlace se cerró automáticamente. Puede cerrar esta ventana.',
        };
    }

    const mapa = {
        sin_token: 'Para capturar datos fiscales necesita un enlace único enviado por su vendedora.',
        invalido: 'El enlace no es válido. Solicite uno nuevo a su vendedora.',
        expirado: 'Este enlace expiró o fue revocado. Solicite uno nuevo a su vendedora.',
    };

    return {
        titulo: 'Enlace no disponible',
        texto: mapa[motivo] || (enlace_invalido
            ? 'No se puede abrir el formulario sin un enlace válido.'
            : 'No se pudo completar la operación.'),
    };
}

export default function ConfirmacionPublica({
    aplicado = false,
    ya_utilizado = false,
    enlace_invalido = false,
    motivo = null,
    branding = null,
}) {
    const { titulo, texto } = mensajePorMotivo({ aplicado, ya_utilizado, enlace_invalido, motivo });
    const [isDarkMode, setIsDarkMode] = useState(true);

    useEffect(() => {
        const isDark = leerTemaPublicoInicial();
        setIsDarkMode(isDark);
        aplicarTemaPublico(isDark);
    }, []);

    const toggleTheme = () => {
        const next = !isDarkMode;
        setIsDarkMode(next);
        aplicarTemaPublico(next);
    };

    return (
        <div
            className="relative min-h-screen px-4 py-16"
            style={{ backgroundColor: 'var(--bg-app, #0a0a0a)' }}
        >
            <Head title={titulo} />
            <button
                type="button"
                onClick={toggleTheme}
                className="absolute top-4 right-4 md:top-6 md:right-6 z-10 p-2.5 md:p-3 rounded-2xl theme-element border theme-border theme-text-muted hover:text-[var(--color-primario)] transition-all hover:scale-105 outline-none shadow-sm"
                title={isDarkMode ? 'Modo claro' : 'Modo oscuro'}
                aria-label={isDarkMode ? 'Activar modo claro' : 'Activar modo oscuro'}
            >
                {isDarkMode ? <Sun className="w-5 h-5" /> : <Moon className="w-5 h-5" />}
            </button>
            <div className={`mx-auto max-w-xl ${geliaCardClass()} p-8 md:p-10 text-center`}>
                {branding?.url_claro ? (
                    <div className="flex justify-center mb-4">
                        <img
                            src={isDarkMode
                                ? (branding.url_oscuro || branding.url_claro)
                                : branding.url_claro}
                            alt={branding.alt || branding.departamento || 'Logo'}
                            className="h-24 md:h-28 w-auto max-w-[340px] object-contain"
                        />
                    </div>
                ) : (
                    <p className="text-[10px] font-black uppercase tracking-[0.35em] m-0" style={{ color: 'var(--color-primario)' }}>
                        Gelia NV
                    </p>
                )}
                <h1 className="mt-3 text-3xl font-black italic tracking-tighter uppercase theme-text-main m-0">
                    {titulo}
                </h1>
                <p className="mt-3 text-sm theme-text-muted m-0">
                    {texto}
                </p>
            </div>
        </div>
    );
}
