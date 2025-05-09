<?php
/**
 * ImnovaGES - Funciones de Importación
 * 
 * Este archivo contiene todas las funciones necesarias para importar
 * datos desde archivos Excel a la base de datos de ImnovaGES.
 */
 
// Aumentar el límite de memoria de PHP y tiempo de ejecución
ini_set('memory_limit', '512M');
set_time_limit(300); // 5 minutos
gc_enable();

// Cargar la biblioteca PhpSpreadsheet
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

// Configuración de la base de datos
$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'administracionwe_awesinmo',
    'username' => 'administracionwe_awesinmo',
    'password' => 'administracionwe_awesinmo'
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

// Función simplificada para cargar archivos Excel
function cargarExcelSimplificado($excelFile) {
    try {
        // Crear lector con configuración básica
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($excelFile);
        $reader->setReadDataOnly(true); // Solo leer datos, ignorar formatos
        
        // Cargar el archivo
        $spreadsheet = $reader->load($excelFile);
        $worksheet = $spreadsheet->getActiveSheet();
        
        // Convertir a array
        $data = $worksheet->toArray();
        
        // Liberar memoria
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        
        // Ejecutar recolector de basura
        gc_collect_cycles();
        
        return $data;
        
    } catch (Exception $e) {
        echo "Error al cargar Excel: " . $e->getMessage() . "<br>";
        return [];
    }
}

// Incluir las funciones de importación
require_once 'reference-tables.php';
require_once 'import-clients.php';
require_once 'import-properties.php';
require_once 'import-orders.php';
require_once 'import-activities.php';
require_once 'import-news.php';
require_once 'relationships-verification.php';

// Función para registrar el uso de memoria
function logMemoryUsage($mensaje) {
    echo "$mensaje - Memoria en uso: " . (memory_get_usage(true) / 1024 / 1024) . " MB<br>";
    gc_collect_cycles();
}