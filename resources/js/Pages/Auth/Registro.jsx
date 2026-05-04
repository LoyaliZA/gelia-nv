import React, { useEffect, useRef } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { animate, createScope } from 'animejs';
import { UserPlus, Upload, ShieldCheck } from 'lucide-react';

export default function Registro({ rol_asignado, catalogos }) {
    const root = useRef(null);
    const scope = useRef(null);

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        apellido_paterno: '',
        apellido_materno: '',
        username: '',
        email: '',
        password: '',
        telefono: '',
        edad: '',
        catalogo_sexo_id: '',
        rol_asignado: rol_asignado, // Viene de la URL firmada
        foto_perfil: null,
    });

    useEffect(() => {
        scope.current = createScope({ root }).add(() => {
            animate('.form-container', {
                translateY: [30, 0],
                opacity: [0, 1],
                easing: 'outExpo',
                duration: 1200,
            });
        });
        return () => scope.current.revert();
    }, []);

    const handleSubmit = (e) => {
        e.preventDefault();
        // Al enviar archivos, Inertia automáticamente usa FormData si hay un File en el estado
        post(route('registro.store', { rol: rol_asignado }));
    };

    return (
        <div ref={root} className="min-h-screen flex flex-col items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
            <Head title="Registro de Colaborador - GELIA" />

            <div className="form-container max-w-2xl w-full bg-white p-10 rounded-3xl shadow-sm border border-gray-200" style={{ opacity: 0 }}>
                <div className="text-center mb-8">
                    <div className="mx-auto w-16 h-16 bg-black rounded-full flex items-center justify-center mb-4">
                        <UserPlus className="w-8 h-8 text-white" />
                    </div>
                    <h2 className="text-3xl font-light text-gray-900">Registro en GELIA</h2>
                    <p className="mt-2 text-sm text-gray-600">
                        Estás siendo registrado con el rol de: <strong className="text-black uppercase">{rol_asignado}</strong>
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Nombre(s)</label>
                            <input type="text" value={data.name || ''} onChange={e => setData('name', e.target.value)} required className="mt-1 w-full p-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-1 focus:border-black" />
                            {errors.name && <p className="text-red-500 text-xs mt-1">{errors.name}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Apellido Paterno</label>
                            <input type="text" value={data.apellido_paterno || ''} onChange={e => setData('apellido_paterno', e.target.value)} required className="mt-1 w-full p-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-1 focus:border-black" />
                            {errors.apellido_paterno && <p className="text-red-500 text-xs mt-1">{errors.apellido_paterno}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Apellido Materno</label>
                            <input type="text" value={data.apellido_materno || ''} onChange={e => setData('apellido_materno', e.target.value)} className="mt-1 w-full p-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-1 focus:border-black" />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Nombre de Usuario</label>
                            <input type="text" value={data.username || ''} onChange={e => setData('username', e.target.value)} required className="mt-1 w-full p-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-1 focus:border-black" />
                            {errors.username && <p className="text-red-500 text-xs mt-1">{errors.username}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Correo Electrónico</label>
                            <input type="email" value={data.email || ''} onChange={e => setData('email', e.target.value)} required className="mt-1 w-full p-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-1 focus:border-black" />
                            {errors.email && <p className="text-red-500 text-xs mt-1">{errors.email}</p>}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Teléfono</label>
                            <input type="text" value={data.telefono || ''} onChange={e => setData('telefono', e.target.value)} className="mt-1 w-full p-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-1 focus:border-black" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Edad</label>
                            <input type="number" min="18" value={data.edad || ''} onChange={e => setData('edad', e.target.value)} className="mt-1 w-full p-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-1 focus:border-black" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Sexo</label>
                            <select value={data.catalogo_sexo_id || ''} onChange={e => setData('catalogo_sexo_id', e.target.value)} required className="mt-1 w-full p-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-1 focus:border-black">
                                <option value="">Seleccione...</option>
                                {catalogos.sexos.map(sexo => (
                                    <option key={sexo.id} value={sexo.id}>{sexo.nombre}</option>
                                ))}
                            </select>
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700">Contraseña Segura</label>
                        <input type="password" value={data.password || ''} onChange={e => setData('password', e.target.value)} required placeholder="Mínimo 8 caracteres, 1 mayúscula, 1 número" className="mt-1 w-full p-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-1 focus:border-black" />
                        {errors.password && <p className="text-red-500 text-xs mt-1">{errors.password}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700">Foto de Perfil (Opcional)</label>
                        <div className="mt-1 flex items-center">
                            <label className="flex items-center justify-center w-full px-4 py-6 bg-gray-50 border-2 border-gray-300 border-dashed rounded-xl cursor-pointer hover:border-black transition-colors">
                                <div className="flex flex-col items-center">
                                    <Upload className="w-6 h-6 text-gray-400 mb-2" />
                                    <span className="text-sm text-gray-500">{data.foto_perfil ? data.foto_perfil.name : 'Haz clic para seleccionar una imagen'}</span>
                                </div>
                                <input type="file" className="hidden" accept="image/*" onChange={e => setData('foto_perfil', e.target.files[0])} />
                            </label>
                        </div>
                    </div>

                    <button type="submit" disabled={processing} className="w-full flex justify-center items-center py-4 px-4 border border-transparent rounded-xl shadow-sm text-sm font-medium text-white bg-black hover:bg-gray-800 focus:outline-none disabled:opacity-50 transition-colors">
                        {processing ? 'Registrando...' : <><ShieldCheck className="w-5 h-5 mr-2"/> Completar Registro</>}
                    </button>
                </form>
            </div>
            <footer className="mt-12 text-center text-sm text-gray-400">
                <p>&copy; {new Date().getFullYear()} GELIA.</p>
                <p className="mt-1">Creado por Gabriel Zárate.</p>
            </footer>
        </div>
    );
}