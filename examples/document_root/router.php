<?php
chdir(__DIR__);
//~ if (preg_match('/\.(?:pdf|js|ico|gif|jpg|png|css|rar|zip)$/', $_SERVER["PHP_SELF"])) {
//~ if (preg_match('/\.(?:pdf|js|ico|css|rar|zip)$/', $_SERVER["PHP_SELF"])) {
// serve the requested resource as-is.
if (preg_match('/\.(?:pdf|js|ico|jpeg|png|css)$/', $_SERVER["PHP_SELF"])) {
	$name = __dir__ . $_SERVER["PHP_SELF"];
	switch (pathinfo($name, PATHINFO_EXTENSION)) {
		case 'css':
			$mime = 'text/css';
			break;
		case 'js':
			$mime = 'text/javascript';
			break;
		case 'png':
			$mime = 'image/png';
			break;
		case 'jpeg':
			$mime = 'image/jpeg';
			break;
		case 'ico':
			$mime = 'image/ico';
			break;
		case 'pdf':
			$mime = 'application/pdf';
			break;
		default:
			$mime = mime_content_type($name);
	}

	header("Content-Type: $mime");
	header("Content-Length: " . filesize($name));

	$fp = fopen($name, 'rb');
	fpassthru($fp);

	exit;
}
// serve via index.php
//~ $_SERVER['PATH_INFO'] = $_SERVER['PHP_SELF'];
//~ $_SERVER['PHP_SELF'] = '/index.php' . $_SERVER['PHP_SELF'];
$_SERVER['SCRIPT_NAME'] = 'index.php';
require __dir__ . '/index.php';
