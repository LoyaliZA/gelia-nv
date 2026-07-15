# Pase 1 — Análisis de direcciones de envío

Fecha: 2026-07-13  
Alcance: base técnica normalizada sin alterar el origen de `pedidos_bma.domicilio_entrega`.

## 1. Versiones del stack

| Componente | Versión |
|---|---|
| PHP | ^8.3 |
| Laravel | ^13.0 (13.7.0) |
| Inertia (PHP) | ^3.0 |
| React | ^19.2.5 |
| Spatie Permission | ^7.4 |
| PHPUnit | ^12.5 |
| DB producción | MySQL |
| DB tests | SQLite `:memory:` |

## 2. Archivos inspeccionados

- `app/Models/Cliente.php`
- `app/Models/ControlPedidos/PedidoBma.php`
- `app/Support/ControlPedidos/FormatearDomicilioCliente.php`
- `app/Http/Controllers/Api/ClienteApiController.php`
- `app/Services/ControlPedidos/EnviarPedidoBmaService.php`
- `app/Services/ControlPedidos/AprobarPedidoBmaService.php`
- `app/Services/ControlPedidos/LiberarResguardoPedidoBmaService.php`
- `app/Services/ControlPedidos/MarcarEmpacadoPedidoBmaService.php`
- `resources/js/Pages/ControlPedidos/Partials/ModalFormPedido.jsx`
- `resources/js/Pages/Admin/Partials/ModalFormCliente.jsx`
- `resources/js/utils/permisos.js`
- `database/migrations/2026_05_02_191502_create_clientes_table.php`
- `database/migrations/2026_06_10_162129_add_new_fields_to_clientes_table.php`
- `database/migrations/2026_07_09_150000_create_modulo_control_pedidos_tables.php`
- `Implementaciones/Control_pedidos_fase2/pase_1_v2_laravel_modelo_direcciones.md`

## 3. Tablas y columnas reales relevantes

### `clientes` (contacto / entrega heredada)

| Columna | Uso |
|---|---|
| `numero_cliente` | Identidad (string; ordenamiento visual con CAST) |
| `telefono` | Contacto |
| `correo_electronico` | Contacto / fiscal CFDI |
| `direccion_contacto` | Calle/domicilio plano de envío |
| `colonia_contacto` | Colonia |
| `municipio_contacto` | Municipio |
| `estado_contacto` | Estado |
| `pais_contacto` | País |
| `cp_contacto` | CP de envío |
| `codigo_postal` | CP fiscal (no es dirección de envío) |
| `direccion_fiscal` … `pais_fiscal` | Bloque fiscal — fuera de alcance de envío |

### `pedidos_bma`

| Columna | Uso |
|---|---|
| `domicilio_entrega` | Texto libre de domicilio |
| `codigo_postal` | CP del pedido |
| `envia_a_otra_persona` / `envia_otra_persona` | Destinatario distinto |
| `es_resguardo` | Bloqueo operativo CEDIS |

## 4. Relaciones actuales

- `Cliente` → pedidos BMA (`PedidoBma.cliente_id`)
- Sin tablas de direcciones normalizadas
- Autofill pedidos: `GET /api/clientes/id/{id}/direccion-envio` → `FormatearDomicilioCliente`

## 5. Permisos actuales

`clientes.ver`, `clientes.crear`, `clientes.carga_masiva`, `mis_clientes.gestionar`, `clientes.correccion_emergencia`, `clientes.limpieza`, más `control_pedidos.*` (listado, crear, editar, auditar, cedis, delegado, etc.).

Nuevos (este pase): `clientes.direcciones.*` (ver, crear, editar, desactivar, ver_historial, revisar_solicitudes, generar_enlace).

## 6. Dependencias encontradas

- Flujo pedidos depende de texto plano hasta Pase 3
- No existe catálogo postal (SEPOMEX) — captura manual de CP
- Patrón de estados: `public const` en modelos (no PHP enums)
- Servicios: `App\Services\{Dominio}\*Service` con método `ejecutar`
- Permisos: `PermisoCatalogoMigracion::registrar`

## 7. Riesgos

1. Dualidad temporal: pedidos siguen leyendo `*_contacto` hasta Pase 3.
2. Backfill: `direccion_contacto` puede ser texto ambiguo sin componentes separables.
3. `numero_cliente` con ceros a la izquierda no debe castear a int en identidad.
4. SQLite vs MySQL en índices únicos parciales / soft deletes.
5. Una sola dirección principal activa debe garantizarse por servicio + índice práctico.

## 8. Nomenclatura elegida

| Tabla | Modelo |
|---|---|
| `cliente_direcciones` | `App\Models\ClienteDireccion` |
| `solicitudes_direccion` | `App\Models\SolicitudDireccion` |
| `enlaces_direccion` | `App\Models\EnlaceDireccion` |
| `cliente_direccion_auditorias` | `App\Models\ClienteDireccionAuditoria` |

Servicios: `App\Services\Clientes\Direcciones\`

## 9. Matriz de reutilización

| Campo real | Acción | Destino | Compatibilidad | Riesgo |
|---|---|---|---|---|
| `direccion_contacto` | Migrar a normalizado + mantener legado | `cliente_direcciones.calle` (mejor esfuerzo) + conservar columna | Alta | Ambigüedad de parseo |
| `colonia_contacto` | Migrar | `colonia` | Alta | Bajo |
| `municipio_contacto` | Migrar | `municipio` | Alta | Bajo |
| `estado_contacto` | Migrar | `estado` | Alta | Bajo |
| `pais_contacto` | Migrar | `pais` | Alta | Bajo |
| `cp_contacto` | Migrar | `codigo_postal` | Alta | Bajo |
| `telefono` | Reutilizar como destinatario si no hay otro | `telefono_destinatario` | Media | Puede no ser quien recibe |
| `nombre` | Reutilizar como destinatario inicial | `nombre_destinatario` | Media | No siempre es consignatario |
| `*_fiscal` | Fuera de alcance | — | N/A | No mezclar |
| `domicilio_entrega` | Mantener como legado | Snapshot Pase 3 | Alta | No alterar origen aún |
| `FormatearDomicilioCliente` | Reutilizar sin cambios (Pase 1) | — | Alta | Sustituir en Pase 3 |

## 10. Plan de migración

1. Crear tablas nuevas (no destructivo).
2. Registrar permisos.
3. Comando `direcciones:diagnosticar`.
4. Comando `direcciones:migrar-legado --dry-run` / real, idempotente, origen `legacy_migration`.
5. Marcar verificación pendiente cuando falten componentes.
6. No tocar `pedidos_bma` ni UI de pedidos.

## 11. Plan de reversión

1. Desactivar uso de nuevas tablas (no hay consumidores en UI pedidos).
2. `migrate:rollback` de migraciones de direcciones si fuera necesario en entornos no productivos.
3. Conservar datos de `clientes` y `pedidos_bma` intactos.
4. No DELETE de columnas heredadas.

## 12. Diagrama del modelo

```text
clientes 1──* cliente_direcciones
              │
              └── direccion_anterior_id (cadena de versiones)
clientes 1──* solicitudes_direccion
clientes 1──* enlaces_direccion
enlace ──? solicitud (token_publico_id)
dirección ──* auditorías
```
