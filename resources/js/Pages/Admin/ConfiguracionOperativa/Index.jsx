import { Head, useForm, usePage } from '@inertiajs/react';
import { Clock, Store, Truck, Save } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass, THEME_INPUT, THEME_BTN_PRIMARY } from '../../../utils/geliaTheme';

const DIAS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

export default function Index({ auth, tienda, general, plazos }) {
    const form = useForm({ tienda, general, plazos });
    const { flash } = usePage().props;
    const cambiar = (grupo, campo, valor) => form.setData(grupo, { ...form.data[grupo], [campo]: valor });
    const error = (grupo, campo) => Object.entries(form.errors).filter(([clave]) => clave === `${grupo}.${campo}` || clave.startsWith(`${grupo}.${campo}.`)).map(([, mensaje]) => mensaje).join(' ');
    const campo = (grupo, nombre, titulo, tipo = 'time', min, max) => (
        <label className="block min-w-0 text-sm theme-text-main" key={`${grupo}.${nombre}`}>
            <span className="block font-medium mb-1">{titulo}</span>
            <input className={`${THEME_INPUT} w-full min-h-11`} type={tipo} required min={min} max={max}
                value={form.data[grupo][nombre]} onChange={e => cambiar(grupo, nombre, e.target.value)} />
            {error(grupo, nombre) && <span className="block text-sm text-red-600 dark:text-red-400 mt-1">{error(grupo, nombre)}</span>}
        </label>
    );
    const dias = (grupo, nombre, titulo) => (
        <fieldset className="min-w-0">
            <legend className="text-sm font-medium theme-text-main mb-2">{titulo}</legend>
            <div className="flex flex-wrap gap-2">
                {DIAS.map((dia, i) => <label key={dia} className="inline-flex items-center gap-2 border theme-border rounded-xl px-3 min-h-11 text-sm theme-text-main">
                    <input type="checkbox" className="gelia-checkbox" checked={form.data[grupo][nombre].includes(i + 1)} onChange={e => cambiar(grupo, nombre, e.target.checked ? [...form.data[grupo][nombre], i + 1].sort((a, b) => a - b) : form.data[grupo][nombre].filter(d => d !== i + 1))} />{dia}
                </label>)}
            </div>
            {error(grupo, nombre) && <p className="text-sm text-red-600 dark:text-red-400">{error(grupo, nombre)}</p>}
        </fieldset>
    );
    const activar = (nombre, titulo) => <label className="flex items-center gap-3 text-sm theme-text-main"><input className="gelia-checkbox" type="checkbox" checked={form.data.plazos[nombre]} onChange={e => cambiar('plazos', nombre, e.target.checked)} />{titulo}</label>;
    const seccion = (Icon, titulo, contenido) => <section className={`${geliaCardClass()} p-5 md:p-6 space-y-5`}><h2 className="flex items-center gap-2 text-lg font-semibold theme-text-main m-0"><Icon size={20} className="text-[var(--color-primario)]" aria-hidden />{titulo}</h2>{contenido}</section>;

    return <AppLayout auth={auth}>
        <Head title="Configuración operativa | GELIANV" />
        <GeliaPageShell className="p-4 md:p-8 space-y-6">
            <header className={`${geliaCardClass()} p-6 md:p-8`}>
                <h1 className="text-2xl md:text-3xl font-semibold theme-text-main m-0">Configuración operativa</h1>
                <p className="text-sm theme-text-muted mt-2 mb-0">Horarios, días hábiles y plazos de pedidos. Las vigencias ya registradas se conservan.</p>
            </header>
            <form className="space-y-5 max-w-5xl" onSubmit={e => { e.preventDefault(); form.put(route('admin.configuracion_operativa.update'), { preserveScroll: true }); }}>
                {seccion(Clock, 'Preparación y recordatorios', <>
                    <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        {campo('general', 'zona_horaria', 'Zona horaria', 'text')}
                        {campo('general', 'recordatorio_hora', 'Hora del recordatorio')}
                        {campo('general', 'cierre_operativo', 'Cierre operativo')}
                        {campo('general', 'dias_resguardo', 'Resguardo general · días', 'number', 1, 90)}
                        {campo('general', 'dias_prorroga_vip', 'Prórroga VIP · días', 'number', 1, 5)}
                    </div>
                </>)}
                {seccion(Store, 'Tienda', <>
                    <div className="space-y-4">
                        <h3 className="text-sm font-semibold theme-text-main m-0">Transferencia · Mismo día</h3>
                        {dias('tienda', 'md_dias', 'Días habilitados para Mismo día')}
                        <div className="max-w-sm">{campo('tienda', 'md_corte', 'Corte del reporte de transferencia')}</div>
                    </div>
                    <div className="border-t theme-border pt-5 space-y-4">
                        <h3 className="text-sm font-semibold theme-text-main m-0">Pago al recoger · vigencia del apartado</h3>
                        {dias('tienda', 'apartado_dias', 'Días de apartado')}
                        <div className="grid sm:grid-cols-3 gap-4">
                            {campo('tienda', 'apartado_cierre', 'Vencimiento ordinario')}
                            {campo('tienda', 'sabado_apertura', 'Apertura del sábado')}
                            {campo('tienda', 'sabado_cierre', 'Vencimiento del sábado')}
                        </div>
                    </div>
                    <div className="border-t theme-border pt-5 space-y-2 max-w-sm">
                        {campo('tienda', 'recoleccion_dias', 'Transferencia · seguimiento de recolección (días naturales)', 'number', 1, 365)}
                        <p className="text-xs theme-text-muted m-0">Desde la remisión. Genera gestión pendiente; conserva la mercancía pagada.</p>
                    </div>
                </>)}
                {seccion(Truck, 'Alertas de retraso', <>
                    {activar('activo', 'Activar alertas de retraso')}
                    {dias('plazos', 'dias_habiles', 'Días hábiles de empaque y recolección')}
                    <div className="grid sm:grid-cols-2 gap-4">
                        {campo('plazos', 'hora_corte', 'Hora de corte de plazos')}
                        {campo('plazos', 'dias_extra_temporada_alta', 'Días extra en temporada alta', 'number', 0, 30)}
                    </div>
                    {activar('temporada_alta', 'Aplicar temporada alta')}
                    <div className="grid sm:grid-cols-2 gap-4">
                        {['comercial', 'local_regional'].map(categoria => <fieldset className="border theme-border rounded-xl p-4 space-y-3" key={categoria}>
                            <legend className="text-sm font-medium theme-text-main">{categoria === 'comercial' ? 'Paquetería comercial' : 'Local y regional'}</legend>
                            {['dias_empaque', 'dias_recoleccion'].map(nombre => <label className="block text-sm theme-text-main" key={nombre}>
                                {nombre === 'dias_empaque' ? 'Días de empaque' : 'Días de recolección'}
                                <input required type="number" min="1" max="30" className={`${THEME_INPUT} w-full mt-1`} value={form.data.plazos[categoria][nombre]} onChange={e => cambiar('plazos', categoria, { ...form.data.plazos[categoria], [nombre]: e.target.value })} />
                                {form.errors[`plazos.${categoria}.${nombre}`] && <span className="text-red-600 dark:text-red-400">{form.errors[`plazos.${categoria}.${nombre}`]}</span>}
                            </label>)}
                        </fieldset>)}
                    </div>
                </>)}
                <div className="flex flex-wrap items-center gap-3">
                    <button type="submit" className={`${THEME_BTN_PRIMARY} min-h-11 inline-flex items-center gap-2`} disabled={form.processing}><Save size={18} />{form.processing ? 'Guardando…' : 'Guardar configuración'}</button>
                    {flash?.success && <p role="status" className="text-sm theme-text-main m-0">{flash.success}</p>}
                    {form.hasErrors && <p role="alert" className="text-sm text-red-600 dark:text-red-400 m-0">Revise los campos indicados.</p>}
                </div>
            </form>
        </GeliaPageShell>
    </AppLayout>;
}
