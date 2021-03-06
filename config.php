<?php

ini_set('memory_limit', '-1');
ini_set('set_memory_limit', -1);
ini_set('max_execution_time' ,0);   

$config['database_host'] = 'localhost';
$config['database_db_name'] = 'purepng';
$config['database_username'] = 'root';
$config['database_password'] = '';
$config['php_timezone'] = 'UTC';
$config['db_timezone'] = '+00:00';
$config['thumbnail_height'] = '200';



$config['images_folder'] = '../purepng/public/uploads/large';
$config['thumbnail_folder'] = '../purepng/public/uploads/thumbnail';
$config['avatar_folder'] = '../purepng/public/img/avatars';


$config['cloudflare_bypass_options'] = 	    
array( 
	'max_retries'   => 5,                   // How many times to try and get clearance?
	'cache'         => true,               // Enable caching?
	'cache_path'    => __DIR__ . '/cache',  // Where to cache cookies? (Default: system tmp directory)
	'verbose'       => false                 // Enable verbose? (Good for debugging issues - doesn't effect cURL handle);
);


date_default_timezone_set($config['php_timezone']);