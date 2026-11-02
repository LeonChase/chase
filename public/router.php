<?php
$path=parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file=realpath(__DIR__.$path);
if ($path!=='/' && $file && str_starts_with($file,__DIR__.DIRECTORY_SEPARATOR) && is_file($file) && preg_match('/\.(css|js|svg|png|ico|woff2)$/i',$file)) { return false; }
// PHP's development server can identify this router as the requested script.
// Symfony Runtime must load the front controller, which returns the app factory.
$_SERVER['SCRIPT_FILENAME']=__DIR__.'/index.php';
return require __DIR__.'/index.php';
