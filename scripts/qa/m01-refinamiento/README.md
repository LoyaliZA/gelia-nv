# Repetir el recorrido de captura municipal

Usa exclusivamente `codex_m01_audit_20261114`, ya preparada por las pruebas MySQL de `../m01-auditoria/README.md`. No reconstruirla mientras esté ejecutándose el navegador. El fixture rechaza cualquier otra base, crea nombres/códigos ficticios con sufijo y añade permisos solo al usuario sintético.

1. Con las variables aisladas del README de auditoría, ejecutar mediante Sail `php scripts/qa/m01-refinamiento/fixtures.php` y redirigir su JSON a `/tmp/m01-fixtures.json` dentro del contenedor Laravel.
2. Iniciar un Laravel propio en 9193 con esa base, caches en `/tmp`, entorno testing, cache array, queue sync, broadcast null, mail array, `APP_URL=http://laravel.test:9193`, `APP_ALLOWED_HOSTS=laravel.test`, `SESSION_DRIVER=file`, `SESSION_COOKIE=gelia_m01_refinamiento`, `SESSION_SECURE_COOKIE=false`.
3. Con el build publicado, ejecutar `./vendor/bin/sail exec --user sail laravel.test node scripts/qa/m01-refinamiento/navegador.mjs`.
4. El guion comprueba que Selenium esté libre y borra solo su propia sesión. Sus capturas y JSON quedan en `/tmp/gelia-m01-refinamiento`. Recuperar solo las capturas `registro-*.png`, `documentos-*.png` y `resultado.json`; `fallo.png`, si existe de otro intento, no acredita el recorrido final.
5. Cerrar exclusivamente ese Laravel temporal y sus workers. No detener contenedores. No se suben binarios, ni se modifican datos reales.

El password del fixture pertenece únicamente al usuario ficticio. No usar estos guiones como seed operativo. El código del navegador prueba controles reales, no un montaje aislado de React.
