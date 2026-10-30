import { HeadphonesIcon } from '@animateicons/react/lucide/headphones-icon';
import { PauseIcon } from '@animateicons/react/lucide/pause-icon';
import { RotateCcwIcon } from '@animateicons/react/lucide/rotate-ccw-icon';
import { TimerIcon } from '@animateicons/react/lucide/timer-icon';
import { UserCheckIcon } from '@animateicons/react/lucide/user-check-icon';
import { UsersIcon } from '@animateicons/react/lucide/users-icon';
import { geliaCardClass } from '../../../../utils/geliaTheme';

const COLOR_TONO = {
    exito: 'var(--color-exito)',
    aviso: 'var(--color-aviso)',
    info: 'var(--color-info)',
    atendiendo: 'var(--color-equipo-atendiendo)',
    pausa: 'var(--color-equipo-pausa)',
    neutro: 'var(--theme-text-muted)',
};

function Metrica({ icon: Icono, etiqueta, valor, tono = 'neutro' }) {
    const color = COLOR_TONO[tono] || COLOR_TONO.neutro;

    return (
        <div className="flex min-w-[11rem] flex-1 items-stretch gap-2.5">
            <span className="w-1 shrink-0 rounded-full" style={{ backgroundColor: color }} aria-hidden />
            <div className="min-w-0">
                <div className="flex items-center gap-2.5">
                    <Icono size={20} color={color} className="shrink-0" />
                    <p className="m-0 text-sm font-semibold leading-snug theme-text-muted">{etiqueta}</p>
                </div>
                <p className="m-0 mt-1.5 text-3xl font-bold tabular-nums leading-none theme-text-main">{valor}</p>
            </div>
        </div>
    );
}

function Grupo({ titulo, children }) {
    return (
        <div className="min-w-0 flex-1">
            <h3 className="m-0 text-sm font-bold theme-text-main">{titulo}</h3>
            <div className="mt-4 flex flex-wrap gap-x-5 gap-y-4">
                {children}
            </div>
        </div>
    );
}

export default function EncabezadoGestionVendedores({
    resumen = {},
    clientesEnFila = 0,
    clientesReatencion = 0,
}) {
    const {
        configurados = 0,
        activos_hoy: activosHoy = 0,
        disponibles = 0,
        atendiendo = 0,
        pre_disponibles: preDisponibles = 0,
        en_pausa: enPausa = 0,
    } = resumen;

    const resumenHablado = `${clientesEnFila} clientes en fila, ${clientesReatencion} en reatención, ${disponibles} disponibles, ${atendiendo} atendiendo, ${preDisponibles} pre-disponibles, ${enPausa} en pausa.`;

    return (
        <section
            className={`${geliaCardClass()} space-y-4 p-4 sm:p-5`}
            aria-labelledby="gestion-vendedores-resumen"
        >
            <div className="flex flex-wrap items-center gap-3">
                <UsersIcon size={24} className="shrink-0 theme-text-main" />
                <h2 id="gestion-vendedores-resumen" className="m-0 text-base font-bold theme-text-main">
                    Resumen del equipo
                </h2>
            </div>

            <p className="sr-only" role="status">{resumenHablado}</p>

            <div className="flex flex-col gap-4 xl:flex-row xl:items-start">
                <Grupo titulo="Ahora">
                    <Metrica icon={UsersIcon} etiqueta="Clientes en fila" valor={clientesEnFila} tono="info" />
                    <Metrica icon={RotateCcwIcon} etiqueta="Reatención" valor={clientesReatencion} tono="neutro" />
                </Grupo>
                <span className="hidden h-auto w-px self-stretch xl:block" style={{ backgroundColor: 'var(--theme-border)' }} aria-hidden />
                <Grupo titulo="Equipo">
                    <Metrica icon={UserCheckIcon} etiqueta="Disponibles" valor={disponibles} tono="exito" />
                    <Metrica icon={HeadphonesIcon} etiqueta="Atendiendo" valor={atendiendo} tono="atendiendo" />
                    <Metrica icon={TimerIcon} etiqueta="Pre-disponibles" valor={preDisponibles} tono="aviso" />
                    <Metrica icon={PauseIcon} etiqueta="En pausa" valor={enPausa} tono="pausa" />
                </Grupo>
            </div>

            <p className="m-0 text-sm font-semibold theme-text-muted">
                {configurados} configurados · {activosHoy} con jornada hoy
            </p>
        </section>
    );
}
