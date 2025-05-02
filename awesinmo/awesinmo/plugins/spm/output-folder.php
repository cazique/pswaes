<?php
	include(__DIR__ . '/header.php');

	$axp_md5 = Request::val('axp');
	$projectFile = '';
	$xmlFile = $spm->get_xml_file($axp_md5 , $projectFile);

	echo $spm->header_nav();

	echo $spm->breadcrumb([
		'index.php' => 'Projects',
		'project.php?axp=' . urlencode($axp_md5) => substr($projectFile, 0, -4),
		'' => 'Output folder'
	]);

	echo $spm->show_select_output_folder([
		'next_page' => 'generate.php?axp=' . urlencode(Request::val('axp')),
		'extra_options' => [
			'dont_write_to_hooks' => 'Only show me the hooks code without actually writing it to existing hook files.'
		]
	]);

	include(__DIR__ . "/footer.php");