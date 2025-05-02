<?php
	define('PREPEND_PATH', '');
	include_once(__DIR__ . '/lib.php');

	// accept a record as an assoc array, return transformed row ready to insert to table
	$transformFunctions = [
		'actividades' => function($data, $options = []) {
			if(isset($data['fecha'])) $data['fecha'] = guessMySQLDateTime($data['fecha']);
			if(isset($data['fecha_creacion'])) $data['fecha_creacion'] = guessMySQLDateTime($data['fecha_creacion']);
			if(isset($data['fecha_modificacion'])) $data['fecha_modificacion'] = guessMySQLDateTime($data['fecha_modificacion']);

			return $data;
		},
		'actividades_encargos' => function($data, $options = []) {

			return $data;
		},
		'agencias' => function($data, $options = []) {
			if(isset($data['fecha_alta'])) $data['fecha_alta'] = guessMySQLDateTime($data['fecha_alta']);

			return $data;
		},
		'clientes' => function($data, $options = []) {
			if(isset($data['fecha_nacimiento'])) $data['fecha_nacimiento'] = guessMySQLDateTime($data['fecha_nacimiento']);
			if(isset($data['fecha_alta'])) $data['fecha_alta'] = guessMySQLDateTime($data['fecha_alta']);
			if(isset($data['ultima_modificacion'])) $data['ultima_modificacion'] = guessMySQLDateTime($data['ultima_modificacion']);

			return $data;
		},
		'detalles_procedencia' => function($data, $options = []) {

			return $data;
		},
		'direcciones' => function($data, $options = []) {
			if(isset($data['fecha_alta'])) $data['fecha_alta'] = guessMySQLDateTime($data['fecha_alta']);
			if(isset($data['ultima_modificacion'])) $data['ultima_modificacion'] = guessMySQLDateTime($data['ultima_modificacion']);

			return $data;
		},
		'encargos' => function($data, $options = []) {
			if(isset($data['fecha_creacion'])) $data['fecha_creacion'] = guessMySQLDateTime($data['fecha_creacion']);
			if(isset($data['fecha_modificacion'])) $data['fecha_modificacion'] = guessMySQLDateTime($data['fecha_modificacion']);
			if(isset($data['fecha_cierre'])) $data['fecha_cierre'] = guessMySQLDateTime($data['fecha_cierre']);
			if(isset($data['fecha_ultima_cita'])) $data['fecha_ultima_cita'] = guessMySQLDateTime($data['fecha_ultima_cita']);
			if(isset($data['fecha_ultima_actividad'])) $data['fecha_ultima_actividad'] = guessMySQLDateTime($data['fecha_ultima_actividad']);
			if(isset($data['fecha_ultimo_contacto'])) $data['fecha_ultimo_contacto'] = guessMySQLDateTime($data['fecha_ultimo_contacto']);

			return $data;
		},
		'estados_contacto' => function($data, $options = []) {

			return $data;
		},
		'estados_encargo' => function($data, $options = []) {

			return $data;
		},
		'estados_noticia' => function($data, $options = []) {

			return $data;
		},
		'inmuebles' => function($data, $options = []) {
			if(isset($data['fecha_alta'])) $data['fecha_alta'] = guessMySQLDateTime($data['fecha_alta']);
			if(isset($data['ultima_modificacion'])) $data['ultima_modificacion'] = guessMySQLDateTime($data['ultima_modificacion']);

			return $data;
		},
		'modalidades_contacto' => function($data, $options = []) {

			return $data;
		},
		'motivos_cierre' => function($data, $options = []) {

			return $data;
		},
		'noticias' => function($data, $options = []) {
			if(isset($data['fecha_valoracion'])) $data['fecha_valoracion'] = guessMySQLDateTime($data['fecha_valoracion']);
			if(isset($data['fecha_estimacion_interna'])) $data['fecha_estimacion_interna'] = guessMySQLDateTime($data['fecha_estimacion_interna']);
			if(isset($data['fecha_ultima_cita'])) $data['fecha_ultima_cita'] = guessMySQLDateTime($data['fecha_ultima_cita']);
			if(isset($data['fecha_ultimo_contacto'])) $data['fecha_ultimo_contacto'] = guessMySQLDateTime($data['fecha_ultimo_contacto']);
			if(isset($data['fecha_cierre'])) $data['fecha_cierre'] = guessMySQLDateTime($data['fecha_cierre']);
			if(isset($data['fecha_creacion'])) $data['fecha_creacion'] = guessMySQLDateTime($data['fecha_creacion']);
			if(isset($data['fecha_modificacion'])) $data['fecha_modificacion'] = guessMySQLDateTime($data['fecha_modificacion']);

			return $data;
		},
		'prioridades' => function($data, $options = []) {

			return $data;
		},
		'subtipologias_inmueble' => function($data, $options = []) {

			return $data;
		},
		'subzonas' => function($data, $options = []) {

			return $data;
		},
		'tipologias_inmueble' => function($data, $options = []) {

			return $data;
		},
		'tipos_actividad' => function($data, $options = []) {

			return $data;
		},
		'tipos_procedencia' => function($data, $options = []) {

			return $data;
		},
		'usuarios' => function($data, $options = []) {
			if(isset($data['fecha_alta'])) $data['fecha_alta'] = guessMySQLDateTime($data['fecha_alta']);
			if(isset($data['ultima_conexion'])) $data['ultima_conexion'] = guessMySQLDateTime($data['ultima_conexion']);

			return $data;
		},
		'zonas' => function($data, $options = []) {

			return $data;
		},
	];

	// accept a record as an assoc array, return a boolean indicating whether to import or skip record
	$filterFunctions = [
		'actividades' => function($data, $options = []) { return true; },
		'actividades_encargos' => function($data, $options = []) { return true; },
		'agencias' => function($data, $options = []) { return true; },
		'clientes' => function($data, $options = []) { return true; },
		'detalles_procedencia' => function($data, $options = []) { return true; },
		'direcciones' => function($data, $options = []) { return true; },
		'encargos' => function($data, $options = []) { return true; },
		'estados_contacto' => function($data, $options = []) { return true; },
		'estados_encargo' => function($data, $options = []) { return true; },
		'estados_noticia' => function($data, $options = []) { return true; },
		'inmuebles' => function($data, $options = []) { return true; },
		'modalidades_contacto' => function($data, $options = []) { return true; },
		'motivos_cierre' => function($data, $options = []) { return true; },
		'noticias' => function($data, $options = []) { return true; },
		'prioridades' => function($data, $options = []) { return true; },
		'subtipologias_inmueble' => function($data, $options = []) { return true; },
		'subzonas' => function($data, $options = []) { return true; },
		'tipologias_inmueble' => function($data, $options = []) { return true; },
		'tipos_actividad' => function($data, $options = []) { return true; },
		'tipos_procedencia' => function($data, $options = []) { return true; },
		'usuarios' => function($data, $options = []) { return true; },
		'zonas' => function($data, $options = []) { return true; },
	];

	/*
	Hook file for overwriting/amending $transformFunctions and $filterFunctions:
	hooks/import-csv.php
	If found, it's included below

	The way this works is by either completely overwriting any of the above 2 arrays,
	or, more commonly, overwriting a single function, for example:
		$transformFunctions['tablename'] = function($data, $options = []) {
			// new definition here
			// then you must return transformed data
			return $data;
		};

	Another scenario is transforming a specific field and leaving other fields to the default
	transformation. One possible way of doing this is to store the original transformation function
	in GLOBALS array, calling it inside the custom transformation function, then modifying the
	specific field:
		$GLOBALS['originalTransformationFunction'] = $transformFunctions['tablename'];
		$transformFunctions['tablename'] = function($data, $options = []) {
			$data = call_user_func_array($GLOBALS['originalTransformationFunction'], [$data, $options]);
			$data['fieldname'] = 'transformed value';
			return $data;
		};
	*/

	@include(__DIR__ . '/hooks/import-csv.php');

	$ui = new CSVImportUI($transformFunctions, $filterFunctions);
