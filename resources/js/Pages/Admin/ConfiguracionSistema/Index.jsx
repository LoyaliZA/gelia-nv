import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Settings, Plus, Edit2, Trash2, Mail, Check, X, AlertCircle, Shield } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import { geliaCardClass, THEME_INPUT, THEME_SELECT, THEME_TEXTAREA, THEME_BTN_PRIMARY, THEME_BTN_SECONDARY, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../utils/geliaTheme';

const SimpleModal = ({ show, onClose, title, children }) => {
    if (!show) return null;
    return (
        <div className={THEME_MODAL_OVERLAY} onClick={onClose}>
            <div className={`${THEME_MODAL_SHELL} max-w-lg w-full mx-4`} onClick={e => e.stopPropagation()}>
                <div className="p-6 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center bg-white dark:bg-gray-900 rounded-t-xl">
                    <h2 className="text-xl font-bold theme-text-main">{title}</h2>
                    <button type="button" onClick={onClose} className="p-2 hover:bg-black/5 dark:hover:bg-white/5 rounded-full"><X className="w-5 h-5"/></button>
                </div>
                <div className="p-6 bg-white dark:bg-gray-900 rounded-b-xl max-h-[80vh] overflow-y-auto">{children}</div>
            </div>
        </div>
    );
};

export default function ConfiguracionSistema({ auth, configuracionesGrupos, configuracionesRaw, sessionDriver = 'database' }) {
    const activeCardClass = geliaCardClass('relative z-10');
    
    const [modalConfig, setModalConfig] = useState(false);
    const [isEditing, setIsEditing] = useState(false);
    const [modalTestMail, setModalTestMail] = useState(false);

    const { data, setData, post, put, delete: destroy, processing, errors, reset } = useForm({
        id: null,
        clave: '',
        valor: '',
        tipo: 'string',
        descripcion: '',
        grupo: '',
    });

    const { data: dataMail, setData: setDataMail, post: postMail, processing: processingMail, reset: resetMail, errors: errorsMail } = useForm({
        email: auth.user.email || '',
    });

    const openModalCreate = () => {
        setIsEditing(false);
        reset();
        setModalConfig(true);
    };

    const openModalEdit = (configuracion) => {
        setIsEditing(true);
        setData({
            id: configuracion.id,
            clave: configuracion.clave,
            valor: configuracion.valor || '',
            tipo: configuracion.tipo,
            descripcion: configuracion.descripcion || '',
            grupo: configuracion.grupo || '',
        });
        setModalConfig(true);
    };

    const openModalTestMail = () => {
        resetMail();
        setDataMail('email', auth.user.email || '');
        setModalTestMail(true);
    };

    const submitConfig = (e) => {
        e.preventDefault();
        if (isEditing) {
            put(route('admin.configuracion_sistema.update', data.id), {
                onSuccess: () => {
                    setModalConfig(false);
                    reset();
                }
            });
        } else {
            post(route('admin.configuracion_sistema.store'), {
                onSuccess: () => {
                    setModalConfig(false);
                    reset();
                }
            });
        }
    };

    const handleDelete = (id) => {
        if (confirm('¿Estás seguro de eliminar esta configuración? Esto podría romper el sistema si es requerida.')) {
            destroy(route('admin.configuracion_sistema.destroy', id));
        }
    };

    const submitTestMail = (e) => {
        e.preventDefault();
        postMail(route('admin.configuracion_sistema.test_mail'), {
            onSuccess: () => {
                setModalTestMail(false);
            }
        });
    };

    const etiquetaSesion = (clave) => {
        const map = {
            'session.lifetime': 'Tiempo de inactividad (minutos)',
            'session.expire_on_close': 'Expirar al cerrar navegador',
            'sesiones.jornada_cierre_activo': 'Cierre automático al fin de jornada',
            'sesiones.jornada_hora_fin': 'Hora de fin de jornada',
            'sesiones.jornada_zona_horaria': 'Zona horaria de jornada',
        };
        return map[clave] || clave;
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Configuración del Sistema | GELIANV" />

            <div className="max-w-[1200px] w-full mx-auto p-4 md:p-8 space-y-8 relative">
                <header className={`${activeCardClass} p-8 md:p-12 flex flex-col md:flex-row justify-between items-start md:items-center gap-6`}>
                    <div className="space-y-3">
                        <div className="flex items-center gap-3">
                            <div className="w-8 h-1.5 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }} />
                            <span className="text-[10px] font-black tracking-[0.2em] uppercase theme-text-muted drop-shadow-sm">
                                Core del Sistema_
                            </span>
                        </div>
                        <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main leading-none m-0">
                            Variables <span style={{ color: 'var(--color-primario)' }}>Globales</span>
                        </h1>
                        <p className="text-sm theme-text-muted flex items-center gap-2">
                            <Settings className="w-4 h-4" />
                            Sobreescritura de .env en tiempo de ejecución
                        </p>
                    </div>

                    <div className="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                        <button onClick={openModalTestMail} type="button" className={`${THEME_BTN_SECONDARY} flex items-center gap-2 w-full sm:w-auto justify-center`}>
                            <Mail className="w-4 h-4" /> Probar Mail
                        </button>
                        <button onClick={openModalCreate} type="button" className={`${THEME_BTN_PRIMARY} flex items-center gap-2 w-full sm:w-auto justify-center`}>
                            <Plus className="w-4 h-4" /> Agregar Variable
                        </button>
                    </div>
                </header>

                <div className={`${activeCardClass} p-6 flex items-start gap-4 border border-amber-500/20 bg-amber-500/5`}>
                    <Shield className="w-6 h-6 text-amber-600 shrink-0 mt-0.5" />
                    <div>
                        <h3 className="text-sm font-black uppercase tracking-widest theme-text-main">Driver de sesiones (solo lectura)</h3>
                        <p className="text-sm theme-text-muted mt-1">
                            El almacenamiento de sesiones se define en <code className="text-xs">SESSION_DRIVER</code> del archivo <code className="text-xs">.env</code>.
                            Valor actual: <strong className="font-mono">{sessionDriver}</strong>.
                            {sessionDriver !== 'database' && (
                                <span className="block mt-1 text-amber-600 font-bold">
                                    Para habilitar el listado de sesiones y la auditoría de accesos, configure SESSION_DRIVER=database en producción.
                                </span>
                            )}
                        </p>
                    </div>
                </div>

                <div className="space-y-8">
                    {Object.keys(configuracionesGrupos).length === 0 ? (
                        <div className={`${activeCardClass} p-12 text-center text-gray-500`}>
                            <AlertCircle className="w-12 h-12 mx-auto mb-4 opacity-50" />
                            <h3 className="text-xl font-bold mb-2 theme-text-main">Sin configuraciones</h3>
                            <p>No se ha registrado ninguna variable global aún.</p>
                        </div>
                    ) : (
                        Object.keys(configuracionesGrupos).map((grupoName) => (
                            <section key={grupoName} className={`${activeCardClass} overflow-hidden`}>
                                <div className="p-6 border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-black/20">
                                    <h2 className="text-xl font-black tracking-tight theme-text-main flex items-center gap-2">
                                        <div className="w-2 h-6 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }} />
                                        {grupoName || 'GENERAL'}
                                    </h2>
                                </div>
                                
                                <div className="divide-y divide-gray-100 dark:divide-gray-800">
                                    {configuracionesGrupos[grupoName].map((config) => (
                                        <div key={config.id} className="p-6 hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                                            <div className="flex-1 min-w-0">
                                                <div className="flex items-center gap-3 mb-1">
                                                    <span className="text-lg font-bold font-mono text-gray-800 dark:text-gray-200 truncate">
                                                        {grupoName === 'Sesiones' ? etiquetaSesion(config.clave) : config.clave}
                                                    </span>
                                                    <span className="px-2 py-0.5 text-xs rounded-full bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300 font-bold uppercase tracking-wider">
                                                        {config.tipo}
                                                    </span>
                                                </div>
                                                <p className="text-sm text-gray-500 dark:text-gray-400 mb-2">{config.descripcion}</p>
                                                
                                                <div className="p-3 bg-gray-50 dark:bg-black/40 rounded-xl border border-gray-100 dark:border-gray-800 break-all font-mono text-sm text-gray-700 dark:text-gray-300">
                                                    {config.tipo === 'boolean' ? (
                                                        (config.valor === 'true' || config.valor === '1' || config.valor === true) ? (
                                                            <span className="flex items-center gap-1 text-green-600 dark:text-green-400 font-bold"><Check className="w-4 h-4"/> TRUE</span>
                                                        ) : (
                                                            <span className="flex items-center gap-1 text-red-600 dark:text-red-400 font-bold"><X className="w-4 h-4"/> FALSE</span>
                                                        )
                                                    ) : (
                                                        config.tipo === 'string' && config.clave.toLowerCase().includes('password') ? '••••••••' : config.valor || <em className="text-gray-400">Nulo/Vacío</em>
                                                    )}
                                                </div>
                                            </div>

                                            <div className="flex items-center gap-2 shrink-0">
                                                <button type="button" onClick={() => openModalEdit(config)} className="p-2 rounded-xl text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors" title="Editar">
                                                    <Edit2 className="w-5 h-5" />
                                                </button>
                                                <button type="button" onClick={() => handleDelete(config.id)} className="p-2 rounded-xl text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors" title="Eliminar">
                                                    <Trash2 className="w-5 h-5" />
                                                </button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </section>
                        ))
                    )}
                </div>
            </div>

            {/* Modal Crear/Editar Variable */}
            <SimpleModal
                show={modalConfig}
                onClose={() => setModalConfig(false)}
                title={isEditing ? 'Editar Variable Global' : 'Nueva Variable Global'}
            >
                <form onSubmit={submitConfig} className="space-y-4">
                    <div>
                        <label className="block text-sm font-bold theme-text-main mb-1">Clave (Formato con puntos, ej. mail.host)</label>
                        <input
                            type="text"
                            value={data.clave}
                            onChange={e => setData('clave', e.target.value)}
                            className={THEME_INPUT}
                            disabled={isEditing}
                            required
                        />
                        {errors.clave && <p className="text-red-500 text-xs mt-1">{errors.clave}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-bold theme-text-main mb-1">Grupo (Ej. Mail, General, WebPush)</label>
                        <input
                            type="text"
                            value={data.grupo}
                            onChange={e => setData('grupo', e.target.value)}
                            className={THEME_INPUT}
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-bold theme-text-main mb-1">Tipo de Dato</label>
                        <select
                            value={data.tipo}
                            onChange={e => setData('tipo', e.target.value)}
                            className={THEME_SELECT}
                        >
                            <option value="string">String (Texto)</option>
                            <option value="integer">Integer (Número)</option>
                            <option value="boolean">Boolean (Verdadero/Falso)</option>
                            <option value="json">JSON</option>
                        </select>
                    </div>

                    <div>
                        <label className="block text-sm font-bold theme-text-main mb-1">Valor</label>
                        {data.tipo === 'boolean' ? (
                            <select
                                value={data.valor}
                                onChange={e => setData('valor', e.target.value)}
                                className={THEME_SELECT}
                            >
                                <option value="true">True (Verdadero)</option>
                                <option value="false">False (Falso)</option>
                            </select>
                        ) : (
                            <textarea
                                value={data.valor}
                                onChange={e => setData('valor', e.target.value)}
                                className={THEME_TEXTAREA}
                                rows={3}
                            />
                        )}
                        {errors.valor && <p className="text-red-500 text-xs mt-1">{errors.valor}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-bold theme-text-main mb-1">Descripción (Opcional)</label>
                        <input
                            type="text"
                            value={data.descripcion}
                            onChange={e => setData('descripcion', e.target.value)}
                            className={THEME_INPUT}
                        />
                    </div>

                    <div className="flex justify-end gap-3 pt-4 mt-6">
                        <button type="button" onClick={() => setModalConfig(false)} className={THEME_BTN_SECONDARY}>Cancelar</button>
                        <button type="submit" disabled={processing} className={THEME_BTN_PRIMARY}>{isEditing ? 'Guardar Cambios' : 'Crear Variable'}</button>
                    </div>
                </form>
            </SimpleModal>

            {/* Modal Test Mail */}
            <SimpleModal
                show={modalTestMail}
                onClose={() => setModalTestMail(false)}
                title="Probar Configuración de Correo"
            >
                <form onSubmit={submitTestMail} className="space-y-4">
                    <p className="text-sm theme-text-muted mb-4">
                        Se enviará un correo de prueba utilizando la configuración actual (o la global si ya sobreescribió).
                    </p>

                    <div>
                        <label className="block text-sm font-bold theme-text-main mb-1">Correo Electrónico Destino</label>
                        <input
                            type="email"
                            value={dataMail.email}
                            onChange={e => setDataMail('email', e.target.value)}
                            className={THEME_INPUT}
                            required
                        />
                        {errorsMail.email && <p className="text-red-500 text-xs mt-1">{errorsMail.email}</p>}
                    </div>

                    <div className="flex justify-end gap-3 pt-4 mt-6">
                        <button type="button" onClick={() => setModalTestMail(false)} className={THEME_BTN_SECONDARY}>Cancelar</button>
                        <button type="submit" disabled={processingMail} className={THEME_BTN_PRIMARY}>Enviar Prueba</button>
                    </div>
                </form>
            </SimpleModal>
        </AppLayout>
    );
}
