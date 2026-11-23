# Anexo extraordinario (minipedido relacionado) — diseño, primera entrega Nacional

Fecha: 23 de noviembre de 2026 · Rama `develop` · Estado: pendiente de aprobación del usuario. Decisión del usuario (23-nov): **el anexo sale en caja y guía aparte**; no se reemite la guía del principal.
Origen: auditoría de Nacional 2.0 (`2026-11-23-nacional-auditoria-informe.md`, hallazgos NAC-021 y G-01 de Ola 4) y la explicación operativa que dio el usuario.

## Intención y por qué

Cuando un pedido ya tiene pago o remisión y está por salir, el cliente a veces pide **agregar piezas**. Hoy el sistema rechaza el ajuste (candado de pago) y el complemento nacional existe solo como un panel escondido dentro del formulario principal. En la operación real, la vendedora recibe la petición primero y trata el anexo como un **minipedido relacionado con el pedido principal**, con su propia consulta a quien empaca (Tienda o CEDIS), su propio pago y su propia remisión. El retiro de piezas en esta etapa es muy raro y queda **fuera** de este diseño.

Meta del usuario: no volver a llenar un formulario completo; hacer un miniproceso, «hasta como una mini consulta».

Contrato de negocio: guía V3.1 §6 N-F04, §7.1 «Anexo con remisión», T-06/M-03/N-06 (complemento relacionado y pagado), AC-05, AC-06, invariantes §1.1.3 (Ventas no valida su propio ingreso) y §1.1.10 (cierre condicionado).

## Metas

- Un **modal corto «Anexo extraordinario»** disponible en un pedido nacional pagado o remisionado, antes o después de salir. Con el principal ya en tránsito el anexo es simplemente un envío nuevo (V3.1 N-F04).
- Datos heredados del principal (cliente, almacén de salida, paquetería, dirección y destinatario); se captura solo lo del anexo: piezas (SKU o captura/documento por pieza) y una nota para la auxiliar.
- El anexo nace como **pedido hijo** vinculado (`pedido_principal_id`) y pasa por una **mini consulta** a CEDIS, cierre de Ventas, cotización de su propia caja, pago propio verificado por la auxiliar, remisión propia, **guía propia del Delegado** y salida de CEDIS. Nada del principal se reemite.
- Identificación inequívoca de cada pieza (resuelve G-01: hoy se manda una pieza genérica «Complemento»).
- En todas las bandejas el anexo se ve **distinto** de la consulta original: etiqueta «Anexo» y enlace «Anexo de {folio principal}».
- No modifica la consulta ni los datos del pedido principal más allá de la relación y de la barrera de integración.

## No metas

- No retiro de piezas extraordinario (se difiere por rareza).
- No Tienda ni Municipal en esta entrega; el modal queda diseñado para conectarlos después con un adaptador por modalidad.
- No motor nuevo común de minipedido (se reutiliza `RegistrarComplementoNacional`, la consulta nacional y la integración de CEDIS).
- No cobrar dos veces mercancía ya pagada; no mutar la remisión del principal.
- No rediseñar el formulario principal ni el pesaje de CEDIS.

## Anti-metas

- Que Ventas pueda marcar un anexo como pagado o remisionado por sí misma.
- Que el anexo salga sin pago verificado, sin guía propia vigente o sin que CEDIS confirme disponibilidad y peso.
- Que el anexo invalide o frene la guía o la salida del principal.
- Que se rompa el flujo ordinario de Nacional o las modalidades Tienda y Municipal.

## Restricciones

- AGENTS.md: comandos por Sail, pruebas dirigidas y aisladas, `npm run build` solo al publicar un lote, sin commit/push sin pedirlo.
- Interfaz según `GUIA_DISENO_GELIA.md`, `.ux-profile.md` y `GUIA_EVIDENCIA_VISUAL.md`; 375 px y 1280 px; reutilizar `CamposDireccionPedido`, galería de evidencias y estilos de `pedidosBmaStyles.js`.
- Permisos: todo permiso nuevo va en migración y `permisos_actuales.php` (memoria del proyecto).
- Sin pagos, guías ni mensajería reales en pruebas.

## Qué existe hoy (base de la decisión)

- `RegistrarComplementoNacional::ejecutar` crea el hijo en BORRADOR con `pedido_principal_id` (si aún no salió), copia cliente, origen, almacén y paquetería, y registra piezas con evidencia. **Defectos a corregir:** fija `pago_validado_at = now()` y `pago_validado_por_id` del creador, usa `folio_remision = 'WIZ-'.uniqid()` y toma el importe tecleado sin consulta (`:80-105`).
- `ResponderPiezasComplementoNacional` deja que CEDIS responda cantidad y estado por pieza sin escribir en el principal.
- `BarreraRecepcionComplementoNacional` impide empacar, guiar y sacar mientras el vínculo no esté recibido e integrado.
- UI actual: panel «Agregar mercancía al pedido pagado» en `FormularioRegistrarNacional.jsx:529`, con importe y un solo archivo; CEDIS responde desde `ModalDetalleCedis.jsx`.
- Rutas existentes: `POST cedis/{pedido}/responder-complemento-nacional`, `custodia-nacional`, `consulta-nacional`, `cerrar-consulta`.

## Enfoques considerados

- **A.** Una puerta única que invoca el complemento existente de cada modalidad. Pequeño, pero deja tres mecanismos por dentro.
- **B.** Motor nuevo común de minipedido. Limpio, pero toca Tienda y Municipal (hoy en verde) y duplica motores.
- **C. (elegido por el usuario)** Primera entrega solo Nacional, con el modal y el contrato diseñados para que las otras modalidades se conecten después con un adaptador. Riesgo bajo y ataca el flujo hoy roto.

## Diseño

### Flujo (decisión del usuario + V3.1)

1. **Vendedora.** En un pedido nacional pagado o remisionado, abre **«Anexo extraordinario»**: piezas (SKU o captura/documento por pieza, cantidad) y nota para la auxiliar. Se crea el hijo `ANEXO` (folio derivado del principal) en estado de consulta.
2. **Mini consulta a CEDIS.** Misma mecánica de la consulta nacional (disponibilidad por pieza, evidencia y peso/caja de las piezas nuevas, porque Nacional exige peso), acotada al anexo y marcada «Anexo». Ventas informa al cliente y **cierra** la mini consulta (regla de cierre existente).
3. **Cotización de su caja.** La continuación nacional existente se reutiliza para el hijo: flete de **su** caja, seguro y reexpedición opcionales, dirección heredada del principal (sin recapturar).
4. **Pago propio.** Ventas reporta la exhibición del anexo; la **auxiliar verifica** y tramita la **remisión propia** (folio real de Wizerp, no inventado). Se elimina la validación automática de pago.
5. **Guía y salida propias.** El Delegado captura la guía del anexo; CEDIS empaca el anexo en **su caja** y lo marca enviado con las barreras ya existentes (guía vigente, cobertura, crédito). Puede viajar con el principal o después: son dos guías.
6. El **principal no espera ni se modifica**: su guía, su caja y su salida siguen su curso. Solo se guarda la relación en ambos pedidos.

### Interfaz

```
Pedido PBMA-0058 (pagado, por salir)  ──►  [ Anexo extraordinario ]

┌ Anexo extraordinario · PBMA-0058 ───────────────┐
│ Hereda: Cliente · CEDIS · DHL · dirección       │
│ Piezas del anexo        [+ SKU]  [+ Captura]    │
│  • SKU-123 ×2                         [quitar]  │
│  • Captura-1.png (sin SKU) ×1         [quitar]  │
│ Nota para la auxiliar  [__________________]    │
│            [Cancelar]  [Enviar a consulta]      │
└─────────────────────────────────────────────────┘
```

- Etiqueta **«Anexo»** y enlace «Anexo de {folio}» en listados, tarjetas de CEDIS y bandeja del auxiliar, con color y texto propios (no solo color).
- Modal compartido en un componente nuevo (`Nacional/ModalAnexoExtraordinario.jsx`) que reutiliza el editor de piezas y la galería de evidencias; contrato de entrada por modalidad (adaptador) para conectar Tienda y Municipal después.

### Contrato del adaptador por modalidad (para fases siguientes)

`crearAnexo(pedidoPrincipal, piezas, nota) → pedidoHijo`, `consultaDelAnexo(hijo)`, `puedeSolicitar(principal) → motivo|null`. Nacional implementa los tres sobre `RegistrarComplementoNacional` y, para la consulta del hijo, sobre la consulta nacional existente.

### Reglas

- Solo con pago validado o remisión en el principal (antes o después de salir); sin remisión ni pago se sigue usando el ajuste ordinario. Principal cancelado: no se permite.
- Idempotencia por `solicitud_id` (ya existe); versión de captura del principal (`revision_esperada`).
- Permiso para solicitar: el mismo que crear/editar pedidos nacionales (`control_pedidos.crear`, Ventas); verificar pago y remisión: Auxiliar; responder consulta: CEDIS; guía: Delegado. Sin permiso nuevo (decisión mía, ver preguntas abiertas).
- Auditoría: autor, fecha, nota, piezas, evidencia y relación principal–hijo en bitácora de ambos pedidos.

## Decididas por mí (corregibles)

- Primera entrega solo Nacional; Tienda y Municipal después.
- El anexo pasa por mini consulta a CEDIS **con peso** porque Nacional lo exige; la reutilización exacta (modal de pesaje en modo «anexo» o respuesta propia en `ModalDetalleCedis`) la decide el constructor tras leer `ModalResponderPesaje.jsx`.
- Como el anexo sale en caja y guía aparte, **no hay paso de «integración física al principal»**: el hijo recorre el flujo nacional completo con datos heredados.
- «A punto de salir» se resuelve con el diseño: el anexo se puede pedir con el principal pagado o remisionado en cualquier momento; si el principal ya salió, el anexo es un envío nuevo.
- Se quita la validación automática de pago del complemento y se sustituye por el flujo normal con la auxiliar. Las pruebas de `NacionalOla3*` que asumen «complemento pagado» se actualizan con esa decisión (V3.1 prevalece sobre un test verde).
- El anexo es un pedido hijo por flujo completo (consulta → cierre → pago → remisión → integración), no un campo del principal.

## Preguntas abiertas (para el usuario)

1. Resuelta: el anexo sale en **caja y guía aparte** (23-nov).
2. «A punto de salir»: resuelta por diseño (cualquier momento con el principal pagado o remisionado).
3. **Abierta, con default mío:** ¿cualquier vendedora del pedido puede solicitar el anexo (mi default, mismo permiso que crear pedidos nacionales), o solo con un permiso especial?

## Queda al constructor

Nombre exacto de rutas y estados del hijo, esquema de la etiqueta en cada bandeja, orden de las pruebas, y cómo reutilizar el editor de piezas sin tocar el formulario principal.
