<?php

$fname = $argv[1];

$data = json_decode(file_get_contents($fname), true);


$patches = isset($data['patches']) ? $data['patches'] : $data['extra']['patches'];

foreach ($patches as $module => &$patchDefs) {
   $newDefs = [];

   foreach ($patchDefs as $description => $filename) {
	$newDefs[] = [
		'description' => $description,
		'filename' => substr($filename, strlen('vendor/creativestyle/magento-patches/')),
		'version-constraint' => '*'
	];
   }

   $patchDefs = $newDefs;
}

echo json_encode($patches, JSON_PRETTY_PRINT + JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES);

