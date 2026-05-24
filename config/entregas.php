<?php

/**
 * Sectores de periferia (km 0 → punto) para asignar horario cuando el domicilio
 * está dentro del radio de tolerancia pero fuera de los polígonos comerciales.
 *
 * Ángulos en grados desde el Norte, sentido horario (0° = Norte, 90° = Este).
 * Alineado al esquema operativo de Villahermosa:
 * - Oeste / Noroeste → ZONA 1 (límite amarillo)
 * - Sur / Suroeste → ZONA 2 (zona verde)
 * - Norte / Noreste / Este / Sureste → ZONA 3 (periferias naranja)
 */
return [
    'periferia_sectores' => [
        ['min' => 0, 'max' => 140, 'zona' => 'ZONA 3'],   // Norte, noreste, este y sureste
        ['min' => 140, 'max' => 250, 'zona' => 'ZONA 2'], // Sur y suroeste (Tamulté)
        ['min' => 250, 'max' => 360, 'zona' => 'ZONA 1'], // Oeste y noroeste (límite amarillo)
    ],
];
