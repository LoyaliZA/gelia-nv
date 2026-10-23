#!/bin/bash
set -u
cd /var/www/html
PHP="php storage/framework/fase5-verificar/fase5.php"
RUN=/tmp/fase5-run
rm -rf "$RUN"
mkdir -p "$RUN" /tmp/fase5-imagenes

esperar_archivo() {
  local archivo="$1" limite=300
  while [ ! -f "$archivo" ] && [ "$limite" -gt 0 ]; do
    sleep 0.1
    limite=$((limite - 1))
  done
  [ -f "$archivo" ]
}

capturar_espera() {
  local salida="$1" intentos=200
  while [ "$intentos" -gt 0 ]; do
    if grep -q "LOCK WAIT" "$RUN/trx.log"; then
      grep "LOCK WAIT" "$RUN/trx.log" | tail -n 1 > "$salida"
      return 0
    fi
    sleep 0.05
    intentos=$((intentos - 1))
  done
  tail -n 1 "$RUN/trx.log" > "$salida" 2>/dev/null || true
  return 1
}

echo "=== INDICES ==="
: > "$RUN/trx.log"
$PHP vigilar "$RUN/trx.log" >/dev/null &
VIGILAR=$!
sleep 1

sembrar_vacio() {
  $PHP sembrar vacio "$1"
}

echo "=== CARGAS ==="
sembrar_vacio 920001 > "$RUN/carga-semilla.json"
PIDP=$(php -r '$j=json_decode(file_get_contents($argv[1]), true); echo $j["producto_id"];' "$RUN/carga-semilla.json")
rm -f "$RUN/carga-listo" "$RUN/carga-soltar"
$PHP observar "$PIDP" "$RUN/carga-listo" "$RUN/carga-soltar" > "$RUN/carga-observador.json" &
esperar_archivo "$RUN/carga-listo"
: > "$RUN/trx.log"
$PHP carga "$PIDP" > "$RUN/carga-a.json" &
$PHP carga "$PIDP" > "$RUN/carga-b.json" &
if capturar_espera "$RUN/carga-trx.json"; then echo CARGAS_LOCK_WAIT=si; else echo CARGAS_LOCK_WAIT=no; fi
touch "$RUN/carga-soltar"
wait
$PHP resumen "$PIDP" > "$RUN/carga-resumen.json"
echo "producto $PIDP"

echo "=== PRINCIPAL ==="
$PHP sembrar dos 920002 > "$RUN/prin-semilla.json"
PIDP=$(php -r '$j=json_decode(file_get_contents($argv[1]), true); echo $j["producto_id"]; echo " "; echo $j["imagenes"][0]; echo " "; echo $j["imagenes"][1];' "$RUN/prin-semilla.json")
set -- $PIDP
PP=$1; I1=$2; I2=$3
rm -f "$RUN/prin-listo" "$RUN/prin-soltar"
$PHP observar "$PP" "$RUN/prin-listo" "$RUN/prin-soltar" > "$RUN/prin-observador.json" &
esperar_archivo "$RUN/prin-listo"
: > "$RUN/trx.log"
$PHP principal "$PP" "$I1" > "$RUN/prin-a.json" &
$PHP principal "$PP" "$I2" > "$RUN/prin-b.json" &
if capturar_espera "$RUN/prin-trx.json"; then echo PRINCIPAL_LOCK_WAIT=si; else echo PRINCIPAL_LOCK_WAIT=no; fi
touch "$RUN/prin-soltar"
wait
$PHP resumen "$PP" > "$RUN/prin-resumen.json"

echo "=== REORDEN ==="
$PHP sembrar tres 920003 > "$RUN/ord-semilla.json"
read -r PP I1 I2 I3 < <(php -r '$j=json_decode(file_get_contents($argv[1]), true); echo $j["producto_id"]," ",$j["imagenes"][0]," ",$j["imagenes"][1]," ",$j["imagenes"][2];' "$RUN/ord-semilla.json")
rm -f "$RUN/ord-listo" "$RUN/ord-soltar"
$PHP observar "$PP" "$RUN/ord-listo" "$RUN/ord-soltar" > "$RUN/ord-observador.json" &
esperar_archivo "$RUN/ord-listo"
: > "$RUN/trx.log"
$PHP reordenar "$PP" "$I1,$I2,$I3" > "$RUN/ord-a.json" &
$PHP reordenar "$PP" "$I3,$I2,$I1" > "$RUN/ord-b.json" &
if capturar_espera "$RUN/ord-trx.json"; then echo REORDEN_LOCK_WAIT=si; else echo REORDEN_LOCK_WAIT=no; fi
touch "$RUN/ord-soltar"
wait
$PHP resumen "$PP" > "$RUN/ord-resumen.json"

echo "=== REINTENTO ==="
$PHP sembrar una 920004 > "$RUN/ret-semilla.json"
read -r PP IM < <(php -r '$j=json_decode(file_get_contents($argv[1]), true); echo $j["producto_id"]," ",$j["imagenes"][0];' "$RUN/ret-semilla.json")
: > "$RUN/trx.log"
$PHP reintento "$PP" "$IM" > "$RUN/ret-a.out" &
APID=$!
visto=0
parado=0
for i in $(seq 1 400); do
  if ! kill -0 "$APID" 2>/dev/null; then
    break
  fi
  if grep -q "for update" "$RUN/trx.log"; then
    visto=1
  fi
  if [ "$visto" = 1 ] && ! tail -n 1 "$RUN/trx.log" | grep -q "for update"; then
    kill -STOP "$APID" 2>/dev/null || true
    parado=1
    break
  fi
  sleep 0.02
done
$PHP resumen "$PP" > "$RUN/ret-durante.json"
$PHP reemplazar "$PP" "$IM" > "$RUN/ret-reemplazo.json"
if [ "$parado" = 1 ]; then
  kill -CONT "$APID" 2>/dev/null || true
fi
wait "$APID" || true
echo REINTENTO_PARADO=$parado
$PHP resumen "$PP" > "$RUN/ret-final.json"
kill "$VIGILAR" 2>/dev/null || true
wait "$VIGILAR" 2>/dev/null || true

echo "=== CONFIRMACION ==="
$PHP sembrar lote 920005 > "$RUN/lot-semilla.json"
read -r LOTE ARCH < <(php -r '$j=json_decode(file_get_contents($argv[1]), true); echo $j["lote_id"]," ",$j["archivo_id"]," ",$j["producto_id"];' "$RUN/lot-semilla.json")
PROD=$(php -r '$j=json_decode(file_get_contents($argv[1]), true); echo $j["producto_id"];' "$RUN/lot-semilla.json")
rm -f "$RUN/lot-listo" "$RUN/lot-soltar"
$PHP observar-archivo "$ARCH" "$RUN/lot-listo" "$RUN/lot-soltar" > "$RUN/lot-observador.json" &
esperar_archivo "$RUN/lot-listo"
: > "$RUN/trx.log"
$PHP confirmar "$LOTE" "$ARCH" > "$RUN/lot-a.out" &
$PHP confirmar "$LOTE" "$ARCH" > "$RUN/lot-b.out" &
if capturar_espera "$RUN/lot-trx.json"; then echo LOTE_LOCK_WAIT=si; else echo LOTE_LOCK_WAIT=no; fi
touch "$RUN/lot-soltar"
wait
$PHP resumen "$PROD" > "$RUN/lot-resumen.json"
$PHP archivo "$ARCH" > "$RUN/lot-archivo.json"
echo CONFIRMACION_HECHA
echo RUN_DIR=$RUN
