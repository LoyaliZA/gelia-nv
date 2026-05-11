import React from 'react';
import { Head, useForm } from '@inertiajs/react';

export default function Login() {
    // Declaración estricta de las variables de estado para evitar inputs no controlados
    const { data, setData, post, processing, errors } = useForm({
        login: '',
        password: '',
        remember: true, 
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/login');
    };

    return (
        <div className="min-h-screen flex flex-col items-center justify-center bg-gray-50 text-gray-900 p-6">
            <Head title="Iniciar Sesión - GELIA" />

            {/* Cabecera institucional con logotipos */}
            <div className="flex items-center justify-center gap-8 mb-10">
                <img 
                    src="/Images/Logos/aromas_logo_negro.png" 
                    alt="Logo Aromas" 
                    className="h-20 md:h-24 object-contain" 
                />
                <div className="h-20 md:h-24 w-px bg-gray-300"></div> {/* Divisor vertical ajustado */}
                <img 
                    src="/Images/Logos/bellaroma_logo_negro.png" 
                    alt="Logo Bellaroma" 
                    className="h-20 md:h-24 object-contain" 
                />
            </div>

            {/* Contenedor del formulario */}
            <div className="max-w-md w-full bg-white p-8 rounded-3xl shadow-sm border border-gray-200">
                <div className="mb-8 text-center">
                    <h1 className="text-3xl font-light tracking-tight text-black mb-2">Bienvenido a GELIA</h1>
                    <p className="text-gray-500 text-sm">Ingresa con tu correo, usuario o nombre.</p>
                </div>
                
                <form onSubmit={handleSubmit} className="space-y-5">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">Usuario, Correo o Nombre</label>
                        {/* Se implementa fallback a string vacío para mantener el input controlado */}
                        <input 
                            type="text" 
                            value={data.login || ''}
                            onChange={e => setData('login', e.target.value)}
                            className="w-full p-3.5 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-1 focus:border-black transition-colors"
                            required
                        />
                        {errors.login && <p className="text-red-500 text-xs mt-1">{errors.login}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">Contraseña</label>
                        <input 
                            type="password" 
                            value={data.password || ''}
                            onChange={e => setData('password', e.target.value)}
                            className="w-full p-3.5 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-1 focus:border-black transition-colors"
                            required
                        />
                        {errors.password && <p className="text-red-500 text-xs mt-1">{errors.password}</p>}
                    </div>

                    <button 
                        type="submit" 
                        disabled={processing}
                        className="w-full py-3.5 bg-black text-white rounded-xl font-medium transition-colors flex justify-center items-center mt-6 disabled:opacity-50"
                    >
                        {processing ? 'Verificando...' : 'Iniciar Sesión'}
                    </button>
                </form>
            </div>

            {/* Pie de página con créditos */}
            <footer className="mt-12 text-center text-sm text-gray-400">
                <p>&copy; {new Date().getFullYear()} GELIA.</p>
                <p className="mt-1">Creado por Gabriel Zárate.</p>
            </footer>
        </div>
    );
}