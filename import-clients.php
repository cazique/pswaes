// Función para importar clientes
function importarClientes($pdo, $excelFile) {
    echo "Importando clientes desde $excelFile...<br>";
    
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
        
        // Contador
        $importados = 0;
        
        // Preparar consulta
        $sql = "INSERT INTO clientes (
                    cliente_id, nombre, apellidos, nombre_completo, tratamiento, genero, 
                    fecha_nacimiento, documento_id, nacionalidad, telefono_fijo, 
                    telefono_movil, email, cliente_telefonico, profesion, 
                    fecha_alta, agencia_id
                ) VALUES (
                    :cliente_id, :nombre, :apellidos, :nombre_completo, :tratamiento, :genero,
                    :fecha_nacimiento, :documento_id, :nacionalidad, :telefono_fijo,
                    :telefono_movil, :email, :cliente_telefonico, :profesion,
                    NOW(), :agencia_id
                ) ON DUPLICATE KEY UPDATE
                    nombre = VALUES(nombre),
                    apellidos = VALUES(apellidos),
                    nombre_completo = VALUES(nombre_completo)";
        
        $stmt = $pdo->prepare($sql);
        
        // Procesar cada fila
        foreach ($rows as $row) {
            // Extraer cliente_id o generar uno nuevo
            $clienteId = isset($columnMap['Id. de la cuenta']) && !empty($row[$columnMap['Id. de la cuenta']]) 
                ? $row[$columnMap['Id. de la cuenta']] 
                : generarIdUnico();
            
            // Extraer nombre y apellidos
            $nombreCompleto = isset($columnMap['Cliente']) ? limpiarDato($row[$columnMap['Cliente']]) : '';
            $nombrePartes = explode(' ', $nombreCompleto, 2);
            $nombre = isset($columnMap['Nombre']) && !empty($row[$columnMap['Nombre']]) 
                ? $row[$columnMap['Nombre']] 
                : (isset($nombrePartes[0]) ? $nombrePartes[0] : '');
            $apellidos = isset($columnMap['Apellido']) && !empty($row[$columnMap['Apellido']]) 
                ? $row[$columnMap['Apellido']] 
                : (isset($nombrePartes[1]) ? $nombrePartes[1] : '');
            
            // Si tenemos nombre y apellidos pero no nombre completo, lo generamos
            if ($nombre && $apellidos && !$nombreCompleto) {
                $nombreCompleto = $nombre . ' ' . $apellidos;
            }
            
            // Construir datos para inserción
            $clienteData = [
                ':cliente_id' => $clienteId,
                ':nombre' => $nombre,
                ':apellidos' => $apellidos,
                ':nombre_completo' => $nombreCompleto,
                ':tratamiento' => isset($columnMap['Tratamiento']) ? limpiarDato($row[$columnMap['Tratamiento']]) : null,
                ':genero' => isset($columnMap['Sesso']) ? limpiarDato($row[$columnMap['Sesso']]) : null,
                ':fecha_nacimiento' => isset($columnMap['Data di nascita']) ? formatearFecha($row[$columnMap['Data di nascita']]) : null,
                ':documento_id' => isset($columnMap['Codice Fiscale']) ? limpiarDato($row[$columnMap['Codice Fiscale']]) : null,
                ':nacionalidad' => isset($columnMap['Cittadinanza']) ? limpiarDato($row[$columnMap['Cittadinanza']]) : null,
                ':telefono_fijo' => isset($columnMap['Teléfono']) ? limpiarDato($row[$columnMap['Teléfono']]) : null,
                ':telefono_movil' => isset($columnMap['Móvil']) ? limpiarDato($row[$columnMap['Móvil']]) : null,
                ':email' => isset($columnMap['Email']) ? limpiarDato($row[$columnMap['Email']]) : null,
                ':cliente_telefonico' => isset($columnMap['Cliente teléfonico']) && $row[$columnMap['Cliente teléfonico']] ? 1 : 0,
                ':profesion' => isset($columnMap['Professione']) ? limpiarDato($row[$columnMap['Professione']]) : 
                              (isset($columnMap['Cliente: Profesión']) ? limpiarDato($row[$columnMap['Cliente: Profesión']]) : null),
                ':agencia_id' => 'mdbq3_es'
            ];
            
            // Ejecutar consulta
            $stmt->execute($clienteData);
            $importados++;
            
            // Procesar direcciones del cliente
            if ((isset($columnMap['Indirizzo residenza']) && !empty($row[$columnMap['Indirizzo residenza']])) ||
                (isset($columnMap['Indirizzo corrispondenza']) && !empty($row[$columnMap['Indirizzo corrispondenza']]))) {
                
                // Insertar dirección de residencia
                if (isset($columnMap['Indirizzo residenza']) && !empty($row[$columnMap['Indirizzo residenza']])) {
                    $sqlDir = "INSERT INTO direcciones (
                                cliente_id, tipo_direccion, calle, numero, codigo_postal, 
                                localidad, provincia, pais, principal, fecha_alta
                            ) VALUES (
                                :cliente_id, 'Residencia', :calle, :numero, :codigo_postal,
                                :localidad, :provincia, :pais, 1, NOW()
                            )";
                    
                    $stmtDir = $pdo->prepare($sqlDir);
                    $stmtDir->execute([
                        ':cliente_id' => $clienteId,
                        ':calle' => limpiarDato($row[$columnMap['Indirizzo residenza']]),
                        ':numero' => isset($columnMap['Numero Civico residenza']) ? limpiarDato($row[$columnMap['Numero Civico residenza']]) : null,
                        ':codigo_postal' => isset($columnMap['CAP residenza']) ? limpiarDato($row[$columnMap['CAP residenza']]) : null,
                        ':localidad' => isset($columnMap['Località residenza']) ? limpiarDato($row[$columnMap['Località residenza']]) : null,
                        ':provincia' => isset($columnMap['Provincia residenza']) ? limpiarDato($row[$columnMap['Provincia residenza']]) : null,
                        ':pais' => isset($columnMap['Nazione residenza']) ? limpiarDato($row[$columnMap['Nazione residenza']]) : 'Espana'
                    ]);
                }
                
                // Insertar dirección de correspondencia
                if (isset($columnMap['Indirizzo corrispondenza']) && !empty($row[$columnMap['Indirizzo corrispondenza']])) {
                    $sqlDir = "INSERT INTO direcciones (
                                cliente_id, tipo_direccion, calle, numero, codigo_postal, 
                                localidad, provincia, pais, principal, fecha_alta
                            ) VALUES (
                                :cliente_id, 'Correspondencia', :calle, :numero, :codigo_postal,
                                :localidad, :provincia, :pais, 0, NOW()
                            )";
                    
                    $stmtDir = $pdo->prepare($sqlDir);
                    $stmtDir->execute([
                        ':cliente_id' => $clienteId,
                        ':calle' => limpiarDato($row[$columnMap['Indirizzo corrispondenza']]),
                        ':numero' => isset($columnMap['Numero Civico corrispondenza']) ? limpiarDato($row[$columnMap['Numero Civico corrispondenza']]) : null,
                        ':codigo_postal' => isset($columnMap['CAP corrispondenza']) ? limpiarDato($row[$columnMap['CAP corrispondenza']]) : null,
                        ':localidad' => isset($columnMap['Località corrispondenza']) ? limpiarDato($row[$columnMap['Località corrispondenza']]) : null,
                        ':provincia' => isset($columnMap['Provincia corrispondenza']) ? limpiarDato($row[$columnMap['Provincia corrispondenza']]) : null,
                        ':pais' => isset($columnMap['Nazione corrispondenza']) ? limpiarDato($row[$columnMap['Nazione corrispondenza']]) : 'Espana'
                    ]);
                }
            }
            
            // Informar progreso cada 100 registros
            if ($importados % 100 === 0) {
                echo "Procesados $importados registros de clientes...<br>";
                ob_flush();
                flush();
            }
        }
        
        echo "Importación de clientes completada. Total: $importados registros.<br>";
        return $importados;
        
    } catch (Exception $e) {
        echo "Error al importar clientes: " . $e->getMessage() . "<br>";
        return 0;
    }
}
