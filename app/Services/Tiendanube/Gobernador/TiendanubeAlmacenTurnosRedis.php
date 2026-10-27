<?php

namespace App\Services\Tiendanube\Gobernador;

use Redis;

/**
 * Un script atómico por tienda. Las pruebas usan la base lógica 15 y un prefijo propio;
 * olvidar() solo borra ese prefijo. No ejecuta FLUSHDB ni FLUSHALL.
 */
class TiendanubeAlmacenTurnosRedis
{
    private const SCRIPT = <<<'LUA'
local p = KEYS[1]
local now = tonumber(ARGV[1])
local intervalo = tonumber(ARGV[2])
local accion = ARGV[3]
local clase = ARGV[4]
local proposito = ARGV[5]
local operacion = ARGV[6]
local fase = ARGV[7]
local extra = ARGV[8]

local function responder(concedido, motivo, faseOut)
  local reanudar = redis.call('GET', p .. ':siguiente') or '0'
  return { concedido, motivo, tostring(reanudar), faseOut or '' }
end

local function motivoTiempo()
  local remoto = tonumber(redis.call('GET', p .. ':limite_remoto_hasta') or '0')
  if now < remoto then
    return 'rate_limit'
  end
  return 'sin_presupuesto_local'
end

local function denegar(motivo)
  local faseOut = fase
  if operacion ~= '' then
    local ek = p .. ':espera:' .. operacion
    local previa = redis.call('HGET', ek, 'fase')
    if fase ~= '' then
      redis.call('HSET', ek, 'fase', fase)
      faseOut = fase
    elseif previa then
      faseOut = previa
    end
    local reanudar = redis.call('GET', p .. ':siguiente') or tostring(now)
    redis.call('HSET', ek, 'motivo', motivo, 'reanudar_despues_at', reanudar)
  end
  return responder(0, motivo, faseOut)
end

local function nombreDe(item)
  local nombre = string.match(item, '^([^|]+)|')
  if nombre then
    return nombre
  end
  return item
end

local function depurarVentana()
  local lista = redis.call('LRANGE', p .. ':ventana', 0, -1)
  if intervalo <= 0 then
    return lista
  end
  local horizonte = 10 * intervalo
  local vigente = {}
  for _, item in ipairs(lista) do
    local marca = tonumber(string.match(item, '|(%d+)$') or '')
    if not marca or (now - marca) < horizonte then
      vigente[#vigente + 1] = item
    end
  end
  if #vigente ~= #lista then
    redis.call('DEL', p .. ':ventana')
    if #vigente > 0 then
      redis.call('RPUSH', p .. ':ventana', unpack(vigente))
    end
  end
  return vigente
end

local function activas()
  local set = {}
  local n = 0
  local flat = redis.call('HGETALL', p .. ':demanda')
  local i = 1
  while i <= #flat do
    local campo = flat[i]
    local vence = tonumber(flat[i + 1]) or 0
    if vence <= now then
      redis.call('HDEL', p .. ':demanda', campo)
    else
      local claseDemanda = string.match(campo, '|([^|]+)$')
      if claseDemanda and not set[claseDemanda] then
        set[claseDemanda] = true
        n = n + 1
      end
    end
    i = i + 2
  end
  return set, n
end

local function conteo()
  local c = {}
  for _, item in ipairs(depurarVentana()) do
    local nombre = nombreDe(item)
    c[nombre] = (c[nombre] or 0) + 1
  end
  return c
end

local mins = { interactivo = 3, recuperacion = 1, publicacion = 3, catalogo = 2 }
local maxs = { interactivo = 6, recuperacion = 2, publicacion = 6, catalogo = 4 }

local function cabe(nombre)
  local set, n = activas()
  local solo = (n == 0) or (n == 1 and set[nombre] == true)
  local c = conteo()
  local used = c[nombre] or 0
  local maximo = solo and 10 or (maxs[nombre] or 10)
  if used >= maximo then
    return false
  end
  if not solo then
    local piso = mins[nombre] or 0
    if used >= piso then
      for otra, _ in pairs(set) do
        if otra ~= nombre and (c[otra] or 0) < (mins[otra] or 0) then
          return false
        end
      end
    end
  end
  return true
end

local function cobrar()
  redis.call('RPUSH', p .. ':ventana', clase .. '|' .. tostring(now))
  redis.call('LTRIM', p .. ':ventana', -10, -1)
  redis.call('SET', p .. ':siguiente', tostring(now + intervalo))
end

local function anotarFase()
  if operacion ~= '' and fase ~= '' then
    redis.call('HSET', p .. ':espera:' .. operacion, 'fase', fase)
  end
end

if accion == 'anunciar' then
  redis.call('HSET', p .. ':demanda', operacion .. '|' .. clase, extra)
  return responder(1, '', '')
end

if accion == 'retirar' then
  redis.call('HDEL', p .. ':demanda', operacion .. '|' .. clase)
  return responder(1, '', '')
end

if accion == 'clases' then
  local set = activas()
  local lista = {}
  for nombre, _ in pairs(set) do
    lista[#lista + 1] = nombre
  end
  return lista
end

if accion == 'estado' then
  local rk = p .. ':reserva:' .. operacion
  return { redis.call('HGET', rk, 'pendiente') or '0', redis.call('HGET', rk, 'vigencias') or '0' }
end

if accion == 'reservar' then
  if operacion == '' then
    return denegar('sin_presupuesto_local')
  end
  local rk = p .. ':reserva:' .. operacion
  if redis.call('HGET', rk, 'pendiente') == '1' then
    if now < tonumber(redis.call('GET', p .. ':siguiente') or '0') then
      return denegar(motivoTiempo())
    end
    return responder(1, '', fase)
  end
  if now < tonumber(redis.call('GET', p .. ':siguiente') or '0') then
    return denegar(motivoTiempo())
  end
  if not cabe(clase) then
    return denegar('sin_presupuesto_local')
  end
  cobrar()
  redis.call('HSET', rk, 'pendiente', '1', 'vigencias', '0', 'clase', clase)
  anotarFase()
  return responder(1, '', fase)
end

if accion == 'adquirir' then
  local siguiente = tonumber(redis.call('GET', p .. ':siguiente') or '0')
  if proposito == 'comprobacion_vigencia' then
    if operacion == '' then
      return denegar('sin_presupuesto_local')
    end
    local rk = p .. ':reserva:' .. operacion
    local vigencias = tonumber(redis.call('HGET', rk, 'vigencias') or '-1')
    if redis.call('HGET', rk, 'pendiente') ~= '1' or vigencias < 0 or vigencias >= 1 then
      return denegar('sin_presupuesto_local')
    end
    if now < siguiente then
      return denegar(motivoTiempo())
    end
    redis.call('HSET', rk, 'vigencias', '1')
    redis.call('SET', p .. ':siguiente', tostring(now + intervalo))
    anotarFase()
    return responder(1, '', fase)
  end
  if operacion ~= '' and redis.call('HGET', p .. ':reserva:' .. operacion, 'pendiente') == '1' then
    if now < siguiente then
      return denegar(motivoTiempo())
    end
    redis.call('HSET', p .. ':reserva:' .. operacion, 'pendiente', '0')
    redis.call('SET', p .. ':siguiente', tostring(now + intervalo))
    anotarFase()
    return responder(1, '', fase)
  end
  if now < siguiente then
    return denegar(motivoTiempo())
  end
  if not cabe(clase) then
    return denegar('sin_presupuesto_local')
  end
  cobrar()
  anotarFase()
  return responder(1, '', fase)
end

if accion == 'limite' then
  local reset = tonumber(extra) or 0
  if reset < 0 then
    reset = 0
  end
  local hasta = now + reset
  redis.call('SET', p .. ':limite_remoto_hasta', tostring(hasta))
  local actual = tonumber(redis.call('GET', p .. ':siguiente') or '0')
  if hasta > actual then
    redis.call('SET', p .. ':siguiente', tostring(hasta))
  end
  local faseOut = fase
  if operacion ~= '' then
    local ek = p .. ':espera:' .. operacion
    local previa = redis.call('HGET', ek, 'fase')
    if fase == '' and previa then
      faseOut = previa
    end
    if faseOut ~= '' then
      redis.call('HSET', ek, 'fase', faseOut)
    end
    redis.call('HSET', ek, 'motivo', 'rate_limit', 'reanudar_despues_at', tostring(hasta))
  end
  return responder(0, 'rate_limit', faseOut)
end

if accion == 'evidencia' then
  if operacion == '' then
    return responder(0, '', '')
  end
  local ek = p .. ':espera:' .. operacion
  local previa = redis.call('HGET', ek, 'evidencia')
  local base = {}
  if previa and previa ~= '' then
    base = cjson.decode(previa)
  end
  local nuevo = cjson.decode(extra)
  for k, v in pairs(nuevo) do
    base[k] = v
  end
  if fase ~= '' then
    redis.call('HSET', ek, 'fase', fase)
  end
  redis.call('HSET', ek, 'evidencia', cjson.encode(base))
  return responder(1, '', fase)
end

if accion == 'liberar' then
  if operacion ~= '' then
    redis.call('HSET', p .. ':reserva:' .. operacion, 'pendiente', '0')
  end
  return responder(1, '', fase)
end

if accion == 'ventana' then
  return redis.call('LRANGE', p .. ':ventana', 0, -1)
end

return responder(0, 'sin_presupuesto_local', fase)
LUA;

    public function __construct(
        private readonly Redis $redis,
        private readonly string $prefijo,
    ) {}

    /**
     * @return array<int, mixed>
     */
    public function evaluar(string $clave, string $accion, int $ahora, int $intervalo, string $clase, string $proposito, string $operacion, string $fase, string $extra = '{}'): array
    {
        $resultado = $this->redis->eval(self::SCRIPT, [
            $this->prefijo.':'.$clave,
            (string) $ahora,
            (string) $intervalo,
            $accion,
            $clase,
            $proposito,
            $operacion,
            $fase,
            $extra,
        ], 1);

        return is_array($resultado) ? $resultado : [];
    }

    /**
     * @return array<string, string>
     */
    public function espera(string $clave, string $operacion): array
    {
        $datos = $this->redis->hGetAll($this->prefijo.':'.$clave.':espera:'.$operacion);

        return is_array($datos) ? $datos : [];
    }

    public function olvidar(): void
    {
        $cursor = null;
        do {
            $claves = $this->redis->scan($cursor, $this->prefijo.':*', 100);
            if ($claves === false) {
                break;
            }
            if (is_array($claves) && $claves !== []) {
                $this->redis->del($claves);
            }
        } while ((int) $cursor !== 0);
    }
}
