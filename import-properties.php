// Función para importar inmuebles
function importarInmuebles($pdo, $excelFile) {
    echo "Importando inmuebles desde $excelFile...<br>";
    
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
        
        // Obtener mapas de tipologías y zonas
        $tipologias = [];
        $zonas = [];
        
        $stmt = $pdo->query("SELECT tipologia_id, nombre FROM tipologias_inmueble");
        while ($row = $stmt->fetch()) {
            $tipologias[strtolower($row['nombre'])] = $row['tipologia_id'];
        }
        
        $stmt = $pdo->query("SELECT zona_id, nombre FROM zonas");
        while ($row = $stmt->fetch()) {
            $zonas[strtolower($row['nombre'])] = $row['zona_id'];
        }
        
        // Contador
        $importados = 0;
        
        // Preparar consulta
        $sql = "INSERT INTO inmuebles (
                    inmueble_id, referencia, propietario_id, estado, 
                    tipologia_id, zona_id, dormitorios, banos, 
                    m2_utiles, m2_construidos, altura, 
                    ano_construccion, ano_reforma, ascensor, 
                    terraza, balcon, garaje, trastero, 
                    calefaccion_tipo, climatizacion, certificado_energetico, 
                    referencia_catastral, fecha_alta, llaves_oficina
                ) VALUES (
                    :inmueble_id, :referencia, :propietario_id, :estado, 
                    :tipologia_id, :zona_id, :dormitorios, :banos, 
                    :m2_utiles, :m2_construidos, :altura, 
                    :ano_construccion, :ano_reforma, :ascensor, 
                    :terraza, :balcon, :garaje, :trastero, 
                    :calefaccion_tipo, :climatizacion, :certificado_energetico, 
                    :referencia_catastral, NOW(), :llaves_oficina
                ) ON DUPLICATE KEY UPDATE
                    referencia = VALUES(referencia),
                    estado = VALUES(estado)";
        
        $stmt = $pdo->prepare($sql);
        
        // Procesar cada fila
        foreach ($rows as $row) {
            // Extraer inmueble_id o generar uno nuevo
            $inmuebleId = isset($columnMap['Inmueble: Id.']) && !empty($row[$columnMap['Inmueble: Id.']]) 
                ? $row[$columnMap['Inmueble: Id.']] 
                : generarIdUnico();
            
            // Extraer referencia
            $inmuebleDesc = isset($columnMap['Inmueble: Inmueble']) ? limpiarDato($row[$columnMap['Inmueble: Inmueble']]) : '';
            $referencia = $inmuebleDesc ? substr($inmuebleDesc, 0, 30) : "INM-" . substr($inmuebleId, 0, 8);
            
            // Mapear estado
            $estadoRaw = isset($columnMap['Estado']) ? strtolower(limpiarDato($row[$columnMap['Estado']])) : 'disponible';
            $estado = 'Disponible';  // valor por defecto
            
            if (strpos($estadoRaw, 'vendido') !== false) {
                $estado = 'Vendido';
            } elseif (strpos($estadoRaw, 'alquilado') !== false) {
                $estado = 'Alquilado';
            } elseif (strpos($estadoRaw, 'reservado') !== false) {
                $estado = 'Reservado';
            } elseif (strpos($estadoRaw, 'inactivo') !== false) {
                $estado = 'Inactivo';
            }
            
            // Mapear tipología
            $tipologiaRaw = isset($columnMap['Tipología inmueble']) ? strtolower(limpiarDato($row[$columnMap['Tipología inmueble']])) : '';
            $tipologiaId = null;
            
            foreach ($tipologias as $nombre => $id) {
                if (strpos($tipologiaRaw, $nombre) !== false) {
                    $tipologiaId = $id;
                    break;
                }
            }
            
            // Mapear zona
            $zonaRaw = isset($columnMap['Zona']) ? strtolower(limpiarDato($row[$columnMap['Zona']])) : '';
            $zonaId = null;
            
            foreach ($zonas as $nombre => $id) {
                if (strpos($zonaRaw, $nombre) !== false) {
                    $zonaId = $id;
                    break;
                }
            }
            
            // Construir datos para inserción
            $inmuebleData = [
                ':inmueble_id' => $inmuebleId,
                ':referencia' => $referencia,
                ':propietario_id' => isset($columnMap['ProprietarioID']) ? limpiarDato($row[$columnMap['ProprietarioID']]) : null,
                ':estado' => $estado,
                ':tipologia_id' => $tipologiaId,
                ':zona_id' => $zonaId,
                ':dormitorios' => isset($columnMap['Dormitorios']) ? limpiarDato($row[$columnMap['Dormitorios']]) : null,
                ':banos' => isset($columnMap['Baños']) ? limpiarDato($row[$columnMap['Baños']]) : null,
                ':m2_utiles' => isset($columnMap['M2 útiles']) ? limpiarDato($row[$columnMap['M2 útiles']]) : null,
                ':m2_construidos' => isset($columnMap['M2 construidos']) ? limpiarDato($row[$columnMap['M2 construidos']]) : null,
                ':altura' => isset($columnMap['Planta']) ? limpiarDato($row[$columnMap['Planta']]) : 
                           (isset($columnMap['Num. planta']) ? limpiarDato($row[$columnMap['Num. planta']]) : null),
                ':ano_construccion' => isset($columnMap['Año construcción']) ? limpiarDato($row[$columnMap['Año construcción']]) : null,
                ':ano_reforma' => isset($columnMap['Año reforma']) ? limpiarDato($row[$columnMap['Año reforma']]) : null,
                ':ascensor' => isset($columnMap['Ascensor']) && $row[$columnMap['Ascensor']] ? 1 : 0,
                ':terraza' => isset($columnMap['Terraza']) && $row[$columnMap['Terraza']] ? 1 : 0,
                ':balcon' => isset($columnMap['Balcón']) && $row[$columnMap['Balcón']] ? 1 : 0,
                ':garaje' => isset($columnMap['Parking']) && $row[$columnMap['Parking']] ? 1 : 0,
                ':trastero' => isset($columnMap['Sótano']) && $row[$columnMap['Sótano']] ? 1 : 0,
                ':calefaccion_tipo' => isset($columnMap['Calefacción']) ? limpiarDato($row[$columnMap['Calefacción']]) : 
                                     (isset($columnMap['Sistema de calefacción']) ? limpiarDato($row[$columnMap['Sistema de calefacción']]) : null),
                ':climatizacion' => isset($columnMap['Climatizzazione Invernale']) ? limpiarDato($row[$columnMap['Climatizzazione Invernale']]) : 
                                  (isset($columnMap['Climatizzazione Estiva']) ? limpiarDato($row[$columnMap['Climatizzazione Estiva']]) : null),
                ':certificado_energetico' => isset($columnMap['Certificazione Energetica']) ? limpiarDato($row[$columnMap['Certificazione Energetica']]) : null,
                ':referencia_catastral' => isset($columnMap['Identificativo catastral']) ? limpiarDato($row[$columnMap['Identificativo catastral']]) : null,
                ':llaves_oficina' => isset($columnMap['Llaves disp. en oficina']) && $row[$columnMap['Llaves disp. en oficina']] ? 1 : 0
            ];
            
            // Ejecutar consulta
            $stmt->execute($inmuebleData);
            $importados++;
            
            // Procesar dirección del inmueble
            $direccionCompleta = isset($columnMap['Dirección completa inmueble']) ? limpiarDato($row[$columnMap['Dirección completa inmueble']]) : '';
            
            if ($direccionCompleta) {
                // Intentar extraer partes de la dirección con expresión regular
                preg_match('/(.+?)(?:\s+(\d+))?(?:\s+\[([^\]]+)\])?/', $direccionCompleta, $matches);
                
                $calle = isset($matches[1]) ? trim($matches[1]) : $direccionCompleta;
                $numero = isset($matches[2]) ? $matches[2] : '';
                $pisoPuerta = isset($matches[3]) ? $matches[3] : '';
                
                // Dividir piso/puerta si es posible
                $piso = '';
                $puerta = '';
                
                if ($pisoPuerta) {
                    if (strpos($pisoPuerta, 'º') !== false) {
                        $partes = explode('º', $pisoPuerta, 2);
                        $piso = $partes[0] . 'º';
                        $puerta = isset($partes[1]) ? trim($partes[1]) : '';
                    } else {
                        $puerta = $pisoPuerta;
                    }
                }
                
                // Insertar dirección
                $sqlDir = "INSERT INTO direcciones (
                            inmueble_id, tipo_direccion, calle, numero, piso, puerta,
                            codigo_postal, localidad, provincia, pais,
                            coordenadas_latitud, coordenadas_longitud, principal, fecha_alta
                        ) VALUES (
                            :inmueble_id, 'Inmueble', :calle, :numero, :piso, :puerta,
                            :codigo_postal, :localidad, :provincia, :pais,
                            :coordenadas_latitud, :coordenadas_longitud, 1, NOW()
                        )";
                
                $stmtDir = $pdo->prepare($sqlDir);
                $stmtDir->execute([
                    ':inmueble_id' => $inmuebleId,
                    ':calle' => $calle,
                    ':numero' => $numero,
                    ':piso' => $piso,
                    ':puerta' => $puerta,
                    ':codigo_postal' => isset($columnMap['Código postal']) ? limpiarDato($row[$columnMap['Código postal']]) : null,
                    ':localidad' => isset($columnMap['Localidad']) ? limpiarDato($row[$columnMap['Localidad']]) : 
                                   (isset($columnMap['Localidad geográfica']) ? limpiarDato($row[$columnMap['Localidad geográfica']]) : 'San Fernando de Henares'),
                    ':provincia' => 'Madrid',
                    ':pais' => 'Espana',
                    ':coordenadas_latitud' => isset($columnMap['Coordenadas (Latitud)']) ? limpiarDato($row[$columnMap['Coordenadas (Latitud)']]) : null,
                    ':coordenadas_longitud' => isset($columnMap['Coordenadas (Longitud)']) ? limpiarDato($row[$columnMap['Coordenadas (Longitud)']]) : null
                ]);
                
                // Obtener el ID de la dirección insertada
                $direccionId = $pdo->lastInsertId();
                
                // Actualizar el inmueble con el ID de la dirección
                $pdo->exec("UPDATE inmuebles SET direccion_id = $direccionId WHERE inmueble_id = '$inmuebleId'");
            }
            
            // Informar progreso cada 100 registros
            if ($importados % 100 === 0) {
                echo "Procesados $importados registros de inmuebles...<br>";
                ob_flush();
                flush();
            }
        }
        
        echo "Importación de inmuebles completada. Total: $importados registros.<br>";
        return $importados;
        
    } catch (Exception $e) {
        echo "Error al importar inmuebles: " . $e->getMessage() . "<br>";
        return 0;
    }
}