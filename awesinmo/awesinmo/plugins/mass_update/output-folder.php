<?php

	include(__DIR__ . '/header.php');

	$axp_md5 = Request::val('axp');
	$projectFile = '';
	$xmlFile = $mass_update->get_xml_file($axp_md5 , $projectFile);


	echo $mass_update->header_nav();

	echo $mass_update->breadcrumb([
		'index.php' => 'Projects',
		'project.php?axp=' . urlencode($axp_md5) => substr($projectFile, 0, -4),
		'' => 'Output folder',
	]); 

	echo $mass_update->show_select_output_folder([
		'next_page' => 'generate.php?axp=' . urlencode($axp_md5),
		'extra_options' => []
	]);

	include(__DIR__ . '/footer.php');
