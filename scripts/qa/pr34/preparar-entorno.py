#!/usr/bin/env python3
"""Crea solo secretos y directorios nuevos de QA; no copia ni lee el .env operativo."""
from pathlib import Path
import base64
import os
import secrets

root = Path(__file__).resolve().parents[3]
config = root / '.env.qa-pr34'
for directory in ['storage/app/public', 'storage/app/private', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap-cache', 'build', 'vite-temp', 'evidence']:
    (root / 'storage/pr34-qa' / directory).mkdir(parents=True, exist_ok=True)
if config.exists():
    print('Configuración QA existente conservada; no se regeneran claves.')
    raise SystemExit(0)
password = secrets.token_hex(24)
key = secrets.token_hex(16)
values = {
    'APP_ENV': 'qa-pr34', 'APP_NAME': 'GELIA_PR34_QA', 'APP_DEBUG': 'true',
    'APP_KEY': 'base64:' + base64.b64encode(secrets.token_bytes(32)).decode(),
    'APP_URL': 'http://qa-pr34', 'APP_ALLOWED_HOSTS': 'qa-pr34,localhost,127.0.0.1',
    'APP_CONFIG_CACHE': '/var/www/html/storage/framework/qa-config.php',
    'APP_ROUTES_CACHE': '/var/www/html/storage/framework/qa-routes.php',
    'APP_SERVICES_CACHE': '/var/www/html/storage/framework/qa-services.php',
    'APP_PACKAGES_CACHE': '/var/www/html/storage/framework/qa-packages.php',
    'DB_CONNECTION': 'mysql', 'DB_HOST': 'mysql', 'DB_PORT': '3306',
    'DB_DATABASE': 'gelia_pr34_qa', 'DB_USERNAME': 'gelia_pr34_qa', 'DB_PASSWORD': password,
    'MYSQL_DATABASE': 'gelia_pr34_qa', 'MYSQL_USER': 'gelia_pr34_qa', 'MYSQL_PASSWORD': password,
    'MYSQL_ROOT_PASSWORD': secrets.token_hex(24),
    'SESSION_DRIVER': 'database', 'SESSION_COOKIE': 'gelia_pr34_qa_session', 'SESSION_SECURE_COOKIE': 'false',
    'CACHE_STORE': 'file', 'QUEUE_CONNECTION': 'database', 'MAIL_MAILER': 'array',
    'BROADCAST_CONNECTION': 'reverb', 'REVERB_APP_ID': 'gelia-pr34-qa',
    'REVERB_APP_KEY': key, 'REVERB_APP_SECRET': secrets.token_hex(32),
    'REVERB_HOST': 'reverb-pr34', 'REVERB_PORT': '8080', 'REVERB_SCHEME': 'http',
    'VITE_REVERB_HOST': 'reverb-pr34', 'VITE_REVERB_PORT': '8080', 'VITE_REVERB_SCHEME': 'http',
    'VITE_REVERB_APP_KEY': key, 'WEBPUSH_ENABLED': 'false', 'WEBAUTHN_ENABLED': 'false',
    'SCOUT_DRIVER': 'null', 'MEDIOS_DRIVER': 'fake', 'TELESCOPE_ENABLED': 'false', 'NIGHTWATCH_ENABLED': 'false',
    'COMPOSE_PROJECT_NAME': 'gelia-pr34-qa', 'SAIL_FILES': 'compose.pr34-qa.yaml',
    'APP_SERVICE': 'laravel.test', 'APP_USER': 'sail', 'WWWUSER': str(os.getuid()), 'WWWGROUP': str(os.getgid()),
    'QA_LOGIN_PASSWORD': secrets.token_hex(16),
}
# Creación exclusiva: nunca truncar una configuración ya existente.
with config.open('x') as stream:
    os.chmod(config, 0o600)
    stream.write(''.join(f'{k}={v}\n' for k, v in values.items()))
print('Claves nuevas de QA creadas en archivo local ignorado por Git, con modo 600.')
