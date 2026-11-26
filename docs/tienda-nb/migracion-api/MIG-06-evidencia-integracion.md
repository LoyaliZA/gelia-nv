# MIG-06 · Evidencia de integración

Pendiente de completar en MIG-06. Consolidar artefactos MIG-01…05.

## Matriz por operación

| Operación | Endpoint / flujo | Evidencia (test/ensayo) | Artefacto fuente | Estado |
|-----------|------------------|-------------------------|------------------|--------|
| Lectura store | GET `/store` | | MIG-01 | |
| Sync categorías | GET `/categories` | | MIG-02 comparacion | |
| Sync productos | GET `/products` | | MIG-02 comparacion | |
| Stock por ubicación | MIG-03 | | MIG-03 modelo | |
| Crear producto | POST `/products` | | MIG-04 payloads | |
| Webhook product/updated | inbox + GET | | MIG-05 inventario | |

## Pendientes críticos abiertos

| ID | Descripción | Origen | Aceptado para corte |
|----|-------------|--------|---------------------|
| | | MIG-01 matriz | no |

## Pruebas automatizadas

| Suite | Comando | Resultado | Fecha |
|-------|---------|-----------|-------|
| Tiendanube | `phpunit --filter=Tiendanube` | | |

**Entrada para:** [MIG-06-registro-cierre.md](./MIG-06-registro-cierre.md).
