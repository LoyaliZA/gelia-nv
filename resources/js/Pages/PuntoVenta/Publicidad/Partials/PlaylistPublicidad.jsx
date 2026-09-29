import React from 'react';
import {
    DndContext,
    KeyboardSensor,
    PointerSensor,
    TouchSensor,
    closestCenter,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    SortableContext,
    arrayMove,
    sortableKeyboardCoordinates,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { Loader2 } from 'lucide-react';
import TarjetaPublicidad from './TarjetaPublicidad';

export default function PlaylistPublicidad({
    items,
    posiciones,
    cargando,
    puedeOrdenar,
    puedeEditar,
    puedeEliminar,
    guardandoOrden,
    onReordenar,
    onToggle,
    onDuracion,
    onEditar,
    onPrevisualizar,
    onEliminar,
    mensajeVacio = 'Aún no hay piezas. La TV mostrará la pantalla institucional.',
}) {
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
        useSensor(TouchSensor, { activationConstraint: { delay: 180, tolerance: 8 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    if (cargando) {
        return (
            <p className="text-sm theme-text-muted inline-flex items-center gap-2">
                <Loader2 className="h-4 w-4 animate-spin" /> Cargando playlist…
            </p>
        );
    }

    if (!items.length) {
        return <p className="text-sm theme-text-muted m-0">{mensajeVacio}</p>;
    }

    const lista = (
        <div className="flex flex-col gap-3">
            <SortableContext items={items.map((item) => item.id)} strategy={verticalListSortingStrategy}>
                {items.map((item) => (
                    <TarjetaPublicidad
                        key={item.id}
                        item={item}
                        posicion={posiciones.get(item.id) || 1}
                        puedeEditar={puedeEditar}
                        puedeEliminar={puedeEliminar}
                        puedeOrdenar={puedeOrdenar && !guardandoOrden}
                        onToggle={onToggle}
                        onDuracion={onDuracion}
                        onEditar={onEditar}
                        onPrevisualizar={onPrevisualizar}
                        onEliminar={onEliminar}
                    />
                ))}
            </SortableContext>
        </div>
    );

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragEnd={({ active, over }) => {
                if (!puedeOrdenar || !over || active.id === over.id || guardandoOrden) return;
                const from = items.findIndex((item) => item.id === active.id);
                const to = items.findIndex((item) => item.id === over.id);
                if (from < 0 || to < 0) return;
                onReordenar(arrayMove(items, from, to));
            }}
        >
            {lista}
        </DndContext>
    );
}
