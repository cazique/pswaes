// Función para verificar y actualizar relaciones
function verificarActualizarRelaciones($pdo) {
    echo "Verificando y actualizando relaciones...<br>";
    
    try {
        // Actualizar direcciones en inmuebles
        $pdo->exec("UPDATE inmuebles i
                    JOIN direcciones d ON i.inmueble_id = d.inmueble_id
                    SET i.direccion_id = d.direccion_id
                    WHERE i.direccion_id IS NULL AND d.tipo_direccion = 'Inmueble'");
        
        echo "Relaciones de direcciones actualizadas.<br>";
        
        // Verificar claves foráneas huérfanas en encargos
        $stmt = $pdo->query("SELECT COUNT(*) FROM encargos e
                            LEFT JOIN inmuebles i ON e.inmueble_id = i.inmueble_id
                            WHERE e.inmueble_id IS NOT NULL AND i.inmueble_id IS NULL");
        
        $huerfanosInmuebles = $stmt->fetchColumn();
        
        if ($huerfanosInmuebles > 0) {
            echo "Atención: Hay $huerfanosInmuebles encargos con referencias a inmuebles inexistentes.<br>";
        }
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM encargos e
                            LEFT JOIN clientes c ON e.propietario_id = c.cliente_id
                            WHERE e.propietario_id IS NOT NULL AND c.cliente_id IS NULL");
        
        $huerfanosClientes = $stmt->fetchColumn();
        
        if ($huerfanosClientes > 0) {
            echo "Atención: Hay $huerfanosClientes encargos con referencias a clientes inexistentes.<br>";
        }
        
        // Actualizar fechas de último contacto
        $pdo->exec("UPDATE encargos e
                    SET e.fecha_ultimo_contacto = CURRENT_DATE
                    WHERE e.fecha_ultimo_contacto IS NULL");
        
        echo "Fechas de último contacto actualizadas.<br>";
        
        return true;
        
    } catch (Exception $e) {
        echo "Error al verificar relaciones: " . $e->getMessage() . "<br>";
        return false;
    }
}