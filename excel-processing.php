/**
 * Función simplificada para cargar archivos Excel
 * Usa el método más básico y compatible con diferentes versiones de PhpSpreadsheet
 * 
 * @param string $excelFile Ruta al archivo Excel
 * @return array Datos del Excel como array
 */
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

/**
 * Función optimizada para cargar archivos Excel (versión completa)
 * Intenta utilizar métodos de optimización específicos para archivos grandes
 * 
 * @param string $excelFile Ruta al archivo Excel
 * @return array Datos del Excel como array
 */
function cargarExcelOptimizado($excelFile) {
    try {
        // Crear lector con configuración optimizada
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($excelFile);
        $reader->setReadDataOnly(true); // Solo leer datos, ignorar formatos
        
        // Opciones adicionales para reducir memoria
        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false); // No leer celdas vacías
        }
        
        // Cargar solo la primera hoja
        $worksheetData = [];
        $worksheetInfo = $reader->listWorksheetInfo($excelFile);
        
        if (!empty($worksheetInfo)) {
            $sheetName = $worksheetInfo[0]['worksheetName'];
            $reader->setLoadSheetsOnly($sheetName);
            
            // Cargar el archivo
            $spreadsheet = $reader->load($excelFile);
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Convertir a array de forma más directa
            $worksheetData = $worksheet->toArray();
            
            // Liberar memoria
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
        
        // Recolectar basura para liberar memoria
        gc_collect_cycles();
        
        return $worksheetData;
        
    } catch (Exception $e) {
        echo "Error al cargar Excel optimizado: " . $e->getMessage() . "<br>";
        // Si falla, intentar con el método simplificado
        return cargarExcelSimplificado($excelFile);
    }
}

/**
 * Alternativa para cargar Excel con Spout (si PhpSpreadsheet sigue fallando)
 * Requiere instalar: composer require box/spout
 * Esta función es una alternativa si decides cambiar a Spout
 */
function cargarExcelConSpout($excelFile) {
    // Verificar si la biblioteca Spout está instalada
    if (!class_exists('\\Box\\Spout\\Reader\\Common\\Creator\\ReaderEntityFactory')) {
        echo "La biblioteca Spout no está instalada. Ejecuta: composer require box/spout";
        return [];
    }
    
    $data = [];
    
    try {
        $reader = \Box\Spout\Reader\Common\Creator\ReaderEntityFactory::createReaderFromFile($excelFile);
        $reader->open($excelFile);
        
        foreach ($reader->getSheetIterator() as $sheet) {
            // Solo procesar la primera hoja
            foreach ($sheet->getRowIterator() as $row) {
                $data[] = $row->toArray();
            }
            break;
        }
        
        $reader->close();
        
    } catch (Exception $e) {
        echo "Error al cargar Excel con Spout: " . $e->getMessage() . "<br>";
    }
    
    return $data;
}
