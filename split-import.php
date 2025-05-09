<?php
/**
 * ImnovaGES - Importador Dividido
 * 
 * Este script permite realizar la importación de datos por partes
 * para evitar problemas de memoria en servidores con recursos limitados.
 */

// Configuración inicial
ini_set('memory_limit', '512M');
set_time_limit(300); // 5 minutos
gc_enable();

// Archivo de progreso
$progresoFile = 'importacion_progreso.json';

// Definir los pasos de importación
$pasos = [
    'tablas_referencia' => 'Tablas de referencia',
    'clientes' => 'Clientes',
    'inmuebles' => 'Inmuebles',
    'encargos' => 'Encargos', 
    'actividades' => 'Actividades',
    'noticias' => 'Noticias',
    'relaciones' => 'Verificación de relaciones'
];

// Cargar progreso si existe
$progreso = [];
if (file_exists($progresoFile)) {
    $progreso = json_decode(file_get_contents($progresoFile), true);
}

// Inicializar progreso si no existe
if (empty($progreso)) {
    $progreso = [
        'paso_actual' => 'tablas_referencia',
        'completados' => [],
        'inicio' => date('Y-m-d H:i:s'),
        'fin' => null,
        'resultados' => []
    ];
}

// Guardar progreso
function guardarProgreso($progreso) {
    global $progresoFile;
    file_put_contents($progresoFile, json_encode($progreso, JSON_PRETTY_PRINT));
}

// Mostrar formulario si no se ha enviado
if (!isset($_POST['ejecutar'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>ImnovaGES - Importador Dividido</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #333; }
            .paso { margin-bottom: 10px; padding: 10px; border: 1px solid #ddd; }
            .completado { background-color: #d4edda; }
            .actual { background-color: #fff3cd; }
            .pendiente { background-color: #f8f9fa; }
            .boton { 
                padding: 10px 15px; 
                background-color: #007bff; 
                color: white; 
                border: none; 
                border-radius: 4px; 
                cursor: pointer; 
            }
            .reset { background-color: #dc3545; }
        </style>
    </head>
    <body>
        <h1>ImnovaGES - Importador Dividido</h1>
        <p>Este script permite realizar la importación de datos por partes para evitar problemas de memoria.</p>
        
        <h2>Progreso de la importación</h2>
        <?php foreach ($pasos as $id => $nombre): ?>
            <div class="paso <?= in_array($id, $progreso['completados']) ? 'completado' : ($id == $progreso['paso_actual'] ? 'actual' : 'pendiente') ?>">
                <strong><?= $nombre ?></strong>: 
                <?php 
                if (in_array($id, $progreso['completados'])) {
                    echo 'Completado';
                    if (isset($progreso['resultados'][$id])) {
                        echo ' - ' . $progreso['resultados'][$id];
                    }
                } elseif ($id == $progreso['paso_actual']) {
                    echo 'Pendiente de ejecutar';
                } else {
                    echo 'Pendiente';
                }
                ?>
            </div>
        <?php endforeach; ?>
        
        <form method="post" action="">
            <p>
                <input type="hidden" name="ejecutar" value="1">
                <button type="submit" class="boton">Ejecutar paso actual: <?= $pasos[$progreso['paso_actual']] ?></button>
            </p>
        </form>
        
        <form method="post" action="">
            <p>
                <input type="hidden" name="reset" value="1">
                <button type="submit" class="boton reset">Reiniciar importación</button>
            </p>
        </form>
    </body>
    </html>
    <?php
    exit;
}

// Reiniciar importación si se solicita
if (isset($_POST['reset'])) {
    $progreso = [
        'paso_actual' => 'tablas_referencia',
        'completados' => [],
        'inicio' => date('Y-m-d H:i:s'),
        'fin' => null,
        'resultados' => []
    ];
    guardarProgreso($progreso);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Cargar las utilidades y funciones necesarias
require_once 'import-functions.php';

// Ejecutar el paso actual
switch ($progreso['paso_actual']) {
    case 'tablas_referencia':
        prepararTablasReferencia($pdo);
        // Avanzar al siguiente paso
        $progreso['completados'][] = 'tablas_referencia';
        $progreso['paso_actual'] = 'clientes';
        $progreso['resultados']['tablas_referencia'] = 'Tablas de referencia preparadas correctamente';
        break;
        
    case 'clientes':
        $totalClientes = importarClientes($pdo, $excelFiles['clientes']);
        $progreso['completados'][] = 'clientes';
        $progreso['paso_actual'] = 'inmuebles';
        $progreso['resultados']['clientes'] = "Importados $totalClientes clientes";
        break;
        
    case 'inmuebles':
        $totalInmuebles = importarInmuebles($pdo, $excelFiles['inmuebles']);
        $progreso['completados'][] = 'inmuebles';
        $progreso['paso_actual'] = 'encargos';
        $progreso['resultados']['inmuebles'] = "Importados $totalInmuebles inmuebles";
        break;
        
    case 'encargos':
        $totalEncargos = importarEncargos($pdo, $excelFiles['encargos']);
        $progreso['completados'][] = 'encargos';
        $progreso['paso_actual'] = 'actividades';
        $progreso['resultados']['encargos'] = "Importados $totalEncargos encargos";
        break;
        
    case 'actividades':
        $totalActividades = importarActividades($pdo, $excelFiles['actividades']);
        $progreso['completados'][] = 'actividades';
        $progreso['paso_actual'] = 'noticias';
        $progreso['resultados']['actividades'] = "Importadas $totalActividades actividades";
        break;
        
    case 'noticias':
        $totalNoticias = importarNoticias($pdo, $excelFiles['noticias']);
        $progreso['completados'][] = 'noticias';
        $progreso['paso_actual'] = 'relaciones';
        $progreso['resultados']['noticias'] = "Importadas $totalNoticias noticias";
        break;
        
    case 'relaciones':
        verificarActualizarRelaciones($pdo);
        // Activar verificación de claves foráneas
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $progreso['completados'][] = 'relaciones';
        $progreso['fin'] = date('Y-m-d H:i:s');
        $progreso['resultados']['relaciones'] = "Relaciones verificadas y actualizadas";
        break;
}

// Guardar progreso
guardarProgreso($progreso);

// Redireccionar para mostrar el progreso actualizado
header('Location: ' . $_SERVER['PHP_SELF']);
exit;
