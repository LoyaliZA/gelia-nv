# Verificación — ficha de tienda en auditoría

## Comprobaciones

| Comprobación | Entorno | Resultado |
| --- | --- | --- |
| `npm test -- resources/js/Pages/ControlPedidos/Auditar/Partials/remisionVigente.test.js` | Host, Vitest | 9 pruebas aprobadas. El cierre del proceso de Vitest reportó `kill EACCES` en el sandbox; las pruebas ya habían pasado. |
| Transformación JSX de `ModalRevisarPedido.jsx` | Vite, caché en `/tmp` | Correcta después de agrupar envío, datos y anexo en un fragmento. |
| `npm run check:style-jsx` | Host | El modal no introduce hexadecimales. El comando falla por literales previos en `Profile/Edit.jsx` y `ModalFormSolicitud.jsx`. |
| Pedido de tienda 3425235, origen TIENDA, sin logística | Escritorio, auditoría real, sin guardar | La ficha muestra cliente, cuenta, pedido, almacén, tipo y total. No muestra envío. Pagos, rechazo, validación, aprobación deshabilitada y adjunto siguen presentes. |
| «Ver el resto del pedido» y el salto «Resto del pedido» | Mismo pedido | Abren cobertura, pedido compartido, envío y datos generales. «Ocultar» los vuelve a guardar. |
| PDF sin número de remisión | Mismo pedido, archivo local no enviado | Aparece «Escribe el número de remisión antes de adjuntar el PDF.», el foco va al número y no sale ninguna petición. |
| Ancho 390 px | Emulación del mismo diálogo | Los datos del cliente se apilan y el total permanece visible. |

No se validó un pago, no se aprobó el pedido y no se guardó un número ni un PDF reales. Los pedidos con logística conservan la revisión amplia porque la ficha solo aplica cuando el origen no requiere logística y el pedido no es recoge en tienda.

## Corrección del panel lateral

Al abrir «Resto del pedido» la ficha pasaba al escritorio de dos columnas y la cobertura quedaba a la izquierda, sin el control de ocultar a la vista. Ahora el pedido de tienda permanece en una columna de 48 rem. El resto se despliega debajo y «Ocultar el resto del pedido» lo vuelve a guardar. Comprobado en el pedido 3425235: el diálogo mide 768 px en una ventana de 1607 px y la cobertura queda en la misma columna que los datos.
