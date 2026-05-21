import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import {
    MapPin, Navigation, Search, CheckCircle2,
    AlertTriangle, Settings2, Clock, CheckSquare
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import MapaGoogle from '@/Components/Entregas/MapaGoogle';
import ModalConfiguracionLogistica from './Partials/ModalConfiguracionLogistica';

// 1. Agregamos la animación que tienes en tu módulo principal
// para no depender de librerías externas y mantener el estándar.
const ESTILOS_ADICIONALES = `
    @keyframes slideUpFade { 
        0% { opacity: 0; transform: translateY(20px); } 
        100% { opacity: 1; transform: translateY(0); } 
    }
    .animate-page-reveal { 
        opacity: 0; 
        animation: slideUpFade 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards; 
    }
`;

export default function Index({ auth, configuracion, googleApiKey, zonas }) {
    // ----------------------------------------------------------------------
    // ESTADO DEL COMPONENTE Y SEGURIDAD
    // ----------------------------------------------------------------------
    const can = (permiso) => auth?.user?.permissions?.includes(permiso) || auth?.user?.roles?.includes('Super Admin');
    const canConfigurar = can('entregas.configurar_zonas');

    const [coordenadas, setCoordenadas] = useState({ latitud: '', longitud: '' });
    const [direccion, setDireccion] = useState('');
    const [cargando, setCargando] = useState(false);
    const [resultado, setResultado] = useState(null);
    const [modalConfigAbierto, setModalConfigAbierto] = useState(false);
    const [errorMotor, setErrorMotor] = useState(null);

    const actualizarCoordenadasDesdeMapa = (lat, lng) => {
        setCoordenadas({ latitud: lat.toFixed(8), longitud: lng.toFixed(8) });
        // Limpiamos la búsqueda manual al interactuar con el mapa
        setDireccion('');
    };

    // ----------------------------------------------------------------------
    // MANEJADORES DE EVENTOS
    // ----------------------------------------------------------------------
    const handleInputChange = (e) => {
        const { name, value } = e.target;
        setCoordenadas({ ...coordenadas, [name]: value });
    };

    const solicitarCotizacion = async () => {
        if (!coordenadas.latitud || !coordenadas.longitud) {
            setErrorMotor('Ingresa las coordenadas en la barra superior.');
            return;
        }

        setCargando(true);
        setErrorMotor(null);
        setResultado(null);

        try {
            const response = await axios.post('/api/entregas/cotizar', coordenadas);
            setResultado(response.data);
        } catch (error) {
            if (error.response && error.response.data) {
                setErrorMotor(error.response.data.mensaje);
            } else {
                setErrorMotor('Error de conexión con el motor matemático.');
            }
        } finally {
            setCargando(false);
        }
    };

    // ----------------------------------------------------------------------
    // RENDERIZADO DE INTERFAZ
    // ----------------------------------------------------------------------
    return (
        <AppLayout auth={auth}>
            <Head title="Cotización de Entregas" />
            <style>{ESTILOS_ADICIONALES}</style>

            {/* 2. Contenedor Principal: 
                Igualamos el margen y padding máximo que usaste en Solicitudes 
            */}
            <div className="max-w-[1440px] mx-auto p-4 md:p-8 space-y-6 md:space-y-8">

                {/* 3. ENCABEZADO DEL MÓDULO AL ESTILO GELIANV
                    Le damos el fondo theme-surface, los bordes gruesos y la tipografía grande e itálica.
                */}
                <header className="animate-page-reveal theme-surface rounded-3xl md:rounded-[2.5rem] p-6 md:p-12 flex flex-col md:flex-row items-center justify-between gap-4 md:gap-6 border theme-border shadow-xl">
                    <div className="w-full md:w-auto text-center md:text-left">
                        <div className="flex items-center justify-center md:justify-start space-x-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }}></span>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>Panel Logístico</p>
                        </div>
                        <h1 className="text-3xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main m-0">
                            COTIZACIÓN DE <span style={{ color: 'var(--color-primario)' }}>ENTREGAS</span>
                        </h1>
                    </div>
                    {/* En el encabezado, modifica el botón así: */}
                    {canConfigurar && (
                        <button
                            onClick={() => setModalConfigAbierto(true)}
                            className="flex items-center justify-center gap-2 px-8 py-4 text-white rounded-2xl font-black uppercase tracking-widest text-[11px] shadow-xl hover:scale-105 transition-all w-full md:w-auto outline-none"
                            style={{ backgroundColor: 'var(--color-primario)' }}
                        >
                            <Settings2 className="w-5 h-5" /> Configurar Zonas
                        </button>
                    )}
                </header>

                {/* 4. BARRA DE HERRAMIENTAS (Buscador y Coordenadas)
                    Aquí aplicamos los inputs redondeados de gran tamaño (px-12 py-4) para igualar tu buscador.
                */}
                <div className="animate-page-reveal flex flex-col lg:flex-row gap-4 items-center justify-between" style={{ animationDelay: '100ms' }}>

                    {/* Buscador Visual */}
                    <div className="relative w-full flex-1">
                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted" />
                        <input
                            type="text"
                            value={direccion}
                            onChange={(e) => setDireccion(e.target.value)}
                            placeholder="Buscar dirección en el mapa..."
                            className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 transition-all shadow-sm placeholder:text-gray-400"
                        />
                    </div>

                    {/* Controles de Coordenadas y Cotización */}
                    <div className="flex flex-col md:flex-row items-center gap-4 w-full lg:w-auto">
                        <div className="flex items-center gap-2 theme-surface border theme-border rounded-xl px-4 py-4 text-sm shadow-sm w-full md:w-auto font-bold">
                            <Navigation className="w-5 h-5 theme-text-muted" />
                            <input
                                type="number"
                                name="latitud"
                                value={coordenadas.latitud}
                                onChange={handleInputChange}
                                placeholder="Latitud"
                                className="w-24 bg-transparent border-none p-0 focus:ring-0 theme-text-main outline-none placeholder:text-gray-400 text-center font-bold"
                            />
                            <span className="text-gray-300 dark:text-gray-600">|</span>
                            <input
                                type="number"
                                name="longitud"
                                value={coordenadas.longitud}
                                onChange={handleInputChange}
                                placeholder="Longitud"
                                className="w-24 bg-transparent border-none p-0 focus:ring-0 theme-text-main outline-none placeholder:text-gray-400 text-center font-bold"
                            />
                        </div>

                        <button
                            onClick={solicitarCotizacion}
                            disabled={cargando}
                            className="w-full md:w-auto px-8 py-4 rounded-xl text-white font-black uppercase tracking-widest text-[11px] transition-transform hover:scale-105 shadow-md flex justify-center items-center gap-2 outline-none disabled:opacity-70 disabled:cursor-not-allowed"
                            style={{ backgroundColor: 'var(--color-primario)' }}
                        >
                            {cargando ? <Clock className="w-5 h-5 animate-spin" /> : <CheckCircle2 className="w-5 h-5" />}
                            {cargando ? 'Calculando_' : 'Cotizar Envío'}
                        </button>
                    </div>
                </div>

                {/* 5. CUERPO DEL MÓDULO (Resultados y Mapa)
                    Lo encerramos en una caja grande rounded-[2.5rem] idéntica a tu tabla de Solicitudes.
                */}
                <div className="animate-page-reveal theme-surface rounded-[2.5rem] border theme-border shadow-2xl overflow-hidden bg-white/70 dark:bg-[#121212]/70 backdrop-blur-xl flex flex-col-reverse lg:flex-row min-h-[500px]" style={{ animationDelay: '200ms' }}>

                    {/* Panel Lateral: Resultados */}
                    <div className="w-full lg:w-[380px] border-r theme-border p-8 flex flex-col bg-slate-50/50 dark:bg-black/10">
                        <h3 className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-6 flex items-center gap-2">
                            <CheckSquare className="w-4 h-4" /> Resumen Logístico
                        </h3>

                        {/* Estado Vacío */}
                        {!resultado && !errorMotor && (
                            <div className="flex flex-col items-center justify-center text-center h-full opacity-50 space-y-4 py-10 lg:py-0">
                                <MapPin className="w-12 h-12 theme-text-muted" />
                                <p className="text-sm font-bold theme-text-muted px-4">Ubica el pin en el mapa o ingresa coordenadas para iniciar el cálculo.</p>
                            </div>
                        )}

                        {/* Manejo de Errores */}
                        {errorMotor && (
                            <div className="p-4 rounded-2xl border bg-red-50 dark:bg-red-500/5 border-red-200 dark:border-red-500/20 shadow-sm flex items-start gap-3">
                                <AlertTriangle className="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
                                <p className="text-xs font-bold text-red-600 dark:text-red-400 leading-tight">{errorMotor}</p>
                            </div>
                        )}

                        {/* Tarjeta de Cotización Exitosa */}
                        {resultado && (
                            <div className="space-y-6 animate-page-reveal">
                                <div className="theme-surface border theme-border p-6 rounded-3xl shadow-lg relative flex flex-col gap-4">
                                    <div className="absolute left-0 top-0 bottom-0 w-1.5 rounded-l-3xl" style={{ backgroundColor: 'var(--color-primario)' }}></div>

                                    <div>
                                        <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Estado de Área</span>
                                        <div className="mt-1 flex items-center">
                                            <span className="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest flex items-center gap-1.5 w-max bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">
                                                <CheckCircle2 className="w-3.5 h-3.5" /> Aprobada
                                            </span>
                                        </div>
                                    </div>

                                    <div>
                                        <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Zona Asignada</span>
                                        <p className="text-base font-black theme-text-main mt-0.5">{resultado.nombre_zona}</p>
                                    </div>

                                    <div>
                                        <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Distancia Calculada</span>
                                        <p className="text-sm font-bold theme-text-main mt-0.5">{resultado.distancia_km} km</p>
                                    </div>

                                    {/* SECCIÓN DE HORARIOS DISPONIBLES */}
                                    {resultado.horarios && resultado.horarios.length > 0 && (
                                        <div className="pt-4 border-t theme-border mt-2">
                                            <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted flex items-center gap-2 mb-3">
                                                <Clock className="w-3.5 h-3.5" /> Ventanas de Entrega
                                            </span>
                                            <div className="flex flex-col gap-2">
                                                {resultado.horarios.map((horario, index) => (
                                                    <div key={index} className="flex items-center justify-between theme-element border theme-border rounded-xl px-4 py-2">
                                                        <span className="text-xs font-bold theme-text-main">
                                                            {horario.hora_inicio.substring(0, 5)} hrs — {horario.hora_fin.substring(0, 5)} hrs
                                                        </span>
                                                        <div className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                    {/* ------------------------------------ */}

                                    {/* El costo final original se mantiene igual debajo de este bloque */}
                                    <div className="pt-4 border-t theme-border mt-2">
                                        <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Costo Final</span>
                                        <div className="text-4xl font-black italic tracking-tighter mt-1" style={{ color: 'var(--color-primario)' }}>
                                            ${resultado.costo_envio} <span className="text-base font-bold opacity-50 not-italic">MXN</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Área del Mapa */}
                    <div className="flex-1 relative bg-[#e5e5e5] dark:bg-[#0f0f0f] flex items-center justify-center min-h-[400px] lg:min-h-full rounded-r-[2.5rem]">
                        {!googleApiKey ? (
                            <div className="flex flex-col items-center gap-3 opacity-60 p-6 text-center">
                                <AlertTriangle className="w-12 h-12 text-red-500" />
                                <span className="font-black uppercase tracking-widest text-xs theme-text-main text-red-500">
                                    Motor Cartográfico Pausado
                                </span>
                                <p className="text-[10px] theme-text-muted mt-2 font-bold max-w-xs">
                                    Ingresa a la configuración de zonas para establecer la API Key de Google Maps.
                                </p>
                            </div>
                        ) : (
                            <MapaGoogle 
                                key={googleApiKey} // <-- ESTA LÍNEA DESTRUYE Y RECONSTRUYE EL MAPA AL CAMBIAR LA LLAVE
                                apiKey={googleApiKey}
                                coordenadas={coordenadas}
                                configuracion={configuracion}
                                zonas={zonas || []}
                                onCoordenadasChange={actualizarCoordenadasDesdeMapa}
                            />
                        )}
                    </div>
                </div>
            </div>

            {modalConfigAbierto && (
                <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-md animate-page-reveal">
                    <div className="w-full max-w-2xl theme-surface border theme-border rounded-[2rem] shadow-2xl p-6 md:p-8 max-h-[90vh] overflow-y-auto relative">
                        <ModalConfiguracionLogistica
                            configuracion={configuracion}
                            onClose={() => setModalConfigAbierto(false)}
                        />
                    </div>
                </div>
            )}
        </AppLayout>
    );
}