# MIG-05 · Inventario de jobs y webhooks

Pendiente de completar en MIG-05.

Entrada: [MIG-01-inventario-endpoints.md](./MIG-01-inventario-endpoints.md).

## Jobs y comandos

| Componente | Cola | API version config | Lector | Escritor | Artefacto contrato |
|--------------|------|-------------------|--------|----------|-------------------|
| `SyncTiendanubeCatalogoJob` | | | MIG-02 | — | |
| `ProcessTiendanubeWebhook` | | | MIG-02 | — | |
| `ProcessTiendanubeImageImportJob` | | | — | MIG-04 | |
| `tiendanube:auditar-imagenes` | — | N/A (CDN) | — | — | fuera API TN |

## Webhooks

| Evento | Handler | GET follow-up | Registrado remoto |
|--------|---------|---------------|-------------------|
| `product/updated` | | `/products/{id}` | |

## Diagnóstico (sin secretos)

| Campo expuesto | Fuente |
|----------------|--------|

**Usado por:** MIG-06 [MIG-06-evidencia-integracion.md](./MIG-06-evidencia-integracion.md).
