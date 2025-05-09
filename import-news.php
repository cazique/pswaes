// Función para importar noticias
function importarNoticias($pdo, $excelFile) {
    echo "Importando noticias desde $excelFile...<br>";
    
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
        $estadosNoticia = [];
        $estadosContacto = [];
        $tiposProcedencia = [];
        $detallesProcedencia = [];
        $motivosCierre = [];
        $usuarios = [];
        
        $stmt = $pdo->query("SELECT estado_noticia_id, nombre FROM estados_noticia");
        while ($row = $stmt->fetch()) {
            $estadosNoticia[strtolower($row['nombre'])] = $row['estado_noticia_id'];
        }
        
        $stmt = $pdo->query("SELECT estado_contacto_id, nombre FROM estados_contacto");
        while ($row = $stmt->fetch()) {
            $estadosContacto[strtolower($row['nombre'])] = $row['estado_contacto_id'];
        }
        
        $stmt = $pdo->query("SELECT tipo_procedencia_id, nombre FROM tipos_procedencia");
        while ($row = $stmt->fetch()) {
            $tiposProcedencia[strtolower($row['nombre'])] = $row['tipo_procedencia_id'];
        }
        
        $stmt = $pdo->query("SELECT detalle_procedencia_id, tipo_procedencia_id, nombre FROM detalles_procedencia");
        while ($row = $stmt->fetch()) {
            $detallesProcedencia[$row['tipo_procedencia_id'] . '-' . strtolower($row['nombre'])] = $row['detalle_procedencia_id'];
        }
        
        $stmt = $pdo->query("SELECT motivo_cierre_id, nombre FROM motivos_cierre");
        while ($row = $stmt->fetch()) {
            $motivosCierre[strtolower($row['nombre'])] = $row['motivo_cierre_id'];
        }
        
        $stmt = $pdo->query("SELECT usuario_id, nombre_completo FROM usuarios");
        while ($row = $stmt->fetch()) {
            $usuarios[strtolower($row['nombre_completo'])] = $row['usuario_id'];
        }
        
        // Contador
        $importados = 0;
        
        // Preparar consulta
        $sql = "INSERT INTO noticias (
                    noticia_id, referencia, inmueble_id, cliente_id,
                    colaborador_id, estado_noticia_id, estado_contacto_id,
                    tipo_procedencia_id, detalle_procedencia_id, motivacion,
                    valoracion, valoracion_solo_inmueble, precio_pedido,
                    fecha_valoracion, fecha_estimacion_interna, fecha_ultima_cita,
                    fecha_ultimo_contacto, fecha_cierre, motivo_cierre_id,
                    nota, fecha_creacion
                ) VALUES (
                    :noticia_id, :referencia, :inmueble_id, :cliente_id,
                    :colaborador_id, :estado_noticia_id, :estado_contacto_id,
                    :tipo_procedencia_id, :detalle_procedencia_id, :motivacion,
                    :valoracion, :valoracion_solo_inmueble, :precio_pedido,
                    :fecha_valoracion, :fecha_estimacion_interna, :fecha_ultima_cita,
                    :fecha_ultimo_contacto, :fecha_cierre, :motivo_cierre_id,
                    :nota, :fecha_creacion
                ) ON DUPLICATE KEY UPDATE
                    referencia = VALUES(referencia),
                    estado_noticia_id = VALUES(estado_noticia_id)";
        
        $stmt = $pdo->prepare($sql);
        
        // Procesar cada fila
        foreach ($rows as $row) {
            // Extraer noticia_id o generar uno nuevo
            $noticiaId = isset($columnMap['Noticia: Id.']) && !empty($row[$columnMap['Noticia: Id.']]) 
                ? $row[$columnMap['Noticia: Id.']] 
                : generarIdUnico();
            
            // Extraer referencia
            $noticiaDesc = isset($columnMap['Noticia: Noticia']) ? limpiarDato($row[$columnMap['Noticia: Noticia']]) : '';
            $referencia = $noticiaDesc ? substr($noticiaDesc, 0, 30) : "NOT-" . substr($noticiaId, 0, 8);
            
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
                        } else {
                            // Si no se encuentra, crear un nuevo cliente
                            $nuevoClienteId = generarIdUnico();
                            
                            // Dividir nombre y apellidos
                            $nombrePartes = explode(' ', $clienteNombre, 2);
                            $nombre = $nombrePartes[0];
                            $apellidos = isset($nombrePartes[1]) ? $nombrePartes[1] : '';
                            
                            $sqlNuevoCliente = "INSERT INTO clientes (
                                                cliente_id, nombre, apellidos, nombre_completo,
                                                telefono_fijo, telefono_movil, fecha_alta, agencia_id
                                            ) VALUES (
                                                :cliente_id, :nombre, :apellidos, :nombre_completo,
                                                :telefono_fijo, :telefono_movil, NOW(), :agencia_id
                                            )";
                            
                            $stmtNuevoCliente = $pdo->prepare($sqlNuevoCliente);
                            $stmtNuevoCliente->execute([
                                ':cliente_id' => $nuevoClienteId,
                                ':nombre' => $nombre,
                                ':apellidos' => $apellidos,
                                ':nombre_completo' => $clienteNombre,
                                ':telefono_fijo' => isset($columnMap['Tel. propietario']) ? limpiarDato($row[$columnMap['Tel. propietario']]) : null,
                                ':telefono_movil' => isset($columnMap['Telefono Movil']) ? limpiarDato($row[$columnMap['Telefono Movil']]) : null,
                                ':agencia_id' => 'mdbq3_es'
                            ]);
                            
                            $clienteId = $nuevoClienteId;
                            
                            echo "Creado nuevo cliente: $clienteNombre ($clienteId)<br>";
                        }
                    }
                }
            }
            
            // Si no hay cliente, pasar al siguiente registro
            if (!$clienteId) {
                echo "No se pudo encontrar o crear cliente para la noticia: $noticiaId. Omitiendo registro.<br>";
                continue;
            }
            
            // Buscar colaborador
            $colaboradorNombre = isset($columnMap['Colaborador']) ? limpiarDato($row[$columnMap['Colaborador']]) : null;
            $colaboradorId = null;
            
            if ($colaboradorNombre) {
                $colaboradorNombreLower = strtolower($colaboradorNombre);
                
                // Buscar por nombre completo
                if (isset($usuarios[$colaboradorNombreLower])) {
                    $colaboradorId = $usuarios[$colaboradorNombreLower];
                } else {
                    // Buscar parcial
                    foreach ($usuarios as $nombre => $id) {
                        if (strpos($nombre, $colaboradorNombreLower) !== false || strpos($colaboradorNombreLower, $nombre) !== false) {
                            $colaboradorId = $id;
                            break;
                        }
                    }
                }
            }
            
            // Mapear estado de noticia
            $estadoRaw = isset($columnMap['Estado noticia']) ? strtolower(limpiarDato($row[$columnMap['Estado noticia']])) : '';
            $estadoNoticiaId = 1;  // Por defecto "Nueva"
            
            foreach ($estadosNoticia as $nombre => $id) {
                if (strpos($estadoRaw, $nombre) !== false) {
                    $estadoNoticiaId = $id;
                    break;
                }
            }
            
            // Mapear estado de contacto
            $estadoContactoRaw = isset($columnMap['Estado contacto']) ? strtolower(limpiarDato($row[$columnMap['Estado contacto']])) : '';
            $estadoContactoId = null;
            
            foreach ($estadosContacto as $nombre => $id) {
                if (strpos($estadoContactoRaw, $nombre) !== false) {
                    $estadoContactoId = $id;
                    break;
                }
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
            
            // Mapear detalle de procedencia
            $detalleProcedenciaRaw = isset($columnMap['Detalle procedencia']) ? limpiarDato($row[$columnMap['Detalle procedencia']]) : '';
            if (!$detalleProcedenciaRaw && isset($columnMap['Detalle procedencia directa'])) {
                $detalleProcedenciaRaw = limpiarDato($row[$columnMap['Detalle procedencia directa']]);
            }
            
            $detalleProcedenciaId = null;
            
            if ($detalleProcedenciaRaw && $tipoProcedenciaId) {
                $detalleProcedenciaRawLower = strtolower($detalleProcedenciaRaw);
                
                // Buscar por clave compuesta
                foreach ($detallesProcedencia as $clave => $id) {
                    list($tipoId, $nombreDetalle) = explode('-', $clave);
                    
                    if ($tipoId == $tipoProcedenciaId && strpos($detalleProcedenciaRawLower, $nombreDetalle) !== false) {
                        $detalleProcedenciaId = $id;
                        break;
                    }
                }
            }
            
            // Determinar motivación
            $motivacionRaw = isset($columnMap['Motivación']) ? strtolower(limpiarDato($row[$columnMap['Motivación']])) : '';
            $motivacion = 'Vender';  // Valor por defecto
            
            if (strpos($motivacionRaw, 'alquil') !== false) {
                $motivacion = 'Alquilar';
            }
            
            // Mapear motivo de cierre
            $motivoCierreRaw = isset($columnMap['Motivo cierre noticia']) ? strtolower(limpiarDato($row[$columnMap['Motivo cierre noticia']])) : '';
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
            $noticiaData = [
                ':noticia_id' => $noticiaId,
                ':referencia' => $referencia,
                ':inmueble_id' => isset($columnMap['Inmueble: Id.']) ? limpiarDato($row[$columnMap['Inmueble: Id.']]) : null,
                ':cliente_id' => $clienteId,
                ':colaborador_id' => $colaboradorId,
                ':estado_noticia_id' => $estadoNoticiaId,
                ':estado_contacto_id' => $estadoContactoId,
                ':tipo_procedencia_id' => $tipoProcedenciaId,
                ':detalle_procedencia_id' => $detalleProcedenciaId,
                ':motivacion' => $motivacion,
                ':valoracion' => isset($columnMap['Valoración']) ? limpiarDato($row[$columnMap['Valoración']]) : null,
                ':valoracion_solo_inmueble' => isset($columnMap['Valoración solo inmueble']) ? limpiarDato($row[$columnMap['Valoración solo inmueble']]) : null,
                ':precio_pedido' => isset($columnMap['Precio pedido por el cliente']) ? limpiarDato($row[$columnMap['Precio pedido por el cliente']]) : null,
                ':fecha_valoracion' => isset($columnMap['Fecha valoración']) ? formatearFecha($row[$columnMap['Fecha valoración']]) : null,
                ':fecha_estimacion_interna' => isset($columnMap['Fecha estimacion interna']) ? formatearFecha($row[$columnMap['Fecha estimacion interna']]) : null,
                ':fecha_ultima_cita' => isset($columnMap['Fecha ultima cita realizada']) ? formatearFecha($row[$columnMap['Fecha ultima cita realizada']]) : null,
                ':fecha_ultimo_contacto' => isset($columnMap['Última fecha de contacto']) ? formatearFecha($row[$columnMap['Última fecha de contacto']]) : null,
                ':fecha_cierre' => isset($columnMap['Fecha cierre noticia']) ? formatearFecha($row[$columnMap['Fecha cierre noticia']]) : null,
                ':motivo_cierre_id' => $motivoCierreId,
                ':nota' => isset($columnMap['Nota noticia']) ? limpiarDato($row[$columnMap['Nota noticia']]) : null,
                ':fecha_creacion' => formatearFecha(date('Y-m-d H:i:s'))
            ];
            
            // Ejecutar consulta
            $stmt->execute($noticiaData);
            $importados++;
            
            // Informar progreso cada 100 registros
            if ($importados % 100 === 0) {
                echo "Procesados $importados registros de noticias...<br>";
                ob_flush();
                flush();
            }
        }
        
        echo "Importación de noticias completada. Total: $importados registros.<br>";
        return $importados;
        
    } catch (Exception $e) {
        echo "Error al importar noticias: " . $e->getMessage() . "<br>";
        return 0;
    }
}