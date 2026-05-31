-- Inspección: varios usuarios con el mismo nombre en un grupo
-- Sustituye :CONVERSACION_ID por el id del chat de grupo

-- 1) Participantes del grupo y sus IDs reales
SELECT
    cp.conversacion_id,
    u.id AS user_id,
    u.name,
    u.username,
    u.email,
    cp.rol
FROM conversacion_participantes cp
JOIN users u ON u.id = cp.user_id
WHERE cp.conversacion_id = :CONVERSACION_ID
ORDER BY u.name, u.id;

-- 2) Usuarios llamados "Gerente" en todo el sistema
SELECT id, name, username, email
FROM users
WHERE name = 'Gerente'
ORDER BY id;

-- 3) Mensajes del grupo: cada fila = un registro distinto (mensajes.id) ligado a user_id
SELECT
    m.id AS mensaje_id,
    m.user_id,
    u.name,
    u.username,
    LEFT(m.contenido, 80) AS contenido,
    m.created_at
FROM mensajes m
JOIN users u ON u.id = m.user_id
WHERE m.conversacion_id = :CONVERSACION_ID
  AND m.deleted_at IS NULL
ORDER BY m.created_at, m.id;

-- 4) Resumen: ¿cuántos user_id distintos enviaron con nombre "Gerente"?
SELECT
    m.user_id,
    u.name,
    u.username,
    COUNT(*) AS total_mensajes,
    MIN(m.created_at) AS primer_mensaje,
    MAX(m.created_at) AS ultimo_mensaje
FROM mensajes m
JOIN users u ON u.id = m.user_id
WHERE m.conversacion_id = :CONVERSACION_ID
  AND m.deleted_at IS NULL
  AND u.name = 'Gerente'
GROUP BY m.user_id, u.name, u.username
ORDER BY m.user_id;
