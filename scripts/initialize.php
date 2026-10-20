<?php
$root=dirname(__DIR__);
if(!is_dir($root.'/var'))mkdir($root.'/var',0700,true);
$file=$root.'/.env.local';
if(!is_file($file)){file_put_contents($file,"APP_SECRET=".bin2hex(random_bytes(32))."\n");chmod($file,0600);}
echo "Lokale Konfiguration geprüft.\n";
