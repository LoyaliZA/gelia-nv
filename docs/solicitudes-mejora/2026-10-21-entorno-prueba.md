# SOLICITUD DE MEJORA - ENTORNO DE PRUEBA - CUBIERTO

## Solicitud inicial
Preparar un volcado de la base actual sin archivos de imagen, y dejar datos de prueba para los entornos nuevos. Hacía falta una cuenta con todos los permisos y modo super admin para que quien revise el sistema pueda entrar, y un seeder que complete los permisos que falten.

## Acciones
Se generó un volcado SQL local, comprimido, solo con datos. No se copiaron archivos de imagen ni tablas de trabajos, caché o sesiones. Las claves de token, contraseña, secreto o api quedaron vacías en ese archivo. En el repositorio quedó el catálogo de los 342 permisos actuales y los seeders que los plantan y crean la cuenta de revisión.

## Cambios
El volcado queda en el servidor, fuera del repositorio: `storage/app/entorno-prueba/gelia-entorno-prueba.sql.gz`.

Para un entorno nuevo, después de migrar:

`./vendor/bin/sail artisan db:importar-entorno-prueba --reemplazar-datos`

Antes hay que definir `AGENTE_REVISION_PASSWORD`. Ese comando no corre en producción. En esta base de trabajo no se importó el volcado.

La cuenta de revisión es `agente.revision@gelia.local`, con el rol Super Admin, todos los permisos y las sucursales activas de operación. El acceso de Gelia AI queda en modo super admin.

Si solo se necesitan los permisos y la cuenta, sin reemplazar datos:

`./vendor/bin/sail artisan db:seed --class=EntornoPruebaSeeder`

## Puntos a revisar
Comprobar en un entorno vacío que la importación deja clientes, productos y la cuenta de revisión. Entrar con esa cuenta y abrir un módulo que exija permiso directo, como punto de venta, eligiendo una sucursal activa. Las fotos no van a verse: el volcado guarda la ruta, no el archivo. Al sembrar permisos, el rol Super Admin pasa a tener todos los permisos; las demás cuentas con ese rol también los reciben por el rol.

## Mejoras posibles
Volver a generar el volcado cuando la operación cambie, con `db:volcado-entorno-prueba`, para que el catálogo de permisos y los datos sigan al día. No quedó incluido un paquete de archivos de imagen.
