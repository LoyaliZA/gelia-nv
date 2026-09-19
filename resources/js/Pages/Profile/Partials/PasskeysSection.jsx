import React, { useCallback, useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { Fingerprint, Plus, Trash2, Loader2 } from 'lucide-react';
import WebAuthnService from '../../../Services/WebAuthnService';

export default function PasskeysSection() {
    const { webauthnEnabled } = usePage().props;
    const [passkeys, setPasskeys] = useState([]);
    const [cargando, setCargando] = useState(true);
    const [procesando, setProcesando] = useState(false);
    const [error, setError] = useState('');
    const [exito, setExito] = useState('');

    const soportado = WebAuthnService.soportado();

    const recargar = useCallback(async () => {
        setCargando(true);
        setError('');
        try {
            setPasskeys(await WebAuthnService.listar());
        } catch (e) {
            setError(WebAuthnService.mensajeErrorPasskey(e, 'No se pudieron cargar las passkeys.'));
        } finally {
            setCargando(false);
        }
    }, []);

    useEffect(() => {
        if (webauthnEnabled) {
            recargar();
        } else {
            setCargando(false);
        }
    }, [webauthnEnabled, recargar]);

    if (!webauthnEnabled) {
        return null;
    }

    const registrar = async () => {
        setProcesando(true);
        setError('');
        setExito('');
        try {
            const nickname = window.navigator.userAgentData?.platform
                || window.navigator.platform
                || 'Este equipo';
            await WebAuthnService.registrarPasskey(`Huella en ${nickname}`);
            setExito('Passkey registrada. Ya puedes entrar con huella en este equipo.');
            await recargar();
        } catch (e) {
            setError(WebAuthnService.mensajeErrorPasskey(e, 'No se pudo registrar la passkey.'));
        } finally {
            setProcesando(false);
        }
    };

    const revocar = async (id) => {
        if (!window.confirm('¿Revocar esta passkey? Dejarás de poder entrar con huella en ese dispositivo.')) {
            return;
        }
        setProcesando(true);
        setError('');
        try {
            await WebAuthnService.revocar(id);
            setExito('Passkey revocada.');
            await recargar();
        } catch (e) {
            setError(WebAuthnService.mensajeErrorPasskey(e, 'No se pudo revocar la passkey.'));
        } finally {
            setProcesando(false);
        }
    };

    return (
        <section className="theme-form-zone p-8 md:p-10 space-y-6 rounded-3xl">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div className="flex items-center gap-3">
                    <Fingerprint className="w-6 h-6" style={{ color: 'var(--color-primario)' }} />
                    <div>
                        <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0 drop-shadow-sm">
                            Acceso con huella_
                        </h2>
                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-1 m-0">
                            Passkeys de este usuario
                        </p>
                    </div>
                </div>
                {soportado && (
                    <button
                        type="button"
                        onClick={registrar}
                        disabled={procesando}
                        className="w-full sm:w-auto py-3 px-5 rounded-2xl text-[10px] font-black uppercase tracking-widest theme-btn-primary disabled:opacity-50 outline-none flex items-center justify-center gap-2"
                    >
                        {procesando ? <Loader2 className="w-4 h-4 animate-spin" /> : <Plus className="w-4 h-4" />}
                        Registrar en este equipo
                    </button>
                )}
            </div>

            {!soportado && (
                <p className="text-sm font-bold theme-text-muted m-0">
                    Este navegador no admite WebAuthn. Usa usuario y contraseña.
                </p>
            )}

            {error && <p className="text-sm font-bold text-red-500 m-0" role="alert">{error}</p>}
            {exito && <p className="text-sm font-bold text-green-600 m-0">{exito}</p>}

            {cargando ? (
                <p className="text-sm font-bold theme-text-muted m-0">Cargando passkeys…</p>
            ) : passkeys.length === 0 ? (
                <p className="text-sm font-bold theme-text-muted m-0">
                    Todavía no hay passkeys. Tras iniciar sesión con contraseña, registra la huella de este equipo.
                </p>
            ) : (
                <ul className="space-y-3 m-0 p-0 list-none">
                    {passkeys.map((passkey) => (
                        <li
                            key={passkey.id}
                            className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-2xl border theme-border theme-element"
                        >
                            <div>
                                <p className="text-sm font-black theme-text-main m-0">
                                    {passkey.nickname || 'Passkey'}
                                </p>
                                <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-1 m-0">
                                    {passkey.platform || 'dispositivo'} · registrada {passkey.created_at || '—'}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => revocar(passkey.id)}
                                disabled={procesando}
                                className="self-start sm:self-center py-2 px-4 rounded-xl text-[10px] font-black uppercase tracking-widest text-red-600 border border-red-300 hover:bg-red-50 outline-none flex items-center gap-2"
                            >
                                <Trash2 className="w-4 h-4" />
                                Revocar
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
