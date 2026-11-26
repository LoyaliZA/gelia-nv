# MIG-05 · Procedimiento de pausa y drenaje

Pendiente de completar en MIG-05. MIG-06 **ejecuta** este procedimiento en el corte.

## Antes del cambio de versión API

1. Pausar workers de cola Tiendanube (_comando/documentar_)
2. Permitir recepción webhook (bandeja durable)
3. Drenar jobs compatibles con versión actual

## Jobs encolados incompatibles

| Job type | Versión payload | Acción |
|----------|-----------------|--------|
| | | drenar / marcar / reprocesar |

## Reinicio post-config 2025-03

1. `config:cache` (_según despliegue_)
2. Reiniciar queue workers
3. Lectura de control (probe MIG-01)
4. Sync completo con depuración apagada ([MIG-02-comparacion-sync.md](./MIG-02-comparacion-sync.md))

## Rollback parcial

| Síntoma | Acción | No hacer |
|---------|--------|----------|
| Escritura stock plano | modo lectura | purgar catálogo |

**Usado por:** MIG-06 [MIG-06-registro-cierre.md](./MIG-06-registro-cierre.md).
