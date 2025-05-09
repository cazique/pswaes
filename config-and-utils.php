<?php
// Aumentar el límite de memoria de PHP
ini_set('memory_limit', '512M');
set_time_limit(300); // 5 minutos

// Mostrar información de memoria y activar recolección de basura
echo "Memoria límite actual: " . ini_get('memory_limit') . "<br>";
echo "Memoria en uso inicial: " . (memory_get_usage(true) / 1024 / 1024) . " MB<br>";
gc_enable();

// Registrar uso de memoria periódicamente
function logMemoryUsage($mensaje) {
    echo "$mensaje - Memoria en uso: " . (memory_get_usage(true) / 1024 / 1024) . " MB<br>";
    gc_collect_cycles();
}

/**
 * ImnovaGES - Importador de Datos Excel
 * 
 * Este script importa los datos de los archivos Excel proporcionados
 * directamente a la base de datos de ImnovaGES.
 * 
 * Requisitos:
 * - PHP 7.2 o superior
 * - Extensión PHP para PDO y MySQL
 * - Extensión PHP para leer Excel (PhpSpreadsheet)
 */
 
// Cargar la biblioteca PhpSpreadsheet (debes instalarla con Composer)
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

// Configuración de la base de datos
$dbConfig = [
    'host' => 'localhost',     // Ajusta según tu configuración
    'dbname' => 'administracionwe_awesinmo',  // Nombre de tu base de datos
    'username' => 'administracionwe_awesinmo',    // Tu usuario de base de datos
    'password' => 'administracionwe_awesinmo'     // Tu contraseña de base de datos
];

// Ruta a los archivos Excel
$excelFiles = [
    'clientes' => 'mdbq3_clientes-2025-01-16-16-46-36_SC.xlsx',
    'inmuebles' => 'mdbq3_inmuebles-2025-01-16-16-45-47_SC.xlsx',
    'encargos' => 'mdbq3_encargos-2025-01-16-16-46-17_SC.xlsx',
    'actividades' => 'mdbq3_actividades-2025-01-16-16-46-53_SC.xlsx',
    'noticias' => 'mdbq3_noticias-2025-01-16-16-46-02_SC.xlsx'
];

// Conexión a la base de datos
try {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    echo "Conexión a la base de datos establecida.<br>";
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Desactivar verificación de claves foráneas temporalmente
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

// Función para limpiar datos
function limpiarDato($valor) {
    if ($valor === null || $valor === '') {
        return null;
    }
    if (is_string($valor)) {
        return trim($valor);
    }
    return $valor;
}

// Función para generar ID único
function generarIdUnico() {
    return substr(uniqid(), 0, 20);
}

// Función para formatear fecha
function formatearFecha($fecha) {
    if ($fecha === null || $fecha === '') {
        return null;
    }
    
    // Si ya es un objeto DateTime
    if ($fecha instanceof DateTime) {
        return $fecha->format('Y-m-d H:i:s');
    }
    
    // Si es una cadena, intentar convertir
    if (is_string($fecha)) {
        try {
            if (strpos($fecha, '/') !== false) {
                // Formato dd/mm/yyyy
                $partes = explode('/', $fecha);
                if (count($partes) === 3) {
                    return "{$partes[2]}-{$partes[1]}-{$partes[0]} 00:00:00";
                }
            }
            
            // Intentar con strtotime
            $timestamp = strtotime($fecha);
            if ($timestamp !== false) {
                return date('Y-m-d H:i:s', $timestamp);
            }
        } catch (Exception $e) {
            // Si hay error, devolver null
            return null;
        }
    }
    
    // Para números, asumir timestamp
    if (is_numeric($fecha)) {
        return date('Y-m-d H:i:s', $fecha);
    }
    
    return null;
}
