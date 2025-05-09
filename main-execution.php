// Ejecutar el proceso completo de importación
function ejecutarImportacion() {
    global $pdo, $excelFiles;
    
    echo "<h1>Importación de datos para ImnovaGES</h1>";
    
    // Preparar tablas de referencia
    prepararTablasReferencia($pdo);
    logMemoryUsage("Después de preparar tablas de referencia");
    
    // Importar cada archivo Excel
    $totalClientes = importarClientes($pdo, $excelFiles['clientes']);
    logMemoryUsage("Después de importar clientes");
    
    $totalInmuebles = importarInmuebles($pdo, $excelFiles['inmuebles']);
    logMemoryUsage("Después de importar inmuebles");
    
    $totalEncargos = importarEncargos($pdo, $excelFiles['encargos']);
    logMemoryUsage("Después de importar encargos");
    
    $totalActividades = importarActividades($pdo, $excelFiles['actividades']);
    logMemoryUsage("Después de importar actividades");
    
    $totalNoticias = importarNoticias($pdo, $excelFiles['noticias']);
    logMemoryUsage("Después de importar noticias");
    
    // Verificar y actualizar relaciones
    verificarActualizarRelaciones($pdo);
    logMemoryUsage("Después de verificar relaciones");
    
    // Activar verificación de claves foráneas
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    
    echo "<h2>Resumen de la importación</h2>";
    echo "<ul>";
    echo "<li>Clientes importados: $totalClientes</li>";
    echo "<li>Inmuebles importados: $totalInmuebles</li>";
    echo "<li>Encargos importados: $totalEncargos</li>";
    echo "<li>Actividades importadas: $totalActividades</li>";
    echo "<li>Noticias importadas: $totalNoticias</li>";
    echo "</ul>";
    
    echo "<p>Importación completada. Puede acceder al sistema ImnovaGES para verificar los datos.</p>";
}

// Iniciar la importación
try {
    // Iniciar buffer de salida para mostrar progreso en tiempo real
    ob_start();
    
    // Ejecutar importación
    ejecutarImportacion();
    
    // Finalizar buffer de salida
    ob_end_flush();
    
} catch (Exception $e) {
    echo "<h2>Error durante la importación</h2>";
    echo "<p>Se ha producido un error durante el proceso de importación: " . $e->getMessage() . "</p>";
    echo "<p>Traza: " . $e->getTraceAsString() . "</p>";
    
    // Activar verificación de claves foráneas en caso de error
    if (isset($pdo)) {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}