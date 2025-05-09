// Función para importar actividades
function importarActividades($pdo, $excelFile) {
    echo "Importando actividades desde $excelFile...<br>";
    
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
        
        // Obtener mapas de tipos de actividad, modalidades y prioridades
        $tiposActividad = [];
        $modalidades = [];
        $prioridades = [];
        
        $stmt = $pdo->query("SELECT tipo_actividad_id, nombre FROM tipos_actividad");
        while ($row = $stmt->fetch()) {
            $tiposActividad[strtolower($row['nombre'])] = $row['tipo_actividad_id'];
        }
        
        $stmt = $pdo->query("SELECT modalidad_contacto_id, nombre FROM modalidades_contacto");
        while ($row = $stmt->fetch()) {
            $modalidades[strtolower($row['nombre'])] = $row['modalidad_contacto_id'];
        }
        
        $stmt = $pdo->query("SELECT prioridad_id, nombre FROM prioridades");
        while ($row = $stmt->fetch()) {
            $prioridades[strtolower($row['nombre'])] = $row['prioridad_id'];
        }
        
        // Crear usuarios desde actividades
        $usuarios = [];
        $usuariosCreados = [];
        
        // Extraer usuarios asignados únicos
        foreach ($rows as $row) {
            if (isset($columnMap['Asignado']) && !empty($row[$columnMap['Asignado']])) {
                $usuario = limpiarDato($row[$columnMap['Asignado']]);
                if (!in_array($usuario, $usuarios)) {
                    $usuarios[] = $usuario;
                }
            }
        }
        
        // Crear usuarios
        $contadorUsuarios = 0;
        foreach ($usuarios as $usuario) {
            $usuarioId = 'USER' . str_pad(++$contadorUsuarios, 4, '0', STR_PAD_LEFT);
            
            // Dividir nombre y apellidos
            $nombrePartes = explode(' ', $usuario, 2);
            $nombre = $nombrePartes[0];
            $apellidos = isset($nombrePartes[1]) ? $nombrePartes[1] : '';
            
            // Insertar usuario
            $pdo->exec("INSERT INTO usuarios (
                            usuario_id, nombre, apellidos, nombre_completo, 
                            email, puesto, agencia_id, activo, fecha_alta
                        ) VALUES (
                            '$usuarioId', '$nombre', '$apellidos', '$usuario',
                            '" . strtolower(str_replace(' ', '.', $nombre)) . "@hogarfamiliar.es', 
                            'Agente Inmobiliario', 'mdbq3_es', 1, NOW()
                        ) ON DUPLICATE KEY UPDATE nombre_completo = VALUES(nombre_completo)");
            
            $usuariosCreados[$usuario] = $usuarioId;
        }
        
        echo "Creados " . count($usuariosCreados) . " usuarios.<br>";
        
        // Contador
        $importados = 0;
        $relacionesCreadas = 0;
        
        // Preparar consulta para actividades
        $sql = "INSERT INTO actividades (
                    actividad_id, referencia, usuario_id, cliente_id, 
                    asunto, tipo_actividad_id, modalidad_contacto_id, 
                    fecha, hora_inicio, hora_fin, prioridad_id, 
                    estado_id, descripcion, resultado, fecha_creacion
                ) VALUES (
                    :actividad_id, :referencia, :usuario_id, :cliente_id, 
                    :asunto, :tipo_actividad_id, :modalidad_contacto_id, 
                    :fecha, :hora_inicio, :hora_fin, :prioridad_id, 
                    :estado_id, :descripcion, :resultado, :fecha_creacion
                ) ON DUPLICATE KEY UPDATE
                    referencia = VALUES(referencia),
                    asunto = VALUES(asunto)";
        
        $stmt = $pdo->prepare($sql);
        
        // Preparar consulta para actividades_encargos
        $sqlRelacion = "INSERT INTO actividades_encargos (actividad_id, encargo_id) 
                        VALUES (:actividad_id, :encargo_id)
                        ON DUPLICATE KEY UPDATE actividad_id = VALUES(actividad_id)";
        
        $stmtRelacion = $pdo->prepare($sqlRelacion);
        
        // Procesar cada fila
        foreach ($rows as $idx => $row) {
            // Generar ID único para la actividad
            $actividadId = generarIdUnico();
            
            // Referencia para la actividad
            $fecha = isset($columnMap['Fecha']) ? formatearFecha($row[$columnMap['Fecha']]) : date('Y-m-d');
            $fechaCorta = is_string($fecha) ? substr($fecha, 0, 10) : date('Y-m-d');
            $referencia = "ACT-{$fechaCorta}-" . str_pad($idx + 1, 4, '0', STR_PAD_LEFT);
            
            // Extraer usuario asignado
            $usuarioAsignado = isset($columnMap['Asignado']) ? limpiarDato($row[$columnMap['Asignado']]) : null;
            $usuarioId = $usuarioAsignado ? ($usuariosCreados[$usuarioAsignado] ?? null) : null;
            
            // Buscar cliente por nombre
            $clienteNombre = isset($columnMap['Cliente']) ? limpiarDato($row[$columnMap['Cliente']]) : null;
            $clienteId = null;
            
            if ($clienteNombre) {
                // Intentar buscar por nombre exacto
                $stmtCliente = $pdo->prepare("SELECT cliente_id FROM clientes WHERE nombre_completo LIKE ? LIMIT 1");
                $stmtCliente->execute(["%" . $clienteNombre . "%"]);
                $resultCliente = $stmtCliente->fetch();
                
                if ($resultCliente) {
                    $clienteId = $resultCliente['cliente_id'];
                } else {
                    // Intentar buscar por nombre o apellidos
                    $nombrePartes = explode(' ', $clienteNombre);
                    if (count($nombrePartes) > 0) {
                        $stmtCliente = $pdo->prepare("SELECT cliente_id FROM clientes WHERE nombre LIKE ? OR apellidos LIKE ? LIMIT 1");
                        $stmtCliente->execute(["%" . $nombrePartes[0] . "%", "%" . end($nombrePartes) . "%"]);
                        $resultCliente = $stmtCliente->fetch();
                        
                        if ($resultCliente) {
                            $clienteId = $resultCliente['cliente_id'];
                        }
                    }
                }
            }
            
            // Mapear tipo de actividad
            $tipoActividadRaw = isset($columnMap['Tipo actividad']) ? strtolower(limpiarDato($row[$columnMap['Tipo actividad']])) : '';
            $tipoActividadId = null;
            
            foreach ($tiposActividad as $nombre => $id) {
                if (strpos($tipoActividadRaw, $nombre) !== false) {
                    $tipoActividadId = $id;
                    break;
                }
            }
            
            // Mapear modalidad de contacto
            $modalidadRaw = isset($columnMap['Modalidad contacto']) ? strtolower(limpiarDato($row[$columnMap['Modalidad contacto']])) : '';
            $modalidadId = null;
            
            foreach ($modalidades as $nombre => $id) {
                if (strpos($modalidadRaw, $nombre) !== false) {
                    $modalidadId = $id;
                    break;
                }
            }
            
            // Extraer horas
            $horaInicio = null;
            $horaFin = null;
            
            // Extraer desde fecha y hora de inicio
            $fechaHoraInicio = isset($columnMap['Fecha y hora de inicio']) ? limpiarDato($row[$columnMap['Fecha y hora de inicio']]) : null;
            if ($fechaHoraInicio && is_string($fechaHoraInicio) && strpos($fechaHoraInicio, ',') !== false) {
                $partes = explode(',', $fechaHoraInicio);
                if (count($partes) > 1) {
                    $horaInicio = trim($partes[1]);
                    // Convertir a formato HH:MM:SS
                    if (strpos($horaInicio, ':') !== false) {
                        $horaPartes = explode(':', $horaInicio);
                        $horaInicio = str_pad($horaPartes[0], 2, '0', STR_PAD_LEFT) . ':' . 
                                     str_pad($horaPartes[1], 2, '0', STR_PAD_LEFT) . ':00';
                    }
                }
            }
            
            // Si no hay hora de inicio, usar "Hora de vencimiento"
            if (!$horaInicio && isset($columnMap['Hora de vencimiento'])) {
                $horaInicio = limpiarDato($row[$columnMap['Hora de vencimiento']]);
                // Convertir a formato HH:MM:SS
                if ($horaInicio && strpos($horaInicio, ':') !== false) {
                    $horaPartes = explode(':', $horaInicio);
                    $horaInicio = str_pad($horaPartes[0], 2, '0', STR_PAD_LEFT) . ':' . 
                                 str_pad($horaPartes[1], 2, '0', STR_PAD_LEFT) . ':00';
                }
            }
            
            // Extraer desde fecha y hora de finalización
            $fechaHoraFin = isset($columnMap['Fecha y hora de finalización']) ? limpiarDato($row[$columnMap['Fecha y hora de finalización']]) : null;
            if ($fechaHoraFin && is_string($fechaHoraFin) && strpos($fechaHoraFin, ',') !== false) {
                $partes = explode(',', $fechaHoraFin);
                if (count($partes) > 1) {
                    $horaFin = trim($partes[1]);
                    // Convertir a formato HH:MM:SS
                    if (strpos($horaFin, ':') !== false) {
                        $horaPartes = explode(':', $horaFin);
                        $horaFin = str_pad($horaPartes[0], 2, '0', STR_PAD_LEFT) . ':' . 
                                  str_pad($horaPartes[1], 2, '0', STR_PAD_LEFT) . ':00';
                    }
                }
            }
            
            // Mapear prioridad
            $prioridadRaw = isset($columnMap['Prioridad']) ? strtolower(limpiarDato($row[$columnMap['Prioridad']])) : '';
            $prioridadId = null;
            
            foreach ($prioridades as $nombre => $id) {
                if (strpos($prioridadRaw, $nombre) !== false) {
                    $prioridadId = $id;
                    break;
                }
            }
            
            // Extraer resultado
            $resultado = null;
            if (isset($columnMap['Tipo de llamada']) && isset($columnMap['Resultado de la llamada']) &&
                !empty($row[$columnMap['Tipo de llamada']]) && !empty($row[$columnMap['Resultado de la llamada']])) {
                $resultado = "Tipo: " . $row[$columnMap['Tipo de llamada']] . " - Resultado: " . $row[$columnMap['Resultado de la llamada']];
            }
            
            // Construir datos para inserción
            $actividadData = [
                ':actividad_id' => $actividadId,
                ':referencia' => $referencia,
                ':usuario_id' => $usuarioId,
                ':cliente_id' => $clienteId,
                ':asunto' => isset($columnMap['Asunto']) ? limpiarDato($row[$columnMap['Asunto']]) : '',
                ':tipo_actividad_id' => $tipoActividadId,
                ':modalidad_contacto_id' => $modalidadId,
                ':fecha' => $fechaCorta,
                ':hora_inicio' => $horaInicio,
                ':hora_fin' => $horaFin,
                ':prioridad_id' => $prioridadId,
                ':estado_id' => 1,  // Por defecto "Completada"
                ':descripcion' => null,
                ':resultado' => $resultado,
                ':fecha_creacion' => formatearFecha(date('Y-m-d H:i:s'))
            ];
            
            // Ejecutar consulta
            $stmt->execute($actividadData);
            $importados++;
            
            // Relacionar con encargos si hay referencias
            for ($i = 1; $i <= 4; $i++) {
                if (isset($columnMap["Encargo$i"]) && !empty($row[$columnMap["Encargo$i"]])) {
                    $encargoId = limpiarDato($row[$columnMap["Encargo$i"]]);
                    
                    // Verificar que el encargo existe
                    $stmtEncargo = $pdo->prepare("SELECT COUNT(*) FROM encargos WHERE encargo_id = ?");
                    $stmtEncargo->execute([$encargoId]);
                    
                    if ($stmtEncargo->fetchColumn() > 0) {
                        $stmtRelacion->execute([
                            ':actividad_id' => $actividadId,
                            ':encargo_id' => $encargoId
                        ]);
                        $relacionesCreadas++;
                    }
                }
            }
            
            // Informar progreso cada 100 registros
            if ($importados % 100 === 0) {
                echo "Procesados $importados registros de actividades...<br>";
                ob_flush();
                flush();
            }
        }
        
        echo "Importación de actividades completada. Total: $importados registros.<br>";
        echo "Relaciones actividades-encargos creadas: $relacionesCreadas.<br>";
        return $importados;
        
    } catch (Exception $e) {
        echo "Error al importar actividades: " . $e->getMessage() . "<br>";
        return 0;
    }
}