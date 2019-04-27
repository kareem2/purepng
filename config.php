<?php

$config['images_folder'] = 'images';

$config['cloudflare_bypass_options'] = 	    
array( 
	'max_retries'   => 5,                   // How many times to try and get clearance?
	'cache'         => true,               // Enable caching?
	'cache_path'    => __DIR__ . '/cache',  // Where to cache cookies? (Default: system tmp directory)
	'verbose'       => false                 // Enable verbose? (Good for debugging issues - doesn't effect cURL handle);
);

