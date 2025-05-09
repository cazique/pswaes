// Función para importar encargos
function importarEncargos($pdo, $excelFile) {
    echo "Importando encargos desde $excelFile...<br>";
    
    try {
        // Cargar archivo Excel usando la función simplificada
        $rows = cargarExcelSimplificado($excelFile);
        
        if (empty($rows)) {
            echo "No se pudieron cargar datos del archivo Excel.<br>";
            return 0;
        }
        
        // Obtener encabezados (primera fila)
        $headers = array_shift($rows);
        
        // Validar encabezados antes de usar array_flip
        $headers = array_filter($headers, function($value) {
            return is_string($value) || is_int($value);
        });
        
        // Mapeo de columnas
        $columnMap = array_flip($headers);
        
        // Obtener mapas de estados y tipos de procedencia
        $estadosEncargo = [];
        $estadosContacto = [];
        $tiposProcedencia = [];
        $motivosCierre = [];
        
        $stmt = $pdo->query("SELECT estado_id, nombre FROM estados_encargo");
        while ($row = $stmt->fetch()) {
            $estadosEncargo[strtolower($row['nombre'])] = $row['estado_id'];
        }
        
        $stmt = $pdo->query("SELECT estado_contacto_id, nombre FROM estados_contacto");
        while ($row = $stmt->fetch()) {
            $estadosContacto[strtolower($row['nombre'])] = $row['estado_contacto_id'];
        }
        
        $stmt = $pdo->query("SELECT tipo_procedencia_id, nombre FROM tipos_procedencia");
        while ($row = $stmt->fetch()) {
            $tiposProcedencia[strtolower($row['nombre'])] = $row['tipo_procedencia_id'];
        }
        
        $stmt = $pdo->query("SELECT motivo_cierre_id, nombre FROM motivos_cierre");
        while ($row = $stmt->fetch()) {
            $motivosCierre[strtolower($row['nombre'])] = $row['motivo_cierre_id'];
        }
        
        // Contador
        $importados = 0;
        
        // Preparar consulta
        $sql = "INSERT INTO encargos (
                    encargo_id, referencia, inmueble_id, propietario_id, 
                    estado_id, estado_contacto_id, motivo, tipo_procedencia_id, 
                    precio, precio_parking_incluido, valoracion, valoracion_solo_inmueble, 
                    nuda_propiedad, tratabilidad, fecha_creacion, fecha_modificacion, 
                    fecha_cierre, motivo_cierre_id, fecha_ultima_cita, 
                    fecha_ultima_actividad, fecha_ultimo_contacto, nota_privada, llaves_oficina
                ) VALUES (
                    :encargo_id, :referencia, :inmueble_id, :propietario_id, 
                    :estado_id, :estado_contacto_id, :motivo, :tipo_procedencia_id, 
                    :precio, :precio_parking_incluido, :valoracion, :valoracion_solo_inmueble, 
                    :nuda_propiedad, :tratabilidad, :fecha_creacion, :fecha_modificacion, 
                    :fecha_cierre, :motivo_cierre_id, :fecha_ultima_cita, 
                    :fecha_ultima_actividad, :fecha_ultimo_contacto, :nota_privada, :llaves_oficina
                ) ON DUPLICATE KEY UPDATE
                    referencia = VALUES(referencia),
                    estado_id = VALUES(estado_id)";
        
        $stmt = $pdo->prepare($sql);
        
        // Procesar cada fila
        foreach ($rows as $row) {
            // Extraer encargo_id o generar uno nuevo
            $encargoId = isset($columnMap['Encargo Id.']) && !empty($row[$columnMap['Encargo Id.']]) 
                ? $row[$columnMap['Encargo Id.']] 
                : generarIdUnico();
            
            // Extraer referencia
            $encargoDesc = isset($columnMap['Encargo']) ? limpiarDato($row[$columnMap['Encargo']]) : '';
            $referencia = $encargoDesc ? substr($encargoDesc, 0, 30) : "ENC-" . substr($encargoId, 0, 8);
            
            // Mapear estado de encargo
            $estadoRaw = isset($columnMap['Estado encargo']) ? strtolower(limpiarDato($row[$columnMap['Estado encargo']])) : '';
            $estadoId = 1;  // Por defecto "Activo"
            
            foreach ($estadosEncargo as $nombre => $id) {
                if (strpos($estadoRaw, $nombre) !== false) {
                    $estadoId = $id;
                    break;
                }
            }
            
            // Mapear estado de contacto
            $estadoContactoRaw = isset($columnMap['Estado de contacto']) ? strtolower(limpiarDato($row[$columnMap['Estado de contacto']])) : '';
            $estadoContactoId = null;
            
            foreach ($estadosContacto as $nombre => $id) {
                if (strpos($estadoContactoRaw, $nombre) !== false) {
                    $estadoContactoId = $id;
                    break;
                }
            }
            
            // Determinar motivo
            $motivoRaw = isset($columnMap['Motivo']) ? strtolower(limpiarDato($row[$columnMap['Motivo']])) : '';
            $motivo = 'Venta';  // Valor por defecto
            
            if (strpos($motivoRaw, 'alquiler') !== false) {
                $motivo = 'Alquiler';
            }
            
            // Mapear tipo de procedencia
            $tipoProcedenciaRaw = isset($columnMap['Tipo procedencia']) ? strtolower(limpiarDato($row[$columnMap['Tipo procedencia']])) : '';
            $tipoProcedenciaId = null;
            
            foreach ($tiposProcedencia as $nombre => $id) {
                if (strpos($tipoProcedenciaRaw, $nombre) !== false) {
                    $tipoProcedenciaId = $id;
                    break;
                }
            }
            
            // Mapear motivo de cierre
            $motivoCierreRaw = isset($columnMap['Motivo cierre encargo']) ? strtolower(limpiarDato($row[$columnMap['Motivo cierre encargo']])) : '';
            $motivoCierreId = null;
            
            if ($motivoCierreRaw) {
                foreach ($motivosCierre as $nombre => $id) {
                    if (strpos($motivoCierreRaw, $nombre) !== false) {
                        $motivoCierreId = $id;
                        break;
                    }
                }
            }
            
            // Construir datos para inserción
            $encargoData = [
                ':encargo_id' => $encargoId,
                ':referencia' => $referencia,
                ':inmueble_id' => isset($columnMap['Inmueble: Inmueble Id.']) ? limpiarDato($row[$columnMap['Inmueble: Inmueble Id.']]) : null,
                ':propietario_id' => isset($columnMap['Propietario: Id. de la cuenta']) ? limpiarDato($row[$columnMap['Propietario: Id. de la cuenta']]) : null,
                ':estado_id' => $estadoId,
                ':estado_contacto_id' => $estadoContactoId,
                ':motivo' => $motivo,
                ':tipo_procedencia_id' => $tipoProcedenciaId,
                ':precio' => isset($columnMap['Precio encargo']) ? limpiarDato($row[$columnMap['Precio encargo']]) : null,
                ':precio_parking_incluido' => isset($columnMap['Precio con parking']) && $row[$columnMap['Precio con parking']] ? 1 : 0,
                ':valoracion' => isset($columnMap['Valoración']) ? limpiarDato($row[$columnMap['Valoración']]) : null,
                ':valoracion_solo_inmueble' => isset($columnMap['Valoración solo inm.']) ? limpiarDato($row[$columnMap['Valoración solo inm.']]) : null,
                ':nuda_propiedad' => isset($columnMap['Nuda propiedad']) && $row[$columnMap['Nuda propiedad']] ? 1 : 0,
                ':tratabilidad' => isset($columnMap['Tratabilidad %']) ? limpiarDato($row[$columnMap['Tratabilidad %']]) : null,
                ':fecha_creacion' => isset($columnMap['Fecha de creación']) ? formatearFecha($row[$columnMap['Fecha de creación']]) : formatearFecha(date('Y-m-d')),
                ':fecha_modificacion' => isset($columnMap['Fecha de la última modificación']) ? formatearFecha($row[$columnMap['Fecha de la última modificación']]) : formatearFecha(date('Y-m-d')),
                ':fecha_cierre' => null,  // No disponible en el Excel
                ':motivo_cierre_id' => $motivoCierreId,
                ':fecha_ultima_cita' => isset($columnMap['Fecha última cita gestión']) ? formatearFecha($row[$columnMap['Fecha última cita gestión']]) : null,
                ':fecha_ultima_actividad' => isset($columnMap['Última fecha de actividad']) ? formatearFecha($row[$columnMap['Última fecha de actividad']]) : null,
                ':fecha_ultimo_contacto' => isset($columnMap['Última fecha de contacto']) ? formatearFecha($row[$columnMap['Última fecha de contacto']]) : null,
                ':nota_privada' => isset($columnMap['Nota privada encargo']) ? limpiarDato($row[$columnMap['Nota privada encargo']]) : null,
                ':llaves_oficina' => isset($columnMap['Llaves disp. en oficina']) && $row[$columnMap['Llaves disp. en oficina']] ? 1 : 0
            ];
            
            // Ejecutar consulta
            $stmt->execute($encargoData);
            $importados++;
            
            // Informar progreso cada 100 registros
            if ($importados % 100 === 0) {
                echo "Procesados $importados registros de encargos...<br>";
                ob_flush();
                flush();
            }
        }
        
        echo "Importación de encargos completada. Total: $importados registros.<br>";
        return $importados;
        
    } catch (Exception $e) {
        echo "Error al importar encargos: " . $e->getMessage() . "<br>";
        return 0;
    }
}
