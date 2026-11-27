# SDD ledger — plan: docs/superpowers/plans/2026-11-27-multienvio-nacional-fases-2-4.md
Rulings heredados: sin commits; sin worktree (develop en sitio); pruebas vía rsync + docker exec gelianv-laravel.test-1; MySQL: gelianv-mysql-1/gelianv solo filas sintéticas; un implementador y una revisión por bloque (petición del usuario: menos fases, urgente).
Mapas de integración (Ventas/Auditar/CEDIS/Delegado) recibidos 27-nov-2026.
## Bloques
A1 base 938a0d2ee87d5dc5a669773d9e48221dcb77f376
A1: implementada (DONE): resumen por lote, VisibilidadMiembrosMultienvio compartido, cajas_origen/cajas_resumen/pendiente/saf_aplicado; 189 passed regresión. Decisiones: n_total solo visibles, parcial sin conteo; 'multienvio' leído de getAttributes (colisión con relación); /listado ya es N+1 previo. Revisión en el cierre del bloque A.
A4: DONE (sin cambios de producción; números reales == spec 65.00/63.40/61.60; 206 passed). A2/A3: DONE_WITH_CONCERNS (497 passed/2 failed preexistentes modalElegibilidadCedisPdv; sin verificación visual; docs UI pendientes; borrador vacío guarda 0).
Aviso para Fase B: la co-desarrolladora edita en el mismo árbol (Delegado: PedidoBmaDelegadoController, ModalDetalleDelegado, TablaDelegado, Delegado/Index.jsx; Direcciones/enlaces; nginx/docker; NacionalLogisticaCobroTest). Antes de B3 revisar git status/diff de esos archivos y construir encima sin pisar. Build npm run build OK en gelianv-laravel.test-1 (publica en /home/ti-admin/gelianv/public/build; public/build de produccion-test es root y está gitignored: publicar allí es paso de despliegue del usuario).
