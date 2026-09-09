#!/usr/bin/env python3
"""
Extrae e importa únicamente datos del módulo Gestión de Pedidos desde un dump SQL.
Excluye catálogos, usuarios, clientes y demás módulos.
"""

from __future__ import annotations

import argparse
import subprocess
import sys
from pathlib import Path

# Tablas de datos del módulo (sin catálogos ni configuración).
TABLAS_PEDIDOS = [
    # Hijos profundos primero en DELETE; INSERT en orden inverso al final.
    "pedido_bma_tarea_sesion_evidencia_fotos",
    "pedido_bma_tarea_sesiones_evidencia",
    "pedido_bma_tarea_documentos",
    "pedido_bma_tarea_historial",
    "pedido_bma_tarea_productos",
    "pedido_bma_tareas_preparacion",
    "pedido_bma_sesion_evidencia_fotos",
    "pedido_bma_sesiones_evidencia",
    "pedido_bma_cancelacion_operativa_tareas",
    "pedido_bma_cancelaciones_operativas",
    "pedido_bma_alertas_preparacion",
    "pedido_bma_anexos_envio",
    "pedido_bma_caratulas",
    "pedido_bma_cierre_pago_items",
    "pedido_bma_cierres_pago",
    "pedido_bma_revisiones_producto",
    "pedido_bma_errores",
    "pedido_bma_historial_estados",
    "pedido_bma_direcciones",
    "pedido_bma_documentos",
    "pedido_bma_cajas",
    "pedido_bma_pagos",
    "auditorias_pedidos_bma",
    "saf_pedido_aplicaciones",
    "saf_evidencias",
    "saf_movimientos",
    "saf_comprobante_reimpresiones",
    "saf_comprobantes_caja",
    "saf_incidencias",
    "saf_creditos",
    "saf_cuentas",
    "reporte_pagos_pedidos_exportaciones",
    "operacion_empaque_miembros",
    "operaciones_empaque",
    "pedidos_bma",
]

# Módulos externos que referencian pedidos_bma: desvincular antes de borrar.
DESVINCULAR_PEDIDO = [
    ("pdv_resguardo_bultos", "pedido_bma_id"),
    ("pdv_resguardo_entregas", "pedido_bma_id"),
    ("pdv_resguardos", "pedido_bma_id"),
]


def extraer_inserts(sql: str, tabla: str) -> str | None:
    marcador = f"INSERT INTO `{tabla}` VALUES"
    inicio = sql.find(marcador)
    if inicio < 0:
        return None
    fin = sql.find(";\n", inicio)
    if fin < 0:
        return None
    return sql[inicio : fin + 1]


def generar_sql_importacion(sql_dump: str) -> str:
    lineas = [
        "SET NAMES utf8mb4;",
        "SET FOREIGN_KEY_CHECKS = 0;",
        "SET UNIQUE_CHECKS = 0;",
        "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';",
        "",
        "-- Desvincular referencias externas a pedidos",
    ]
    for tabla, columna in DESVINCULAR_PEDIDO:
        lineas.append(f"UPDATE `{tabla}` SET `{columna}` = NULL WHERE `{columna}` IS NOT NULL;")

    lineas.append("")
    lineas.append("-- Limpiar tablas del módulo de pedidos")
    for tabla in TABLAS_PEDIDOS:
        lineas.append(f"DELETE FROM `{tabla}`;")

    lineas.append("")
    lineas.append("-- Cargar datos desde snapshot")
    inserts = 0
    for tabla in reversed(TABLAS_PEDIDOS):
        stmt = extraer_inserts(sql_dump, tabla)
        if stmt:
            lineas.append(stmt)
            inserts += 1

    lineas.extend([
        "",
        "SET FOREIGN_KEY_CHECKS = 1;",
        "SET UNIQUE_CHECKS = 1;",
        f"-- Tablas con datos importados: {inserts}",
    ])
    return "\n".join(lineas) + "\n"


def main() -> int:
    parser = argparse.ArgumentParser(description="Importar módulo de pedidos desde snapshot SQL")
    parser.add_argument("snapshot_sql", type=Path, help="Ruta al .sql o .sql.gz del snapshot")
    parser.add_argument(
        "--output",
        type=Path,
        default=Path("/tmp/import_modulo_pedidos.sql"),
        help="Archivo SQL de salida",
    )
    parser.add_argument(
        "--execute",
        action="store_true",
        help="Ejecutar el SQL generado vía sail mysql",
    )
    args = parser.parse_args()

    if not args.snapshot_sql.exists():
        print(f"ERROR: no existe {args.snapshot_sql}", file=sys.stderr)
        return 1

    if str(args.snapshot_sql).endswith(".gz"):
        raw = subprocess.check_output(["gunzip", "-c", str(args.snapshot_sql)])
        sql_dump = raw.decode("utf-8", errors="replace")
    else:
        sql_dump = args.snapshot_sql.read_text(encoding="utf-8", errors="replace")

    out_sql = generar_sql_importacion(sql_dump)
    args.output.write_text(out_sql, encoding="utf-8")
    print(f"SQL generado: {args.output} ({len(out_sql)} bytes)")

    if args.execute:
        project = Path(__file__).resolve().parents[1]
        cmd = [
            str(project / "vendor/bin/sail"),
            "exec",
            "-T",
            "mysql",
            "mysql",
            "-usail",
            "-ppassword",
            "laravel",
        ]
        subprocess.run(cmd, input=out_sql.encode("utf-8"), cwd=project, check=True)
        print("Importación ejecutada correctamente.")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
