# Pase 1 — Informe de cierre

Fecha: 2026-07-13

## Implementado

- Informe de análisis: `docs/control-pedidos/pase-1-analisis-direcciones.md`
- Tablas: `cliente_direcciones`, `enlaces_direccion`, `solicitudes_direccion`, `cliente_direccion_auditorias`
- Modelos y relaciones en `Cliente`
- Servicios: Normalizador, Versionador, DetectorDuplicados, Identidad, Auditoría, Gestión (contrato Pase 2/3), Sync contacto, Enlaces
- Comandos: `direcciones:diagnosticar`, `direcciones:migrar-legado`
- Permisos Spatie `clientes.direcciones.*`
- Formateador `FormatearDireccionEstructurada`
- Dual-write preparado (activado al marcar principal/verificar)
- Origen de `pedidos_bma.domicilio_entrega` **sin cambios**

## Pruebas

`GestionDireccionesPase1Test`: 8 passed (Sail PHP 8.5).

## Contrato técnico para Pases 2–3

`GestionDireccionesClienteService`:

- `listarActivasVerificadasPorCliente`
- `obtenerParaSnapshot`
- `crearPrimeraDireccion` / `crearDireccionAdicional` / `crearNuevaVersion`
- `marcarComoPrincipal` / `sincronizarDireccionPrincipalConContacto`
- `detectarPosiblesDuplicados` / `formatearDireccionEstructurada`

## Riesgos pendientes

- Backfill ambigüo deja verificación `pending`
- Dual-write completo de pantallas queda para Pase 2
- UI pública y pedidos aún no consumen el modelo
