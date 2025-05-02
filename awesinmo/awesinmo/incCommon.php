<?php

	#########################################################
	/*
	~~~~~~ LIST OF FUNCTIONS ~~~~~~
		get_table_groups() -- returns an associative array (table_group => tables_array)
		getTablePermissions($tn) -- returns an array of permissions allowed for logged member to given table (allowAccess, allowInsert, allowView, allowEdit, allowDelete) -- allowAccess is set to true if any access level is allowed
		get_sql_fields($tn) -- returns the SELECT part of the table view query
		get_sql_from($tn[, true, [, false]]) -- returns the FROM part of the table view query, with full joins (unless third paramaeter is set to true), optionally skipping permissions if true passed as 2nd param.
		get_joined_record($table, $id[, true]) -- returns assoc array of record values for given PK value of given table, with full joins, optionally skipping permissions if true passed as 3rd param.
		get_defaults($table) -- returns assoc array of table fields as array keys and default values (or empty), excluding automatic values as array values
		htmlUserBar() -- returns html code for displaying user login status to be used on top of pages.
		showNotifications($msg, $class) -- returns html code for displaying a notification. If no parameters provided, processes the GET request for possible notifications.
		parseMySQLDate(a, b) -- returns a if valid mysql date, or b if valid mysql date, or today if b is true, or empty if b is false.
		parseCode(code) -- calculates and returns special values to be inserted in automatic fields.
		addFilter(i, filterAnd, filterField, filterOperator, filterValue) -- enforce a filter over data
		clearFilters() -- clear all filters
		loadView($view, $data) -- passes $data to templates/{$view}.php and returns the output
		loadTable($table, $data) -- loads table template, passing $data to it
		br2nl($text) -- replaces all variations of HTML <br> tags with a new line character
		entitiesToUTF8($text) -- convert unicode entities (e.g. &#1234;) to actual UTF8 characters, requires multibyte string PHP extension
		func_get_args_byref() -- returns an array of arguments passed to a function, by reference
		permissions_sql($table, $level) -- returns an array containing the FROM and WHERE additions for applying permissions to an SQL query
		error_message($msg[, $back_url]) -- returns html code for a styled error message .. pass explicit false in second param to suppress back button
		toMySQLDate($formattedDate, $sep = datalist_date_separator, $ord = datalist_date_format)
		reIndex(&$arr) -- returns a copy of the given array, with keys replaced by 1-based numeric indices, and values replaced by original keys
		get_embed($provider, $url[, $width, $height, $retrieve]) -- returns embed code for a given url (supported providers: [auto-detect], or explicitly pass one of: youtube, vimeo, googlemap, dailymotion, videofileurl)
		check_record_permission($table, $id, $perm = 'view') -- returns true if current user has the specified permission $perm ('view', 'edit' or 'delete') for the given recors, false otherwise
		NavMenus($options) -- returns the HTML code for the top navigation menus. $options is not implemented currently.
		StyleSheet() -- returns the HTML code for included style sheet files to be placed in the <head> section.
		PrepareUploadedFile($FieldName, $MaxSize, $FileTypes={image file types}, $NoRename=false, $dir="") -- validates and moves uploaded file for given $FieldName into the given $dir (or the default one if empty)
		get_home_links($homeLinks, $default_classes, $tgroup) -- process $homeLinks array and return custom links for homepage. Applies $default_classes to links if links have classes defined, and filters links by $tgroup (using '*' matches all table_group values)
		quick_search_html($search_term, $label, $separate_dv = true) -- returns HTML code for the quick search box.
	~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	*/

	#########################################################

	function get_table_groups($skip_authentication = false) {
		$tables = getTableList($skip_authentication);
		$all_groups = ['None'];

		$groups = [];
		foreach($all_groups as $grp) {
			foreach($tables as $tn => $td) {
				if($td[3] && $td[3] == $grp) $groups[$grp][] = $tn;
				if(!$td[3]) $groups[0][] = $tn;
			}
		}

		return $groups;
	}

	#########################################################

	function getTablePermissions($tn) {
		static $table_permissions = [];
		if(isset($table_permissions[$tn])) return $table_permissions[$tn];

		$groupID = getLoggedGroupID();
		$memberID = makeSafe(getLoggedMemberID());
		$res_group = sql("SELECT `tableName`, `allowInsert`, `allowView`, `allowEdit`, `allowDelete` FROM `membership_grouppermissions` WHERE `groupID`='{$groupID}'", $eo);
		$res_user  = sql("SELECT `tableName`, `allowInsert`, `allowView`, `allowEdit`, `allowDelete` FROM `membership_userpermissions`  WHERE LCASE(`memberID`)='{$memberID}'", $eo);

		while($row = db_fetch_assoc($res_group)) {
			$table_permissions[$row['tableName']] = [
				1 => intval($row['allowInsert']),
				2 => intval($row['allowView']),
				3 => intval($row['allowEdit']),
				4 => intval($row['allowDelete']),
				'insert' => intval($row['allowInsert']),
				'view' => intval($row['allowView']),
				'edit' => intval($row['allowEdit']),
				'delete' => intval($row['allowDelete'])
			];
		}

		// user-specific permissions, if specified, overwrite his group permissions
		while($row = db_fetch_assoc($res_user)) {
			$table_permissions[$row['tableName']] = [
				1 => intval($row['allowInsert']),
				2 => intval($row['allowView']),
				3 => intval($row['allowEdit']),
				4 => intval($row['allowDelete']),
				'insert' => intval($row['allowInsert']),
				'view' => intval($row['allowView']),
				'edit' => intval($row['allowEdit']),
				'delete' => intval($row['allowDelete'])
			];
		}

		// if user has any type of access, set 'access' flag
		foreach($table_permissions as $t => $p) {
			$table_permissions[$t]['access'] = $table_permissions[$t][0] = false;

			if($p['insert'] || $p['view'] || $p['edit'] || $p['delete']) {
				$table_permissions[$t]['access'] = $table_permissions[$t][0] = true;
			}
		}

		return $table_permissions[$tn] ?? [];
	}

	#########################################################

	function get_sql_fields($table_name) {
		$sql_fields = [
			'actividades' => "`actividades`.`actividad_id` as 'actividad_id', `actividades`.`referencia` as 'referencia', `actividades`.`usuario_id` as 'usuario_id', `actividades`.`cliente_id` as 'cliente_id', `actividades`.`asunto` as 'asunto', `actividades`.`tipo_actividad_id` as 'tipo_actividad_id', `actividades`.`modalidad_contacto_id` as 'modalidad_contacto_id', if(`actividades`.`fecha`,date_format(`actividades`.`fecha`,'%d/%m/%Y'),'') as 'fecha', `actividades`.`hora_inicio` as 'hora_inicio', `actividades`.`hora_fin` as 'hora_fin', `actividades`.`duracion_minutos` as 'duracion_minutos', `actividades`.`prioridad_id` as 'prioridad_id', `actividades`.`estado_id` as 'estado_id', `actividades`.`descripcion` as 'descripcion', `actividades`.`resultado` as 'resultado', if(`actividades`.`fecha_creacion`,date_format(`actividades`.`fecha_creacion`,'%d/%m/%Y %H:%i'),'') as 'fecha_creacion', if(`actividades`.`fecha_modificacion`,date_format(`actividades`.`fecha_modificacion`,'%d/%m/%Y %H:%i'),'') as 'fecha_modificacion'",
			'actividades_encargos' => "`actividades_encargos`.`actividad_id` as 'actividad_id', `actividades_encargos`.`encargo_id` as 'encargo_id'",
			'agencias' => "`agencias`.`agencia_id` as 'agencia_id', `agencias`.`nombre` as 'nombre', `agencias`.`direccion` as 'direccion', `agencias`.`codigo_postal` as 'codigo_postal', `agencias`.`localidad` as 'localidad', `agencias`.`provincia` as 'provincia', `agencias`.`telefono` as 'telefono', `agencias`.`email` as 'email', `agencias`.`responsable_id` as 'responsable_id', if(`agencias`.`fecha_alta`,date_format(`agencias`.`fecha_alta`,'%d/%m/%Y'),'') as 'fecha_alta', `agencias`.`activo` as 'activo'",
			'clientes' => "`clientes`.`cliente_id` as 'cliente_id', `clientes`.`nombre` as 'nombre', `clientes`.`apellidos` as 'apellidos', `clientes`.`nombre_completo` as 'nombre_completo', `clientes`.`tratamiento` as 'tratamiento', `clientes`.`genero` as 'genero', if(`clientes`.`fecha_nacimiento`,date_format(`clientes`.`fecha_nacimiento`,'%d/%m/%Y'),'') as 'fecha_nacimiento', `clientes`.`documento_id` as 'documento_id', `clientes`.`nacionalidad` as 'nacionalidad', `clientes`.`estado_civil` as 'estado_civil', `clientes`.`telefono_fijo` as 'telefono_fijo', `clientes`.`telefono_movil` as 'telefono_movil', `clientes`.`telefono_adicional` as 'telefono_adicional', `clientes`.`email` as 'email', `clientes`.`cliente_telefonico` as 'cliente_telefonico', `clientes`.`profesion` as 'profesion', `clientes`.`sector_actividad` as 'sector_actividad', `clientes`.`empresa` as 'empresa', if(`clientes`.`fecha_alta`,date_format(`clientes`.`fecha_alta`,'%d/%m/%Y'),'') as 'fecha_alta', if(`clientes`.`ultima_modificacion`,date_format(`clientes`.`ultima_modificacion`,'%d/%m/%Y %H:%i'),'') as 'ultima_modificacion', `clientes`.`agencia_id` as 'agencia_id', `clientes`.`usuario_id` as 'usuario_id', `clientes`.`observaciones` as 'observaciones'",
			'detalles_procedencia' => "`detalles_procedencia`.`detalle_procedencia_id` as 'detalle_procedencia_id', `detalles_procedencia`.`tipo_procedencia_id` as 'tipo_procedencia_id', `detalles_procedencia`.`nombre` as 'nombre', `detalles_procedencia`.`descripcion` as 'descripcion'",
			'direcciones' => "`direcciones`.`direccion_id` as 'direccion_id', `direcciones`.`cliente_id` as 'cliente_id', `direcciones`.`inmueble_id` as 'inmueble_id', `direcciones`.`tipo_direccion` as 'tipo_direccion', `direcciones`.`calle` as 'calle', `direcciones`.`numero` as 'numero', `direcciones`.`piso` as 'piso', `direcciones`.`puerta` as 'puerta', `direcciones`.`escalera` as 'escalera', `direcciones`.`codigo_postal` as 'codigo_postal', `direcciones`.`localidad` as 'localidad', `direcciones`.`provincia` as 'provincia', `direcciones`.`pais` as 'pais', `direcciones`.`referencia_catastral` as 'referencia_catastral', `direcciones`.`coordenadas_latitud` as 'coordenadas_latitud', `direcciones`.`coordenadas_longitud` as 'coordenadas_longitud', `direcciones`.`principal` as 'principal', if(`direcciones`.`fecha_alta`,date_format(`direcciones`.`fecha_alta`,'%d/%m/%Y %H:%i'),'') as 'fecha_alta', if(`direcciones`.`ultima_modificacion`,date_format(`direcciones`.`ultima_modificacion`,'%d/%m/%Y %H:%i'),'') as 'ultima_modificacion'",
			'encargos' => "`encargos`.`encargo_id` as 'encargo_id', `encargos`.`referencia` as 'referencia', `encargos`.`inmueble_id` as 'inmueble_id', `encargos`.`propietario_id` as 'propietario_id', `encargos`.`estado_id` as 'estado_id', `encargos`.`estado_contacto_id` as 'estado_contacto_id', `encargos`.`motivo` as 'motivo', `encargos`.`tipo_procedencia_id` as 'tipo_procedencia_id', `encargos`.`precio` as 'precio', `encargos`.`precio_parking_incluido` as 'precio_parking_incluido', `encargos`.`valoracion` as 'valoracion', `encargos`.`valoracion_solo_inmueble` as 'valoracion_solo_inmueble', `encargos`.`nuda_propiedad` as 'nuda_propiedad', `encargos`.`tratabilidad` as 'tratabilidad', if(`encargos`.`fecha_creacion`,date_format(`encargos`.`fecha_creacion`,'%d/%m/%Y %H:%i'),'') as 'fecha_creacion', if(`encargos`.`fecha_modificacion`,date_format(`encargos`.`fecha_modificacion`,'%d/%m/%Y %H:%i'),'') as 'fecha_modificacion', if(`encargos`.`fecha_cierre`,date_format(`encargos`.`fecha_cierre`,'%d/%m/%Y'),'') as 'fecha_cierre', `encargos`.`motivo_cierre_id` as 'motivo_cierre_id', if(`encargos`.`fecha_ultima_cita`,date_format(`encargos`.`fecha_ultima_cita`,'%d/%m/%Y'),'') as 'fecha_ultima_cita', if(`encargos`.`fecha_ultima_actividad`,date_format(`encargos`.`fecha_ultima_actividad`,'%d/%m/%Y'),'') as 'fecha_ultima_actividad', if(`encargos`.`fecha_ultimo_contacto`,date_format(`encargos`.`fecha_ultimo_contacto`,'%d/%m/%Y'),'') as 'fecha_ultimo_contacto', `encargos`.`nota_privada` as 'nota_privada', `encargos`.`llaves_oficina` as 'llaves_oficina'",
			'estados_contacto' => "`estados_contacto`.`estado_contacto_id` as 'estado_contacto_id', `estados_contacto`.`nombre` as 'nombre', `estados_contacto`.`descripcion` as 'descripcion', `estados_contacto`.`dias_inactividad_min` as 'dias_inactividad_min', `estados_contacto`.`dias_inactividad_max` as 'dias_inactividad_max', `estados_contacto`.`orden` as 'orden', `estados_contacto`.`color` as 'color'",
			'estados_encargo' => "`estados_encargo`.`estado_id` as 'estado_id', `estados_encargo`.`nombre` as 'nombre', `estados_encargo`.`descripcion` as 'descripcion', `estados_encargo`.`orden` as 'orden', `estados_encargo`.`color` as 'color'",
			'estados_noticia' => "`estados_noticia`.`estado_noticia_id` as 'estado_noticia_id', `estados_noticia`.`nombre` as 'nombre', `estados_noticia`.`descripcion` as 'descripcion', `estados_noticia`.`orden` as 'orden', `estados_noticia`.`color` as 'color'",
			'inmuebles' => "`inmuebles`.`inmueble_id` as 'inmueble_id', `inmuebles`.`referencia` as 'referencia', `inmuebles`.`propietario_id` as 'propietario_id', `inmuebles`.`estado` as 'estado', `inmuebles`.`tipologia_id` as 'tipologia_id', `inmuebles`.`subtipologia_id` as 'subtipologia_id', `inmuebles`.`direccion_id` as 'direccion_id', `inmuebles`.`zona_id` as 'zona_id', `inmuebles`.`dormitorios` as 'dormitorios', `inmuebles`.`banos` as 'banos', `inmuebles`.`aseos` as 'aseos', `inmuebles`.`m2_utiles` as 'm2_utiles', `inmuebles`.`m2_construidos` as 'm2_construidos', `inmuebles`.`m2_terraza` as 'm2_terraza', `inmuebles`.`altura` as 'altura', `inmuebles`.`orientacion` as 'orientacion', `inmuebles`.`estado_conservacion` as 'estado_conservacion', `inmuebles`.`ano_construccion` as 'ano_construccion', `inmuebles`.`ano_reforma` as 'ano_reforma', `inmuebles`.`ascensor` as 'ascensor', `inmuebles`.`terraza` as 'terraza', `inmuebles`.`balcon` as 'balcon', `inmuebles`.`garaje` as 'garaje', `inmuebles`.`trastero` as 'trastero', `inmuebles`.`calefaccion_tipo` as 'calefaccion_tipo', `inmuebles`.`climatizacion` as 'climatizacion', `inmuebles`.`certificado_energetico` as 'certificado_energetico', `inmuebles`.`consumo_energetico` as 'consumo_energetico', `inmuebles`.`emisiones_co2` as 'emisiones_co2', `inmuebles`.`referencia_catastral` as 'referencia_catastral', if(`inmuebles`.`fecha_alta`,date_format(`inmuebles`.`fecha_alta`,'%d/%m/%Y %H:%i'),'') as 'fecha_alta', if(`inmuebles`.`ultima_modificacion`,date_format(`inmuebles`.`ultima_modificacion`,'%d/%m/%Y %H:%i'),'') as 'ultima_modificacion', `inmuebles`.`llaves_oficina` as 'llaves_oficina', `inmuebles`.`notas_privadas` as 'notas_privadas', `inmuebles`.`observaciones` as 'observaciones'",
			'modalidades_contacto' => "`modalidades_contacto`.`modalidad_contacto_id` as 'modalidad_contacto_id', `modalidades_contacto`.`nombre` as 'nombre', `modalidades_contacto`.`descripcion` as 'descripcion'",
			'motivos_cierre' => "`motivos_cierre`.`motivo_cierre_id` as 'motivo_cierre_id', `motivos_cierre`.`nombre` as 'nombre', `motivos_cierre`.`descripcion` as 'descripcion', `motivos_cierre`.`es_positivo` as 'es_positivo'",
			'noticias' => "`noticias`.`noticia_id` as 'noticia_id', `noticias`.`referencia` as 'referencia', `noticias`.`inmueble_id` as 'inmueble_id', `noticias`.`cliente_id` as 'cliente_id', `noticias`.`colaborador_id` as 'colaborador_id', `noticias`.`estado_noticia_id` as 'estado_noticia_id', `noticias`.`estado_contacto_id` as 'estado_contacto_id', `noticias`.`tipo_procedencia_id` as 'tipo_procedencia_id', `noticias`.`detalle_procedencia_id` as 'detalle_procedencia_id', `noticias`.`motivacion` as 'motivacion', `noticias`.`valoracion` as 'valoracion', `noticias`.`valoracion_solo_inmueble` as 'valoracion_solo_inmueble', `noticias`.`precio_pedido` as 'precio_pedido', if(`noticias`.`fecha_valoracion`,date_format(`noticias`.`fecha_valoracion`,'%d/%m/%Y'),'') as 'fecha_valoracion', if(`noticias`.`fecha_estimacion_interna`,date_format(`noticias`.`fecha_estimacion_interna`,'%d/%m/%Y'),'') as 'fecha_estimacion_interna', if(`noticias`.`fecha_ultima_cita`,date_format(`noticias`.`fecha_ultima_cita`,'%d/%m/%Y'),'') as 'fecha_ultima_cita', if(`noticias`.`fecha_ultimo_contacto`,date_format(`noticias`.`fecha_ultimo_contacto`,'%d/%m/%Y'),'') as 'fecha_ultimo_contacto', if(`noticias`.`fecha_cierre`,date_format(`noticias`.`fecha_cierre`,'%d/%m/%Y'),'') as 'fecha_cierre', `noticias`.`motivo_cierre_id` as 'motivo_cierre_id', `noticias`.`nota` as 'nota', if(`noticias`.`fecha_creacion`,date_format(`noticias`.`fecha_creacion`,'%d/%m/%Y %H:%i'),'') as 'fecha_creacion', if(`noticias`.`fecha_modificacion`,date_format(`noticias`.`fecha_modificacion`,'%d/%m/%Y %H:%i'),'') as 'fecha_modificacion'",
			'prioridades' => "`prioridades`.`prioridad_id` as 'prioridad_id', `prioridades`.`nombre` as 'nombre', `prioridades`.`descripcion` as 'descripcion', `prioridades`.`orden` as 'orden', `prioridades`.`color` as 'color'",
			'subtipologias_inmueble' => "`subtipologias_inmueble`.`subtipologia_id` as 'subtipologia_id', `subtipologias_inmueble`.`tipologia_id` as 'tipologia_id', `subtipologias_inmueble`.`nombre` as 'nombre', `subtipologias_inmueble`.`descripcion` as 'descripcion'",
			'subzonas' => "`subzonas`.`subzona_id` as 'subzona_id', `subzonas`.`zona_id` as 'zona_id', `subzonas`.`nombre` as 'nombre', `subzonas`.`descripcion` as 'descripcion'",
			'tipologias_inmueble' => "`tipologias_inmueble`.`tipologia_id` as 'tipologia_id', `tipologias_inmueble`.`nombre` as 'nombre', `tipologias_inmueble`.`descripcion` as 'descripcion'",
			'tipos_actividad' => "`tipos_actividad`.`tipo_actividad_id` as 'tipo_actividad_id', `tipos_actividad`.`nombre` as 'nombre', `tipos_actividad`.`descripcion` as 'descripcion', `tipos_actividad`.`requiere_resultado` as 'requiere_resultado'",
			'tipos_procedencia' => "`tipos_procedencia`.`tipo_procedencia_id` as 'tipo_procedencia_id', `tipos_procedencia`.`nombre` as 'nombre', `tipos_procedencia`.`descripcion` as 'descripcion'",
			'usuarios' => "`usuarios`.`usuario_id` as 'usuario_id', `usuarios`.`nombre` as 'nombre', `usuarios`.`apellidos` as 'apellidos', `usuarios`.`nombre_completo` as 'nombre_completo', `usuarios`.`email` as 'email', `usuarios`.`telefono` as 'telefono', `usuarios`.`puesto` as 'puesto', `usuarios`.`agencia_id` as 'agencia_id', `usuarios`.`activo` as 'activo', if(`usuarios`.`fecha_alta`,date_format(`usuarios`.`fecha_alta`,'%d/%m/%Y'),'') as 'fecha_alta', if(`usuarios`.`ultima_conexion`,date_format(`usuarios`.`ultima_conexion`,'%d/%m/%Y %H:%i'),'') as 'ultima_conexion'",
			'zonas' => "`zonas`.`zona_id` as 'zona_id', `zonas`.`nombre` as 'nombre', `zonas`.`localidad` as 'localidad', `zonas`.`provincia` as 'provincia', `zonas`.`descripcion` as 'descripcion'",
		];

		if(isset($sql_fields[$table_name])) return $sql_fields[$table_name];

		return false;
	}

	#########################################################

	function get_sql_from($table_name, $skip_permissions = false, $skip_joins = false, $lower_permissions = false) {
		$sql_from = [
			'actividades' => "`actividades` ",
			'actividades_encargos' => "`actividades_encargos` ",
			'agencias' => "`agencias` ",
			'clientes' => "`clientes` ",
			'detalles_procedencia' => "`detalles_procedencia` ",
			'direcciones' => "`direcciones` ",
			'encargos' => "`encargos` ",
			'estados_contacto' => "`estados_contacto` ",
			'estados_encargo' => "`estados_encargo` ",
			'estados_noticia' => "`estados_noticia` ",
			'inmuebles' => "`inmuebles` ",
			'modalidades_contacto' => "`modalidades_contacto` ",
			'motivos_cierre' => "`motivos_cierre` ",
			'noticias' => "`noticias` ",
			'prioridades' => "`prioridades` ",
			'subtipologias_inmueble' => "`subtipologias_inmueble` ",
			'subzonas' => "`subzonas` ",
			'tipologias_inmueble' => "`tipologias_inmueble` ",
			'tipos_actividad' => "`tipos_actividad` ",
			'tipos_procedencia' => "`tipos_procedencia` ",
			'usuarios' => "`usuarios` ",
			'zonas' => "`zonas` ",
		];

		$pkey = [
			'actividades' => 'actividad_id',
			'actividades_encargos' => 'actividad_id',
			'agencias' => 'agencia_id',
			'clientes' => 'cliente_id',
			'detalles_procedencia' => 'detalle_procedencia_id',
			'direcciones' => 'direccion_id',
			'encargos' => 'encargo_id',
			'estados_contacto' => 'estado_contacto_id',
			'estados_encargo' => 'estado_id',
			'estados_noticia' => 'estado_noticia_id',
			'inmuebles' => 'inmueble_id',
			'modalidades_contacto' => 'modalidad_contacto_id',
			'motivos_cierre' => 'motivo_cierre_id',
			'noticias' => 'noticia_id',
			'prioridades' => 'prioridad_id',
			'subtipologias_inmueble' => 'subtipologia_id',
			'subzonas' => 'subzona_id',
			'tipologias_inmueble' => 'tipologia_id',
			'tipos_actividad' => 'tipo_actividad_id',
			'tipos_procedencia' => 'tipo_procedencia_id',
			'usuarios' => 'usuario_id',
			'zonas' => 'zona_id',
		];

		if(!isset($sql_from[$table_name])) return false;

		$from = ($skip_joins ? "`{$table_name}`" : $sql_from[$table_name]);

		if($skip_permissions) return $from . ' WHERE 1=1';

		// mm: build the query based on current member's permissions
		// allowing lower permissions if $lower_permissions set to 'user' or 'group'
		$perm = getTablePermissions($table_name);
		if($perm['view'] == 1 || ($perm['view'] > 1 && $lower_permissions == 'user')) { // view owner only
			$from .= ", `membership_userrecords` WHERE `{$table_name}`.`{$pkey[$table_name]}`=`membership_userrecords`.`pkValue` AND `membership_userrecords`.`tableName`='{$table_name}' AND LCASE(`membership_userrecords`.`memberID`)='" . getLoggedMemberID() . "'";
		} elseif($perm['view'] == 2 || ($perm['view'] > 2 && $lower_permissions == 'group')) { // view group only
			$from .= ", `membership_userrecords` WHERE `{$table_name}`.`{$pkey[$table_name]}`=`membership_userrecords`.`pkValue` AND `membership_userrecords`.`tableName`='{$table_name}' AND `membership_userrecords`.`groupID`='" . getLoggedGroupID() . "'";
		} elseif($perm['view'] == 3) { // view all
			$from .= ' WHERE 1=1';
		} else { // view none
			return false;
		}

		return $from;
	}

	#########################################################

	function get_joined_record($table, $id, $skip_permissions = false) {
		$sql_fields = get_sql_fields($table);
		$sql_from = get_sql_from($table, $skip_permissions);

		if(!$sql_fields || !$sql_from) return false;

		$pk = getPKFieldName($table);
		if(!$pk) return false;

		$safe_id = makeSafe($id, false);
		$sql = "SELECT {$sql_fields} FROM {$sql_from} AND `{$table}`.`{$pk}`='{$safe_id}'";
		$eo = ['silentErrors' => true];
		$res = sql($sql, $eo);
		if($row = db_fetch_assoc($res)) return $row;

		return false;
	}

	#########################################################

	function get_defaults($table) {
		/* array of tables and their fields, with default values (or empty), excluding automatic values */
		$defaults = [
			'actividades' => [
				'actividad_id' => '',
				'referencia' => '',
				'usuario_id' => '',
				'cliente_id' => '',
				'asunto' => '',
				'tipo_actividad_id' => '',
				'modalidad_contacto_id' => '',
				'fecha' => '',
				'hora_inicio' => '',
				'hora_fin' => '',
				'duracion_minutos' => '',
				'prioridad_id' => '',
				'estado_id' => '',
				'descripcion' => '',
				'resultado' => '',
				'fecha_creacion' => '',
				'fecha_modificacion' => '',
			],
			'actividades_encargos' => [
				'actividad_id' => '',
				'encargo_id' => '',
			],
			'agencias' => [
				'agencia_id' => '',
				'nombre' => '',
				'direccion' => '',
				'codigo_postal' => '',
				'localidad' => '',
				'provincia' => '',
				'telefono' => '',
				'email' => '',
				'responsable_id' => '',
				'fecha_alta' => 'curdate()',
				'activo' => '1',
			],
			'clientes' => [
				'cliente_id' => '',
				'nombre' => '',
				'apellidos' => '',
				'nombre_completo' => '',
				'tratamiento' => '',
				'genero' => '',
				'fecha_nacimiento' => '',
				'documento_id' => '',
				'nacionalidad' => '',
				'estado_civil' => '',
				'telefono_fijo' => '',
				'telefono_movil' => '',
				'telefono_adicional' => '',
				'email' => '',
				'cliente_telefonico' => '0',
				'profesion' => '',
				'sector_actividad' => '',
				'empresa' => '',
				'fecha_alta' => 'curdate()',
				'ultima_modificacion' => '',
				'agencia_id' => '',
				'usuario_id' => '',
				'observaciones' => '',
			],
			'detalles_procedencia' => [
				'detalle_procedencia_id' => '',
				'tipo_procedencia_id' => '',
				'nombre' => '',
				'descripcion' => '',
			],
			'direcciones' => [
				'direccion_id' => '',
				'cliente_id' => '',
				'inmueble_id' => '',
				'tipo_direccion' => '',
				'calle' => '',
				'numero' => '',
				'piso' => '',
				'puerta' => '',
				'escalera' => '',
				'codigo_postal' => '',
				'localidad' => '',
				'provincia' => '',
				'pais' => 'Espana',
				'referencia_catastral' => '',
				'coordenadas_latitud' => '',
				'coordenadas_longitud' => '',
				'principal' => '0',
				'fecha_alta' => '',
				'ultima_modificacion' => '',
			],
			'encargos' => [
				'encargo_id' => '',
				'referencia' => '',
				'inmueble_id' => '',
				'propietario_id' => '',
				'estado_id' => '',
				'estado_contacto_id' => '',
				'motivo' => '',
				'tipo_procedencia_id' => '',
				'precio' => '',
				'precio_parking_incluido' => '0',
				'valoracion' => '',
				'valoracion_solo_inmueble' => '',
				'nuda_propiedad' => '0',
				'tratabilidad' => '',
				'fecha_creacion' => '',
				'fecha_modificacion' => '',
				'fecha_cierre' => '',
				'motivo_cierre_id' => '',
				'fecha_ultima_cita' => '',
				'fecha_ultima_actividad' => '',
				'fecha_ultimo_contacto' => '',
				'nota_privada' => '',
				'llaves_oficina' => '0',
			],
			'estados_contacto' => [
				'estado_contacto_id' => '',
				'nombre' => '',
				'descripcion' => '',
				'dias_inactividad_min' => '0',
				'dias_inactividad_max' => '999',
				'orden' => '0',
				'color' => '#000000',
			],
			'estados_encargo' => [
				'estado_id' => '',
				'nombre' => '',
				'descripcion' => '',
				'orden' => '0',
				'color' => '#000000',
			],
			'estados_noticia' => [
				'estado_noticia_id' => '',
				'nombre' => '',
				'descripcion' => '',
				'orden' => '0',
				'color' => '#000000',
			],
			'inmuebles' => [
				'inmueble_id' => '',
				'referencia' => '',
				'propietario_id' => '',
				'estado' => 'Disponible',
				'tipologia_id' => '',
				'subtipologia_id' => '',
				'direccion_id' => '',
				'zona_id' => '',
				'dormitorios' => '',
				'banos' => '',
				'aseos' => '',
				'm2_utiles' => '',
				'm2_construidos' => '',
				'm2_terraza' => '',
				'altura' => '',
				'orientacion' => '',
				'estado_conservacion' => '',
				'ano_construccion' => '',
				'ano_reforma' => '',
				'ascensor' => '0',
				'terraza' => '0',
				'balcon' => '0',
				'garaje' => '0',
				'trastero' => '0',
				'calefaccion_tipo' => '',
				'climatizacion' => '',
				'certificado_energetico' => '',
				'consumo_energetico' => '',
				'emisiones_co2' => '',
				'referencia_catastral' => '',
				'fecha_alta' => '',
				'ultima_modificacion' => '',
				'llaves_oficina' => '0',
				'notas_privadas' => '',
				'observaciones' => '',
			],
			'modalidades_contacto' => [
				'modalidad_contacto_id' => '',
				'nombre' => '',
				'descripcion' => '',
			],
			'motivos_cierre' => [
				'motivo_cierre_id' => '',
				'nombre' => '',
				'descripcion' => '',
				'es_positivo' => '0',
			],
			'noticias' => [
				'noticia_id' => '',
				'referencia' => '',
				'inmueble_id' => '',
				'cliente_id' => '',
				'colaborador_id' => '',
				'estado_noticia_id' => '',
				'estado_contacto_id' => '',
				'tipo_procedencia_id' => '',
				'detalle_procedencia_id' => '',
				'motivacion' => '',
				'valoracion' => '',
				'valoracion_solo_inmueble' => '',
				'precio_pedido' => '',
				'fecha_valoracion' => '',
				'fecha_estimacion_interna' => '',
				'fecha_ultima_cita' => '',
				'fecha_ultimo_contacto' => '',
				'fecha_cierre' => '',
				'motivo_cierre_id' => '',
				'nota' => '',
				'fecha_creacion' => '',
				'fecha_modificacion' => '',
			],
			'prioridades' => [
				'prioridad_id' => '',
				'nombre' => '',
				'descripcion' => '',
				'orden' => '0',
				'color' => '#000000',
			],
			'subtipologias_inmueble' => [
				'subtipologia_id' => '',
				'tipologia_id' => '',
				'nombre' => '',
				'descripcion' => '',
			],
			'subzonas' => [
				'subzona_id' => '',
				'zona_id' => '',
				'nombre' => '',
				'descripcion' => '',
			],
			'tipologias_inmueble' => [
				'tipologia_id' => '',
				'nombre' => '',
				'descripcion' => '',
			],
			'tipos_actividad' => [
				'tipo_actividad_id' => '',
				'nombre' => '',
				'descripcion' => '',
				'requiere_resultado' => '0',
			],
			'tipos_procedencia' => [
				'tipo_procedencia_id' => '',
				'nombre' => '',
				'descripcion' => '',
			],
			'usuarios' => [
				'usuario_id' => '',
				'nombre' => '',
				'apellidos' => '',
				'nombre_completo' => '',
				'email' => '',
				'telefono' => '',
				'puesto' => '',
				'agencia_id' => '',
				'activo' => '1',
				'fecha_alta' => 'curdate()',
				'ultima_conexion' => '',
			],
			'zonas' => [
				'zona_id' => '',
				'nombre' => '',
				'localidad' => '',
				'provincia' => '',
				'descripcion' => '',
			],
		];

		return isset($defaults[$table]) ? $defaults[$table] : [];
	}

	#########################################################

	function htmlUserBar() {
		global $Translation;
		if(!defined('PREPEND_PATH')) define('PREPEND_PATH', '');

		$mi = getMemberInfo();
		$adminConfig = config('adminConfig');
		$home_page = (basename($_SERVER['PHP_SELF']) == 'index.php');
		ob_start();

		?>
		<nav class="navbar navbar-default navbar-fixed-top hidden-print" role="navigation">
			<div class="navbar-header">
				<button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
					<span class="glyphicon glyphicon-menu-hamburger"></span>
				</button>
				<!-- application title is obtained from the name besides the yellow database icon in AppGini, use underscores for spaces -->
				<a class="navbar-brand" href="<?php echo PREPEND_PATH; ?>index.php"><i class="glyphicon glyphicon-home"></i> <?php echo APP_TITLE; ?></a>
				<p class="navbar-text pull-left navbar-sub-brand-separator hidden">/</p>
				<a class="navbar-brand navbar-sub-brand title-link hidden"></a>
			</div>
			<div class="collapse navbar-collapse">

				<?php if(!Authentication::isGuest()) { ?>
					<ul class="nav navbar-nav visible-xs">
						<div class="btn-group">
							<a class="btn navbar-btn btn-default btn-lg signed-in-as" href="<?php echo PREPEND_PATH; ?>membership_profile.php">
								<i class="glyphicon glyphicon-user"></i>
								<strong class="username"><?php echo $mi['username']; ?></strong>
							</a>
							<a class="btn navbar-btn btn-default btn-lg" href="<?php echo PREPEND_PATH; ?>index.php?signOut=1">
								<i class="glyphicon glyphicon-log-out"></i>
							</a>
						</div>
					</ul>
				<?php } ?>

				<ul class="nav navbar-nav"><?php echo ($home_page && !HOMEPAGE_NAVMENUS ? '' : NavMenus()); ?></ul>

				<?php if(userCanImport()){ ?>
					<ul class="nav navbar-nav">
						<a href="<?php echo PREPEND_PATH; ?>import-csv.php" class="btn btn-default navbar-btn hidden-xs btn-import-csv" title="<?php echo html_attr($Translation['import csv file']); ?>"><i class="glyphicon glyphicon-th"></i> <?php echo $Translation['import CSV']; ?></a>
						<a href="<?php echo PREPEND_PATH; ?>import-csv.php" class="btn btn-default navbar-btn visible-xs btn-lg btn-import-csv" title="<?php echo html_attr($Translation['import csv file']); ?>"><i class="glyphicon glyphicon-th"></i> <?php echo $Translation['import CSV']; ?></a>
					</ul>
				<?php } ?>

				<?php if(getLoggedAdmin() !== false) { ?>
					<ul class="nav navbar-nav">
						<a href="<?php echo PREPEND_PATH; ?>admin/pageHome.php" class="btn btn-danger navbar-btn hidden-xs" title="<?php echo html_attr($Translation['admin area']); ?>"><i class="glyphicon glyphicon-cog"></i> <?php echo $Translation['admin area']; ?></a>
						<a href="<?php echo PREPEND_PATH; ?>admin/pageHome.php" class="btn btn-danger navbar-btn visible-xs btn-lg" title="<?php echo html_attr($Translation['admin area']); ?>"><i class="glyphicon glyphicon-cog"></i> <?php echo $Translation['admin area']; ?></a>
					</ul>
				<?php } ?>

				<?php if(!Request::val('signIn') && !Request::val('loginFailed')) { ?>
					<?php if(Authentication::isGuest()) { ?>
						<p class="navbar-text navbar-right hidden-xs">&nbsp;</p>
						<a href="<?php echo PREPEND_PATH; ?>index.php?signIn=1" class="btn btn-success navbar-btn navbar-right hidden-xs"><?php echo $Translation['sign in']; ?></a>
						<p class="navbar-text navbar-right hidden-xs">
							<?php echo $Translation['not signed in']; ?>
						</p>
						<a href="<?php echo PREPEND_PATH; ?>index.php?signIn=1" class="btn btn-success btn-block btn-lg navbar-btn visible-xs">
							<?php echo $Translation['not signed in']; ?>
							<i class="glyphicon glyphicon-chevron-right"></i>
							<?php echo $Translation['sign in']; ?>
						</a>
					<?php } else { ?>
						<ul class="nav navbar-nav navbar-right hidden-xs">
							<!-- logged user profile menu -->
							<li class="dropdown" title="<?php echo html_attr("{$Translation['signed as']} {$mi['username']}"); ?>">
								<a href="#" class="dropdown-toggle profile-menu-icon" data-toggle="dropdown"><i class="glyphicon glyphicon-user icon"></i><span class="profile-menu-text"><?php echo $mi['username']; ?></span><b class="caret"></b></a>
								<ul class="dropdown-menu profile-menu">
									<li class="user-profile-menu-item" title="<?php echo html_attr("{$Translation['Your info']}"); ?>">
										<a href="<?php echo PREPEND_PATH; ?>membership_profile.php"><i class="glyphicon glyphicon-user"></i> <?php echo $Translation['my account']; ?> <span class="label label-default username"><?php echo $mi['username']; ?></span></a>
									</li>
									<li class="keyboard-shortcuts-menu-item hidden-xs" title="<?php echo html_attr("{$Translation['keyboard shortcuts']}"); ?>">
										<a href="#" class="help-shortcuts-launcher">
											<img src="<?php echo PREPEND_PATH; ?>resources/images/keyboard.png">
											<?php echo html_attr($Translation['keyboard shortcuts']); ?>
										</a>
									</li>
									<li class="sign-out-menu-item" title="<?php echo html_attr("{$Translation['sign out']}"); ?>">
										<a href="<?php echo PREPEND_PATH; ?>index.php?signOut=1"><i class="glyphicon glyphicon-log-out"></i> <?php echo $Translation['sign out']; ?></a>
									</li>
								</ul>
							</li>
						</ul>
						<script>
							/* periodically check if user is still signed in */
							setInterval(function() {
								$j.ajax({
									url: '<?php echo PREPEND_PATH; ?>ajax_check_login.php',
									success: function(username) {
										if(!username.length) window.location = '<?php echo PREPEND_PATH; ?>index.php?signIn=1';
									}
								});
							}, 60000);
						</script>
					<?php } ?>
				<?php } ?>

				<a href="#" class="btn btn-default navbar-btn hidden navbar-right hidden-xs install-pwa-btn" title="<?php echo html_attr($Translation['install mobile app']); ?>">
					<i class="glyphicon glyphicon-cloud-download"></i>
				</a>
				<a href="#" class="btn btn-default btn-block btn-lg navbar-btn hidden hidden-sm hidden-md hidden-lg install-pwa-btn" title="<?php echo html_attr($Translation['install mobile app']); ?>">
					<i class="glyphicon glyphicon-cloud-download"></i> <?php echo $Translation['install mobile app']; ?>
				</a>
				<script>
					// when browser detects that site is installable as PWA, show install button
					window.addEventListener('beforeinstallprompt', function(e) {
						e.preventDefault();

						// To override default silent period, set AppGini.config.PWAInstallPromptSilentPeriodDays to the number of days
						let silentPeriod = 86400000; // default silent period is 10 days
						if(AppGini.config.PWAInstallPromptSilentPeriodDays) {
							silentPeriod = parseInt(AppGini.config.PWAInstallPromptSilentPeriodDays) * 10 * 60 * 60 * 24 * 1000;
						}

						// if user dismissed the install prompt, don't show it again for some time
						if(
							localStorage.getItem('AppGini.PWApromptDismissedAt')
							&& (new Date().getTime() - localStorage.getItem('AppGini.PWApromptDismissedAt')) < silentPeriod
						) return;

						// unhide .install-pwa-btn by removing .hidden
						document.querySelectorAll('.install-pwa-btn').forEach(function(el) {
							el.classList.remove('hidden');

							// install on click
							el.addEventListener('click', function(ce) {
								ce.preventDefault();
								e.prompt();

								// add a localStorage item to prevent showing the install button for some time
								localStorage.setItem('AppGini.PWApromptDismissedAt', new Date().getTime());
							});
						});
					});
				</script>
			</div>
		</nav>
		<?php

		return ob_get_clean();
	}

	#########################################################

	function showNotifications($msg = '', $class = '', $fadeout = true) {
		global $Translation;
		if($error_message = strip_tags(Request::val('error_message')))
			$error_message = '<div class="text-bold">' . $error_message . '</div>';

		if(!$msg) { // if no msg, use url to detect message to display
			if(Request::val('record-added-ok')) {
				$msg = $Translation['new record saved'];
				$class = 'alert-success';
			} elseif(Request::val('record-added-error')) {
				$msg = $Translation['Couldn\'t save the new record'] . $error_message;
				$class = 'alert-danger';
				$fadeout = false;
			} elseif(Request::val('record-updated-ok')) {
				$msg = $Translation['record updated'];
				$class = 'alert-success';
			} elseif(Request::val('record-updated-error')) {
				$msg = $Translation['Couldn\'t save changes to the record'] . $error_message;
				$class = 'alert-danger';
				$fadeout = false;
			} elseif(Request::val('record-deleted-ok')) {
				$msg = $Translation['The record has been deleted successfully'];
				$class = 'alert-success';
			} elseif(Request::val('record-deleted-error')) {
				$msg = $Translation['Couldn\'t delete this record'] . $error_message;
				$class = 'alert-danger';
				$fadeout = false;
			} else {
				return '';
			}
		}
		$id = 'notification-' . rand();

		ob_start();
		// notification template
		?>
		<div id="%%ID%%" class="alert alert-dismissable %%CLASS%%" style="opacity: 1; padding-top: 6px; padding-bottom: 6px; animation: fadeIn 1.5s ease-out; z-index: 100; position: relative;">
			<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
			%%MSG%%
		</div>
		<script>
			$j(function() {
				var autoDismiss = <?php echo $fadeout ? 'true' : 'false'; ?>,
					embedded = !$j('nav').length,
					messageDelay = 10, fadeDelay = 1.5;

				if(!autoDismiss) {
					if(embedded)
						$j('#%%ID%%').before('<div class="modal-top-spacer"></div>');
					else
						$j('#%%ID%%').css({ margin: '0 0 1rem' });

					return;
				}

				// below code runs only in case of autoDismiss

				if(embedded)
					$j('#%%ID%%').css({ margin: '1rem 0 -1rem' });
				else
					$j('#%%ID%%').css({ margin: '-15px 0 -20px' });

				setTimeout(function() {
					$j('#%%ID%%').css({    animation: 'fadeOut ' + fadeDelay + 's ease-out' });
				}, messageDelay * 1000);

				setTimeout(function() {
					$j('#%%ID%%').css({    visibility: 'hidden' });
				}, (messageDelay + fadeDelay) * 1000);
			})
		</script>
		<style>
			@keyframes fadeIn {
				0%   { opacity: 0; }
				100% { opacity: 1; }
			}
			@keyframes fadeOut {
				0%   { opacity: 1; }
				100% { opacity: 0; }
			}
		</style>

		<?php
		$out = ob_get_clean();

		$out = str_replace('%%ID%%', $id, $out);
		$out = str_replace('%%MSG%%', $msg, $out);
		$out = str_replace('%%CLASS%%', $class, $out);

		return $out;
	}

	#########################################################

	function validMySQLDate($date) {
		$date = trim($date);

		try {
			$dtObj = new DateTime($date);
		} catch(Exception $e) {
			return false;
		}

		$parts = explode('-', $date);
		return (
			count($parts) == 3
			// see https://dev.mysql.com/doc/refman/8.0/en/datetime.html
			&& intval($parts[0]) >= 1000
			&& intval($parts[0]) <= 9999
			&& intval($parts[1]) >= 1
			&& intval($parts[1]) <= 12
			&& intval($parts[2]) >= 1
			&& intval($parts[2]) <= 31
		);
	}

	#########################################################

	function parseMySQLDate($date, $altDate) {
		// is $date valid?
		if(validMySQLDate($date)) return trim($date);

		if($date != '--' && validMySQLDate($altDate)) return trim($altDate);

		if($date != '--' && $altDate && is_numeric($altDate))
			return @date('Y-m-d', @time() + ($altDate >= 1 ? $altDate - 1 : $altDate) * 86400);

		return '';
	}

	#########################################################

	function parseCode($code, $isInsert = true, $rawData = false) {
		$mi = Authentication::getUser();

		if($isInsert) {
			$arrCodes = [
				'<%%creatorusername%%>' => $mi['username'],
				'<%%creatorgroupid%%>' => $mi['groupId'],
				'<%%creatorip%%>' => $_SERVER['REMOTE_ADDR'],
				'<%%creatorgroup%%>' => $mi['group'],

				'<%%creationdate%%>' => ($rawData ? date('Y-m-d') : date(app_datetime_format('phps'))),
				'<%%creationtime%%>' => ($rawData ? date('H:i:s') : date(app_datetime_format('phps', 't'))),
				'<%%creationdatetime%%>' => ($rawData ? date('Y-m-d H:i:s') : date(app_datetime_format('phps', 'dt'))),
				'<%%creationtimestamp%%>' => ($rawData ? date('Y-m-d H:i:s') : time()),
			];
		} else {
			$arrCodes = [
				'<%%editorusername%%>' => $mi['username'],
				'<%%editorgroupid%%>' => $mi['groupId'],
				'<%%editorip%%>' => $_SERVER['REMOTE_ADDR'],
				'<%%editorgroup%%>' => $mi['group'],

				'<%%editingdate%%>' => ($rawData ? date('Y-m-d') : date(app_datetime_format('phps'))),
				'<%%editingtime%%>' => ($rawData ? date('H:i:s') : date(app_datetime_format('phps', 't'))),
				'<%%editingdatetime%%>' => ($rawData ? date('Y-m-d H:i:s') : date(app_datetime_format('phps', 'dt'))),
				'<%%editingtimestamp%%>' => ($rawData ? date('Y-m-d H:i:s') : time()),
			];
		}

		$pc = str_ireplace(array_keys($arrCodes), array_values($arrCodes), $code);

		return $pc;
	}

	#########################################################

	function parseMySQLDateTime($datetime, $altDateTime) {
		// is $datetime valid?
		if(mysql_datetime($datetime)) return mysql_datetime($datetime);

		if($altDateTime === '') return '';

		// is $altDateTime valid?
		if(mysql_datetime($altDateTime)) return mysql_datetime($altDateTime);

		/* parse $altDateTime */
		$matches = [];
		if(!preg_match('/^([+-])(\d+)(s|m|h|d)(0)?$/', $altDateTime, $matches))
			return '';

		$sign = ($matches[1] == '-' ? -1 : 1);
		$unit = $matches[3];
		$qty = $matches[2];

		// m0 means increment minutes, set seconds to 0
		// h0 means increment hours, set minutes and seconds to 0
		// d0 means increment days, set time to 00:00:00
		$zeroTime = $matches[4] == '0';

		switch($unit) {
			case 's':
				$seconds = $qty * $sign;
				break;
			case 'm':
				$seconds = $qty * 60 * $sign;
				if($zeroTime) return @date('Y-m-d H:i:00', @time() + $seconds);
				break;
			case 'h':
				$seconds = $qty * 3600 * $sign;
				if($zeroTime) return @date('Y-m-d H:00:00', @time() + $seconds);
				break;
			case 'd':
				$seconds = $qty * 86400 * $sign;
				if($zeroTime) return @date('Y-m-d 00:00:00', @time() + $seconds);
				break;
		}

		return @date('Y-m-d H:i:s', @time() + $seconds);
	}

	#########################################################

	function addFilter($index, $filterAnd, $filterField, $filterOperator, $filterValue) {
		// validate input
		if($index < 1 || $index > 80 || !is_int($index)) return false;
		if($filterAnd != 'or')   $filterAnd = 'and';
		$filterField = intval($filterField);

		/* backward compatibility */
		if(in_array($filterOperator, FILTER_OPERATORS)) {
			$filterOperator = array_search($filterOperator, FILTER_OPERATORS);
		}

		if(!in_array($filterOperator, array_keys(FILTER_OPERATORS))) {
			$filterOperator = 'like';
		}

		if(!$filterField) {
			$filterOperator = '';
			$filterValue = '';
		}

		$_REQUEST['FilterAnd'][$index] = $filterAnd;
		$_REQUEST['FilterField'][$index] = $filterField;
		$_REQUEST['FilterOperator'][$index] = $filterOperator;
		$_REQUEST['FilterValue'][$index] = $filterValue;

		return true;
	}

	#########################################################

	function clearFilters() {
		for($i=1; $i<=80; $i++) {
			addFilter($i, '', 0, '', '');
		}
	}

	#########################################################

	/**
	* Loads a given view from the templates folder, passing the given data to it
	* @param $view the name of a php file (without extension) to be loaded from the 'templates' folder
	* @param $the_data_to_pass_to_the_view (optional) associative array containing the data to pass to the view
	* @return string the output of the parsed view
	*/
	function loadView($view, $the_data_to_pass_to_the_view = false) {
		global $Translation;

		$view = __DIR__ . "/templates/$view.php";
		if(!is_file($view)) return false;

		if(is_array($the_data_to_pass_to_the_view)) {
			foreach($the_data_to_pass_to_the_view as $data_k => $data_v)
				$$data_k = $data_v;
		}
		unset($the_data_to_pass_to_the_view, $data_k, $data_v);

		ob_start();
		@include($view);
		return ob_get_clean();
	}

	#########################################################

	/**
	* Loads a table template from the templates folder, passing the given data to it
	* @param $table_name the name of the table whose template is to be loaded from the 'templates' folder
	* @param $the_data_to_pass_to_the_table associative array containing the data to pass to the table template
	* @return the output of the parsed table template as a string
	*/
	function loadTable($table_name, $the_data_to_pass_to_the_table = []) {
		$dont_load_header = $the_data_to_pass_to_the_table['dont_load_header'];
		$dont_load_footer = $the_data_to_pass_to_the_table['dont_load_footer'];

		$header = $table = $footer = '';

		if(!$dont_load_header) {
			// try to load tablename-header
			if(!($header = loadView("{$table_name}-header", $the_data_to_pass_to_the_table))) {
				$header = loadView('table-common-header', $the_data_to_pass_to_the_table);
			}
		}

		$table = loadView($table_name, $the_data_to_pass_to_the_table);

		if(!$dont_load_footer) {
			// try to load tablename-footer
			if(!($footer = loadView("{$table_name}-footer", $the_data_to_pass_to_the_table))) {
				$footer = loadView('table-common-footer', $the_data_to_pass_to_the_table);
			}
		}

		return "{$header}{$table}{$footer}";
	}

	#########################################################

	function br2nl($text) {
		return  preg_replace('/\<br(\s*)?\/?\>/i', "\n", $text);
	}

	#########################################################

	function entitiesToUTF8($input) {
		return preg_replace_callback('/(&#[0-9]+;)/', '_toUTF8', $input);
	}

	function _toUTF8($m) {
		if(function_exists('mb_convert_encoding')) {
			return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
		} else {
			return $m[1];
		}
	}

	#########################################################

	function func_get_args_byref() {
		if(!function_exists('debug_backtrace')) return false;

		$trace = debug_backtrace();
		return $trace[1]['args'];
	}

	#########################################################

	function permissions_sql($table, $level = 'all') {
		if(!in_array($level, ['user', 'group'])) { $level = 'all'; }
		$perm = getTablePermissions($table);
		$from = '';
		$where = '';
		$pk = getPKFieldName($table);

		if($perm['view'] == 1 || ($perm['view'] > 1 && $level == 'user')) { // view owner only
			$from = 'membership_userrecords';
			$where = "(`$table`.`$pk`=membership_userrecords.pkValue and membership_userrecords.tableName='$table' and lcase(membership_userrecords.memberID)='" . getLoggedMemberID() . "')";
		} elseif($perm['view'] == 2 || ($perm['view'] > 2 && $level == 'group')) { // view group only
			$from = 'membership_userrecords';
			$where = "(`$table`.`$pk`=membership_userrecords.pkValue and membership_userrecords.tableName='$table' and membership_userrecords.groupID='" . getLoggedGroupID() . "')";
		} elseif($perm['view'] == 3) { // view all
			// no further action
		} elseif($perm['view'] == 0) { // view none
			return false;
		}

		return ['where' => $where, 'from' => $from, 0 => $where, 1 => $from];
	}

	#########################################################

	function error_message($msg, $back_url = '', $full_page = true) {
		global $Translation;

		ob_start();

		if($full_page) include(__DIR__ . '/header.php');

		echo '<div class="panel panel-danger">';
			echo '<div class="panel-heading"><h3 class="panel-title">' . $Translation['error:'] . '</h3></div>';
			echo '<div class="panel-body"><p class="text-danger">' . $msg . '</p>';
			if($back_url !== false) { // explicitly passing false suppresses the back link completely
				echo '<div class="text-center">';
				if($back_url) {
					echo '<a href="' . $back_url . '" class="btn btn-danger btn-lg vspacer-lg"><i class="glyphicon glyphicon-chevron-left"></i> ' . $Translation['< back'] . '</a>';
				// in embedded mode, close modal window
				} elseif(Request::val('Embedded')) {
					echo '<button class="btn btn-danger btn-lg" type="button" onclick="AppGini.closeParentModal();"><i class="glyphicon glyphicon-chevron-left"></i> ' . $Translation['< back'] . '</button>';
				} else {
					echo '<a href="#" class="btn btn-danger btn-lg vspacer-lg" onclick="history.go(-1); return false;"><i class="glyphicon glyphicon-chevron-left"></i> ' . $Translation['< back'] . '</a>';
				}
				echo '</div>';
			}
			echo '</div>';
		echo '</div>';

		if($full_page) include(__DIR__ . '/footer.php');

		return ob_get_clean();
	}

	#########################################################

	function toMySQLDate($formattedDate, $sep = datalist_date_separator, $ord = datalist_date_format) {
		// extract date elements
		$de=explode($sep, $formattedDate);
		$mySQLDate=intval($de[strpos($ord, 'Y')]).'-'.intval($de[strpos($ord, 'm')]).'-'.intval($de[strpos($ord, 'd')]);
		return $mySQLDate;
	}

	#########################################################

	function reIndex(&$arr) {
		$i=1;
		foreach($arr as $n=>$v) {
			$arr2[$i]=$n;
			$i++;
		}
		return $arr2;
	}

	#########################################################

	function get_embed($provider, $url, $max_width = '', $max_height = '', $retrieve = 'html') {
		global $Translation;
		if(!$url) return '';

		$providers = [
			'youtube' => ['oembed' => 'https://www.youtube.com/oembed', 'regex' => '/^http.*(youtu\.be|youtube\.com)\/.*/i'],
			'vimeo' => ['oembed' => 'https://vimeo.com/api/oembed.json', 'regex' => '/^http.*vimeo\.com\/.*/i'],
			'googlemap' => ['oembed' => '', 'regex' => '/^http.*\.google\..*maps/i'],
			'dailymotion' => ['oembed' => 'https://www.dailymotion.com/services/oembed', 'regex' => '/^http.*(dailymotion\.com|dai\.ly)\/.*/i'],
			'videofileurl' => ['oembed' => '', 'regex' => '/\.(mp4|webm|ogg|ogv)$/i'],
		];

		if(!$max_height) $max_height = 360;
		if(!$max_width) $max_width = 480;

		if(!isset($providers[$provider])) {
			// try detecting provider from URL based on regex
			foreach($providers as $p => $opts) {
				if(preg_match($opts['regex'], $url)) {
					$provider = $p;
					break;
				}
			}

			if(!isset($providers[$provider]))
				return '<div class="text-danger">' . $Translation['invalid provider'] . '</div>';
		}

		if(isset($providers[$provider]['regex']) && !preg_match($providers[$provider]['regex'], $url)) {
			return '<div class="text-danger">' . $Translation['invalid url'] . '</div>';
		}

		if($providers[$provider]['oembed']) {
			$oembed = $providers[$provider]['oembed'] . '?url=' . urlencode($url) . "&amp;maxwidth={$max_width}&amp;maxheight={$max_height}&amp;format=json";
			$data_json = request_cache($oembed);

			$data = json_decode($data_json, true);
			if($data === null) {
				/* an error was returned rather than a json string */
				if($retrieve == 'html') return "<div class=\"text-danger\">{$data_json}\n<!-- {$oembed} --></div>";
				return '';
			}

			// if html data not empty, apply max width and height in place of provided height and width
			$provided_width = $data['width'] ?? null;
			$provided_height = $data['height'] ?? null;
			if($provided_width && $provided_height) {
				$aspect_ratio = $provided_width / $provided_height;
				if($max_width / $aspect_ratio < $max_height) {
					$max_height = intval($max_width / $aspect_ratio);
				} else {
					$max_width = intval($max_height * $aspect_ratio);
				}

				$data['html'] = str_replace("width=\"{$provided_width}\"", "width=\"{$max_width}\"", $data['html']);
				$data['html'] = str_replace("height=\"{$provided_height}\"", "height=\"{$max_height}\"", $data['html']);
			}

			return (isset($data[$retrieve]) ? $data[$retrieve] : $data['html']);
		}

		/* special cases (where there is no oEmbed provider) */
		if($provider == 'googlemap') return get_embed_googlemap($url, $max_width, $max_height, $retrieve);
		if($provider == 'videofileurl') return get_embed_videofileurl($url, $max_width, $max_height, $retrieve);

		return '<div class="text-danger">' . $Translation['invalid provider'] . '</div>';
	}

	#########################################################

	function get_embed_videofileurl($url, $max_width = '', $max_height = '', $retrieve = 'html') {
		global $Translation;

		$allowed_exts = ['mp4', 'webm', 'ogg', 'ogv'];
		$ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));

		if(!in_array($ext, $allowed_exts)) {
			return '<div class="text-danger">' . $Translation['invalid url'] . '</div>';
		}

		$video = "<video controls style=\"max-width: 100%%; height: auto;\" src=\"%s\"></video>";

		switch($retrieve) {
			case 'html':
				return sprintf($video, $url);
			default: // 'thumbnail'
				return '';
		}
	}

	#########################################################

	function get_embed_googlemap($url, $max_width = '', $max_height = '', $retrieve = 'html') {
		global $Translation;
		$url_parts = parse_url($url);
		$coords_regex = '/-?\d+(\.\d+)?[,+]-?\d+(\.\d+)?(,\d{1,2}z)?/'; /* https://stackoverflow.com/questions/2660201 */

		if(!preg_match($coords_regex, $url_parts['path'] . '?' . $url_parts['query'], $m))
			return '<div class="text-danger">' . $Translation['cant retrieve coordinates from url'] . '</div>';

		list($lat, $long, $zoom) = explode(',', $m[0]);
		$zoom = intval($zoom);
		if(!$zoom) $zoom = 15; /* default zoom */
		if(!$max_height) $max_height = 360;
		if(!$max_width) $max_width = 480;

		$api_key = config('adminConfig')['googleAPIKey'];

		// if max_height is all numeric, append 'px' to it
		$frame_height = $max_height;
		if(is_numeric($frame_height)) $frame_height .= 'px';

		$embed_url = 'https://www.google.com/maps/embed/v1/%s?' . http_build_query([
			'key' => $api_key,
			'zoom' => $zoom,
			'maptype' => 'roadmap',
		], '', '&amp;');

		$thumbnail_url = 'https://maps.googleapis.com/maps/api/staticmap?' . http_build_query([
			'key' => $api_key,
			'zoom' => $zoom,
			'maptype' => 'roadmap',
			'size' => "{$max_width}x{$max_height}",
			'center' => "$lat,$long",
		], '', '&amp;');

		$iframe = "<iframe allowfullscreen loading=\"lazy\" style=\"border: none; width: 100%%; height: $frame_height;\" src=\"%s\" referrerpolicy=\"no-referrer-when-downgrade\"></iframe>";

		switch($retrieve) {
			case 'html':
				$embed_url = sprintf($embed_url, 'view') . '&amp;' . http_build_query(['center' => "$lat,$long"]);
				return sprintf($iframe, $embed_url);
			case 'html-pinpoint':
				$embed_url = sprintf($embed_url, 'place') . '&amp;' . http_build_query(['q' => "$lat,$long"]);
				return sprintf($iframe, $embed_url);
			case 'thumbnail-pinpoint':
				return $thumbnail_url . '&amp;' . http_build_query(['markers' => "$lat,$long"]);
			default: // 'thumbnail'
				return $thumbnail_url;
		}
	}

	#########################################################

	function request_cache($request, $force_fetch = false) {
		static $cache_table_exists = null;
		$max_cache_lifetime = 7 * 86400; /* max cache lifetime in seconds before refreshing from source */

		// force fetching request if no cache table exists
		if($cache_table_exists === null)
			$cache_table_exists = sqlValue("show tables like 'membership_cache'");

		if(!$cache_table_exists)
			return request_cache($request, true);

		/* retrieve response from cache if exists */
		if(!$force_fetch) {
			$res = sql("select response, request_ts from membership_cache where request='" . md5($request) . "'", $eo);
			if(!$row = db_fetch_array($res)) return request_cache($request, true);

			$response = $row[0];
			$response_ts = $row[1];
			if($response_ts < time() - $max_cache_lifetime) return request_cache($request, true);
		}

		/* if no response in cache, issue a request */
		if(!$response || $force_fetch) {
			$response = @file_get_contents($request);
			if($response === false) {
				$error = error_get_last();
				$error_message = preg_replace('/.*: (.*)/', '$1', $error['message']);
				return $error_message;
			} elseif($cache_table_exists) {
				/* store response in cache */
				$ts = time();
				sql("replace into membership_cache set request='" . md5($request) . "', request_ts='{$ts}', response='" . makeSafe($response, false) . "'", $eo);
			}
		}

		return $response;
	}

	#########################################################

	function check_record_permission($table, $id, $perm = 'view') {
		if($perm != 'edit' && $perm != 'delete') $perm = 'view';

		$perms = getTablePermissions($table);
		if(!$perms[$perm]) return false;

		$safe_id = makeSafe($id);
		$safe_table = makeSafe($table);

		// fix for zero-fill: quote id only if not numeric
		if(!is_numeric($safe_id)) $safe_id = "'$safe_id'";

		if($perms[$perm] == 1) { // own records only
			$username = getLoggedMemberID();
			$owner = sqlValue("select memberID from membership_userrecords where tableName='{$safe_table}' and pkValue={$safe_id}");
			if($owner == $username) return true;
		} elseif($perms[$perm] == 2) { // group records
			$group_id = getLoggedGroupID();
			$owner_group_id = sqlValue("select groupID from membership_userrecords where tableName='{$safe_table}' and pkValue={$safe_id}");
			if($owner_group_id == $group_id) return true;
		} elseif($perms[$perm] == 3) { // all records
			return true;
		}

		return false;
	}

	#########################################################

	function NavMenus($options = []) {
		if(!defined('PREPEND_PATH')) define('PREPEND_PATH', '');
		global $Translation;
		$prepend_path = PREPEND_PATH;

		/* default options */
		if(empty($options)) {
			$options = ['tabs' => 7];
		}

		$table_group_name = array_keys(get_table_groups()); /* 0 => group1, 1 => group2 .. */
		/* if only one group named 'None', set to translation of 'select a table' */
		if((count($table_group_name) == 1 && $table_group_name[0] == 'None') || count($table_group_name) < 1) $table_group_name[0] = $Translation['select a table'];
		$table_group_index = array_flip($table_group_name); /* group1 => 0, group2 => 1 .. */
		$menu = array_fill(0, count($table_group_name), '');

		$t = time();
		$arrTables = getTableList();
		if(is_array($arrTables)) {
			foreach($arrTables as $tn => $tc) {
				/* ---- list of tables where hide link in nav menu is set ---- */
				$tChkHL = array_search($tn, []);

				/* ---- list of tables where filter first is set ---- */
				$tChkFF = array_search($tn, []);
				if($tChkFF !== false && $tChkFF !== null) {
					$searchFirst = '&Filter_x=1';
				} else {
					$searchFirst = '';
				}

				/* when no groups defined, $table_group_index['None'] is NULL, so $menu_index is still set to 0 */
				$menu_index = intval($table_group_index[$tc[3]]);
				if(!$tChkHL && $tChkHL !== 0) $menu[$menu_index] .= "<li><a href=\"{$prepend_path}{$tn}_view.php?t={$t}{$searchFirst}\"><img src=\"{$prepend_path}" . ($tc[2] ? $tc[2] : 'blank.gif') . "\" height=\"32\"> {$tc[0]}</a></li>";
			}
		}

		// custom nav links, as defined in "hooks/links-navmenu.php"
		global $navLinks;
		if(is_array($navLinks)) {
			$memberInfo = getMemberInfo();
			$links_added = [];
			foreach($navLinks as $link) {
				if(!isset($link['url']) || !isset($link['title'])) continue;
				if(getLoggedAdmin() !== false || @in_array($memberInfo['group'], $link['groups']) || @in_array('*', $link['groups'])) {
					$menu_index = intval($link['table_group']);
					if(!$links_added[$menu_index]) $menu[$menu_index] .= '<li class="divider"></li>';

					/* add prepend_path to custom links if they aren't absolute links */
					if(!preg_match('/^(http|\/\/)/i', $link['url'])) $link['url'] = $prepend_path . $link['url'];
					if(!preg_match('/^(http|\/\/)/i', $link['icon']) && $link['icon']) $link['icon'] = $prepend_path . $link['icon'];

					$menu[$menu_index] .= "<li><a href=\"{$link['url']}\"><img src=\"" . ($link['icon'] ? $link['icon'] : "{$prepend_path}blank.gif") . "\" height=\"32\"> {$link['title']}</a></li>";
					$links_added[$menu_index]++;
				}
			}
		}

		$menu_wrapper = '';
		for($i = 0; $i < count($menu); $i++) {
			$menu_wrapper .= <<<EOT
				<li class="dropdown">
					<a href="#" class="dropdown-toggle" data-toggle="dropdown">{$table_group_name[$i]} <b class="caret"></b></a>
					<ul class="dropdown-menu" role="menu">{$menu[$i]}</ul>
				</li>
EOT;
		}

		return $menu_wrapper;
	}

	#########################################################

	function StyleSheet() {
		if(!defined('PREPEND_PATH')) define('PREPEND_PATH', '');
		$prepend_path = PREPEND_PATH;
		$mtime = filemtime( __DIR__ . '/dynamic.css');
		$theme = getUserTheme();
		$theme3d = ($theme == 'bootstrap' && BOOTSTRAP_3D_EFFECTS ? '<link rel="stylesheet" href="' . PREPEND_PATH . 'resources/initializr/css/bootstrap-theme.css">' . "\n" : '');

		$css_links = <<<EOT

			<link rel="stylesheet" href="{$prepend_path}resources/initializr/css/{$theme}.css">
			{$theme3d}
			<link rel="stylesheet" href="{$prepend_path}resources/select2/select2.css" media="screen">
			<link rel="stylesheet" href="{$prepend_path}resources/timepicker/bootstrap-timepicker.min.css" media="screen">
			<link rel="stylesheet" href="{$prepend_path}dynamic.css?{$mtime}">
EOT;

		return $css_links;
	}

	#########################################################

	function PrepareUploadedFile($FieldName, $MaxSize, $FileTypes = 'jpg|jpeg|gif|png|webp', $NoRename = false, $dir = '') {
		global $Translation;
		$f = $_FILES[$FieldName];
		if($f['error'] == 4 || !$f['name']) return '';

		$dir = getUploadDir($dir);

		/* get php.ini upload_max_filesize in bytes */
		$php_upload_size_limit = toBytes(ini_get('upload_max_filesize'));
		$MaxSize = min($MaxSize, $php_upload_size_limit);

		if($f['size'] > $MaxSize || $f['error']) {
			echo error_message(str_replace(['<MaxSize>', '{MaxSize}'], intval($MaxSize / 1024), $Translation['file too large']));
			exit;
		}
		if(!preg_match('/\.(' . $FileTypes . ')$/i', $f['name'], $ft)) {
			echo error_message(str_replace(['<FileTypes>', '{FileTypes}'], str_replace('|', ', ', $FileTypes), $Translation['invalid file type']));
			exit;
		}

		$name = str_replace(' ', '_', $f['name']);
		if(!$NoRename) $name = substr(md5(microtime() . rand(0, 100000)), -17) . $ft[0];

		if(!file_exists($dir)) @mkdir($dir, 0777);

		if(!@move_uploaded_file($f['tmp_name'], $dir . $name)) {
			echo error_message("Couldn't save the uploaded file. Try chmoding the upload folder '{$dir}' to 777.");
			exit;
		}

		@chmod($dir . $name, 0666);
		return $name;
	}

	#########################################################

	function get_home_links($homeLinks, $default_classes, $tgroup = '') {
		if(!is_array($homeLinks) || !count($homeLinks)) return '';

		$memberInfo = getMemberInfo();

		ob_start();
		foreach($homeLinks as $link) {
			if(!isset($link['url']) || !isset($link['title'])) continue;
			if($tgroup != $link['table_group'] && $tgroup != '*') continue;

			/* fall-back classes if none defined */
			if(!$link['grid_column_classes']) $link['grid_column_classes'] = $default_classes['grid_column'];
			if(!$link['panel_classes']) $link['panel_classes'] = $default_classes['panel'];
			if(!$link['link_classes']) $link['link_classes'] = $default_classes['link'];

			if(getLoggedAdmin() !== false || @in_array($memberInfo['group'], $link['groups']) || @in_array('*', $link['groups'])) {
				?>
				<div class="col-xs-12 <?php echo $link['grid_column_classes']; ?>">
					<div class="panel <?php echo $link['panel_classes']; ?>">
						<div class="panel-body">
							<a class="btn btn-block btn-lg <?php echo $link['link_classes']; ?>" title="<?php echo preg_replace("/&amp;(#[0-9]+|[a-z]+);/i", "&$1;", html_attr(strip_tags($link['description']))); ?>" href="<?php echo $link['url']; ?>"><?php echo ($link['icon'] ? '<img src="' . $link['icon'] . '">' : ''); ?><strong><?php echo $link['title']; ?></strong></a>
							<div class="panel-body-description"><?php echo $link['description']; ?></div>
						</div>
					</div>
				</div>
				<?php
			}
		}

		return ob_get_clean();
	}

	#########################################################

	function quick_search_html($search_term, $label, $separate_dv = true) {
		global $Translation;

		$safe_search = html_attr($search_term);
		$safe_label = html_attr($label);
		$safe_clear_label = html_attr($Translation['Reset Filters']);

		if($separate_dv) {
			$reset_selection = "document.forms[0].SelectedID.value = '';";
		} else {
			$reset_selection = "document.forms[0].writeAttribute('novalidate', 'novalidate');";
		}
		$reset_selection .= ' document.forms[0].NoDV.value=1; return true;';

		$html = <<<EOT
		<div class="input-group" id="quick-search">
			<input type="text" id="SearchString" name="SearchString" value="{$safe_search}" class="form-control" placeholder="{$safe_label}">
			<span class="input-group-btn">
				<button name="Search_x" value="1" id="Search" type="submit" onClick="{$reset_selection}" class="btn btn-default" title="{$safe_label}"><i class="glyphicon glyphicon-search"></i></button>
				<button name="ClearQuickSearch" value="1" id="ClearQuickSearch" type="submit" onClick="\$j('#SearchString').val(''); {$reset_selection}" class="btn btn-default" title="{$safe_clear_label}"><i class="glyphicon glyphicon-remove-circle"></i></button>
			</span>
		</div>
EOT;
		return $html;
	}

	#########################################################

	function getLookupFields($skipPermissions = false, $filterByPermission = 'view') {
		$pcConfig = [
			'actividades' => [
			],
			'actividades_encargos' => [
			],
			'agencias' => [
			],
			'clientes' => [
			],
			'detalles_procedencia' => [
			],
			'direcciones' => [
			],
			'encargos' => [
			],
			'estados_contacto' => [
			],
			'estados_encargo' => [
			],
			'estados_noticia' => [
			],
			'inmuebles' => [
			],
			'modalidades_contacto' => [
			],
			'motivos_cierre' => [
			],
			'noticias' => [
			],
			'prioridades' => [
			],
			'subtipologias_inmueble' => [
			],
			'subzonas' => [
			],
			'tipologias_inmueble' => [
			],
			'tipos_actividad' => [
			],
			'tipos_procedencia' => [
			],
			'usuarios' => [
			],
			'zonas' => [
			],
		];

		if($skipPermissions) return $pcConfig;

		if(!in_array($filterByPermission, ['access', 'insert', 'edit', 'delete'])) $filterByPermission = 'view';

		/**
		* dynamic configuration based on current user's permissions
		* $userPCConfig array is populated only with parent tables where the user has access to
		* at least one child table
		*/
		$userPCConfig = [];
		foreach($pcConfig as $tn => $lookupFields) {
			$perm = getTablePermissions($tn);
			if(!$perm[$filterByPermission]) continue;

			foreach($lookupFields as $fn => $ChildConfig) {
				$permParent = getTablePermissions($ChildConfig['parent-table']);
				if(!$permParent[$filterByPermission]) continue;

				$userPCConfig[$tn][$fn] = $pcConfig[$tn][$fn];
				// show add new only if configured above AND the user has insert permission
				$userPCConfig[$tn][$fn]['display-add-new'] = ($perm['insert'] && $pcConfig[$tn][$fn]['display-add-new']);
			}
		}

		return $userPCConfig;
	}

	#########################################################

	function getChildTables($parentTable, $skipPermissions = false, $filterByPermission = 'view') {
		$pcConfig = getLookupFields($skipPermissions, $filterByPermission);
		$childTables = [];
		foreach($pcConfig as $tn => $lookupFields)
			foreach($lookupFields as $fn => $ChildConfig)
				if($ChildConfig['parent-table'] == $parentTable)
					$childTables[$tn][$fn] = $ChildConfig;

		return $childTables;
	}

	#########################################################

	function isDetailViewEnabled($tn) {
		$tables = ['actividades', 'actividades_encargos', 'agencias', 'clientes', 'detalles_procedencia', 'direcciones', 'encargos', 'estados_contacto', 'estados_encargo', 'estados_noticia', 'inmuebles', 'modalidades_contacto', 'motivos_cierre', 'noticias', 'prioridades', 'subtipologias_inmueble', 'subzonas', 'tipologias_inmueble', 'tipos_actividad', 'tipos_procedencia', 'usuarios', 'zonas', ];
		return in_array($tn, $tables);
	}

	#########################################################

	function appDir($path = '') {
		// if path not empty and doesn't start with a slash, add it
		if($path && $path[0] != '/') $path = '/' . $path;
		return __DIR__ . $path;
	}

	#########################################################

	/**
	 * Inserts a new record in a table, performing various before and after tasks
	 * @param string $tableName the name of the table to insert into
	 * @param array $data associative array of field names and values to insert
	 * @param string $recordOwner the username of the record owner
	 * @param string $errorMessage error message to be set in case of failure
	 *
	 * @return mixed the ID of the inserted record if successful, false otherwise
	 */
	function tableInsert($tableName, $data, $recordOwner, &$errorMessage = '') {
		global $Translation;

		// mm: can member insert record?
		if(!getTablePermissions($tableName)['insert']) {
			$errorMessage = $Translation['no insert permission'];
			return false;
		}

		$memberInfo = getMemberInfo();

		// check for required fields
		$fields = get_table_fields($tableName);
		$notNullFields = notNullFields($tableName);
		foreach($notNullFields as $fieldName) {
			if($data[$fieldName] !== '') continue;

			$errorMessage = "{$fields[$fieldName]['info']['caption']}: {$Translation['field not null']}";
			return false;
		}

		@include_once(__DIR__ . "/hooks/{$tableName}.php");

		// hook: before_insert
		$beforeInsertFunc = "{$tableName}_before_insert";
		if(function_exists($beforeInsertFunc)) {
			$args = [];
			if(!$beforeInsertFunc($data, $memberInfo, $args)) {
				if(isset($args['error_message'])) $errorMessage = $args['error_message'];
				return false;
			}
		}

		$pkIsAutoInc = pkIsAutoIncrement($tableName);
		$pkField = getPKFieldName($tableName) ?: '';

		$error = '';
		// set empty fields to NULL
		$data = array_map(function($v) { return ($v === '' ? NULL : $v); }, $data);
		insert($tableName, backtick_keys_once($data), $error);
		if($error) {
			$errorMessage = $error;
			return false;
		}

		$recID = $pkIsAutoInc ? db_insert_id() : ($data[$pkField] ?? false);

		update_calc_fields($tableName, $recID, calculated_fields()[$tableName]);

		// hook: after_insert
		$afterInsertFunc = "{$tableName}_after_insert";
		if(function_exists($afterInsertFunc)) {
			if($row = getRecord($tableName, $recID)) {
				$data = array_map('makeSafe', $row);
			}
			$data['selectedID'] = makeSafe($recID);
			$args = [];
			if(!$afterInsertFunc($data, $memberInfo, $args)) { return $recID; }
		}

		// mm: save ownership data
		// record owner is current user
		set_record_owner($tableName, $recID, $recordOwner);

		return $recID;
	}

	#########################################################

	/**
	 * Checks whether the primary key of a table is auto-increment
	 * @param string $tn the name of the table
	 *
	 * @return bool true if the primary key is auto-increment, false otherwise
	 */
	function pkIsAutoIncrement($tn) {
		// caching
		static $cache = [];

		if(isset($cache[$tn])) return $cache[$tn];

		$pk = getPKFieldName($tn);
		if(!$pk) {
			$cache[$tn] = false;
			return false;
		}

		$isAutoInc = sqlValue("SHOW COLUMNS FROM `$tn` WHERE Field='{$pk}' AND Extra LIKE '%auto_increment%'");
		$cache[$tn] = $isAutoInc ? true : false;
		return $cache[$tn];
	}

	#########################################################

	/**
	 * @return bool true if the current user is an admin and revealing SQL is allowed, false otherwise
	 */
	function showSQL() {
		$allowAdminShowSQL = true;
		return $allowAdminShowSQL && getLoggedAdmin() !== false;
	}

	#########################################################

	/**
	 * Compact filters by removing empty conditions and groups
	 * @param array $FilterAnd array of filter AND/OR conditions, passed by reference
	 * @param array $FilterField array of filter field indices, passed by reference
	 * @param array $filterOperator array of filter operators, passed by reference
	 * @param array $FilterValue array of filter values, passed by reference
	 */
	function compactFilters(&$FilterAnd, &$FilterField, &$FilterOperator, &$FilterValue) {

		// TODO: move to definitions.php as constants
		$filterConditionsPerGroup = 4; // Number of filter conditions per group
		$filterGroups = datalist_filters_count / $filterConditionsPerGroup; // Number of filter groups

		$filterConditionIsEmpty = function($i) use ($FilterField, $FilterOperator) {
			// check if filter is empty
			return !$FilterField[$i] || !$FilterOperator[$i];
		};

		$filterGroupIsEmpty = function($i) use ($filterConditionIsEmpty, $filterConditionsPerGroup) {
			// check if filter group is empty
			for($j = 1; $j <= $filterConditionsPerGroup; $j++) {
				if(!$filterConditionIsEmpty(($i - 1) * $filterConditionsPerGroup + $j)) {
					return false;
				}
			}
			return true;
		};

		// 'compact' filter conditions by removing gaps inside each group and removing empty groups
		$compactedGroups = [];
		for($gi = 1; $gi <= $filterGroups; $gi++) {
			$compactedGroups[$gi] = [];
			for($fi = 1; $fi <= $filterConditionsPerGroup; $fi++) {
				$filterIndex = (($gi - 1) * $filterConditionsPerGroup) + $fi;
				if(!$filterConditionIsEmpty($filterIndex)) {
					$compactedGroups[$gi][] = $filterIndex;
				}
			}
		}

		// rmove empty groups
		$compactedGroups = array_filter($compactedGroups, function($group) {
			return count($group) > 0;
		});

		// re-index groups
		$compactedGroups = array_values($compactedGroups);

		// now rebuild filters based on the compacted groups
		$newFilterAnd = $newFilterField = $newFilterOperator = $newFilterValue = [];
		foreach($compactedGroups as $gi0b => $group) {
			foreach($group as $fi0b => $fi) {
				$filterIndex = $gi0b * $filterConditionsPerGroup + $fi0b + 1;
				$newFilterAnd[$filterIndex] = $FilterAnd[$fi];
				$newFilterField[$filterIndex] = $FilterField[$fi];
				$newFilterOperator[$filterIndex] = $FilterOperator[$fi];
				$newFilterValue[$filterIndex] = $FilterValue[$fi];
			}
		}

		// update filter variables
		$FilterAnd = $newFilterAnd;
		$FilterField = $newFilterField;
		$FilterOperator = $newFilterOperator;
		$FilterValue = $newFilterValue;
	}

