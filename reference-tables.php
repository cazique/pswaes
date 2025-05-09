// Preparar tablas de referencia
function prepararTablasReferencia($pdo) {
    echo "Preparando tablas de referencia...<br>";
    
    // Agencia principal
    $pdo->exec("INSERT INTO agencias (agencia_id, nombre, direccion, codigo_postal, localidad, provincia, telefono, email, fecha_alta) 
                VALUES ('mdbq3_es', 'Hogar Familiar Inmobiliaria', 'Calle Principal 5', '28830', 'San Fernando de Henares', 'Madrid', '916000000', 'info@hogarfamiliar.es', NOW())
                ON DUPLICATE KEY UPDATE nombre = VALUES(nombre)");
    
    // Estados de encargo
    $pdo->exec("INSERT INTO estados_encargo (nombre, descripcion, orden, color) VALUES
                ('Activo', 'Encargo en búsqueda activa', 1, '#28a745'),
                ('Rebajado', 'Encargo con precio reducido', 2, '#fd7e14'),
                ('Renovado', 'Encargo renovado', 3, '#17a2b8'),
                ('Rebajado/Renovado', 'Encargo renovado con precio reducido', 4, '#007bff'),
                ('Cerrado positivamente', 'Encargo cerrado con éxito', 5, '#28a745'),
                ('Cerrado negativamente', 'Encargo cerrado sin éxito', 6, '#dc3545')
                ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion)");
    
    // Estados de contacto
    $pdo->exec("INSERT INTO estados_contacto (nombre, descripcion, dias_inactividad_min, dias_inactividad_max, orden, color) VALUES
                ('1 - Último Contacto < 30 días', 'Contactado en el último mes', 0, 30, 1, '#28a745'),
                ('2 - Último Contacto 30-60 días', 'Contactado entre 1 y 2 meses', 30, 60, 2, '#ffc107'),
                ('3 - Último Contacto 60-90 días', 'Contactado entre 2 y 3 meses', 60, 90, 3, '#fd7e14'),
                ('4 - Último Contacto > 90 días', 'Sin contacto en más de 3 meses', 90, 999, 4, '#dc3545')
                ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion)");
                
    // Estados de noticia
    $pdo->exec("INSERT INTO estados_noticia (nombre, descripcion, orden, color) VALUES
                ('Nueva', 'Noticia recién creada', 1, '#007bff'),
                ('En seguimiento', 'Noticia en proceso de seguimiento', 2, '#28a745'),
                ('Cerrada con éxito (encargo)', 'Convertida en encargo', 3, '#17a2b8'),
                ('Cerrada sin éxito', 'No culminó en encargo', 4, '#dc3545')
                ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion)");
    
    // Tipos de actividad
    $pdo->exec("INSERT INTO tipos_actividad (nombre, descripcion, requiere_resultado) VALUES
                ('Cita', 'Reunión presencial', 1),
                ('Llamada', 'Contacto telefónico', 1),
                ('Email', 'Comunicación por correo electrónico', 0),
                ('Valoración', 'Tasación de inmueble', 1),
                ('Visita', 'Visita a inmueble', 1),
                ('Nota', 'Anotación interna', 0)
                ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion)");
    
    // Modalidades de contacto
    $pdo->exec("INSERT INTO modalidades_contacto (nombre, descripcion) VALUES
                ('Presencial', 'Contacto en persona'),
                ('Telefónico', 'Contacto por teléfono'),
                ('Email', 'Contacto por correo electrónico'),
                ('WhatsApp', 'Contacto por mensajería instantánea'),
                ('Videoconferencia', 'Contacto por videollamada')
                ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion)");
    
    // Tipos de procedencia
    $pdo->exec("INSERT INTO tipos_procedencia (nombre, descripcion) VALUES
                ('Directa', 'Contacto directo del cliente'),
                ('Colaborador', 'A través de agente colaborador'),
                ('Web', 'A través de la página web'),
                ('Portal inmobiliario', 'A través de portal externo'),
                ('Recomendación', 'Recomendado por otro cliente')
                ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion)");
    
    // Tipologías de inmueble
    $pdo->exec("INSERT INTO tipologias_inmueble (nombre, descripcion) VALUES
                ('Piso', 'Vivienda en bloque residencial'),
                ('Casa', 'Vivienda unifamiliar'),
                ('Local', 'Local comercial'),
                ('Oficina', 'Espacio para uso profesional'),
                ('Nave', 'Nave industrial'),
                ('Terreno', 'Parcela sin edificar'),
                ('Garaje', 'Plaza de garaje'),
                ('Trastero', 'Espacio de almacenamiento')
                ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion)");
    
    // Zonas
    $pdo->exec("INSERT INTO zonas (nombre, localidad, provincia, descripcion) VALUES
                ('Centro', 'San Fernando de Henares', 'Madrid', 'Zona centro de la ciudad'),
                ('La Estación', 'San Fernando de Henares', 'Madrid', 'Zona cercana a la estación de tren'),
                ('El Olivar', 'San Fernando de Henares', 'Madrid', 'Zona residencial nueva'),
                ('Los Alperchines', 'San Fernando de Henares', 'Madrid', 'Zona residencial'),
                ('Parque Henares', 'San Fernando de Henares', 'Madrid', 'Zona residencial cercana al parque')
                ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion)");
    
    // Prioridades
    $pdo->exec("INSERT INTO prioridades (nombre, descripcion, orden, color) VALUES
                ('Alta', 'Atención inmediata requerida', 1, '#dc3545'),
                ('Media', 'Atención normal', 2, '#ffc107'),
                ('Baja', 'Puede esperar', 3, '#28a745')
                ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion)");
    
    echo "Tablas de referencia preparadas.<br>";
}
