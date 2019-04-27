
**Installing**

- Run `composer install` command to install libraries.
- Create the database using `purepng.sql` file.
- Create a folder for images.
- Create a folder for chache, name it `cache`.

**Configuration**

`config.php` file contains the main config parameter for the scraper, you can change the datatbse credentialsfrom it, here is a sample of configuration parameters:
```
$config['database_host'] = 'localhost'; 
$config['database_db_name'] = 'purepng';
$config['database_username'] = 'root';
$config['database_password'] = '';

$config['images_folder'] = 'images'; // Same as the name created before.

$config['cloudflare_bypass_options'] = 	    
array( 
	'max_retries'   => 5,                   // How many times to try and get clearance?
	'cache'         => true,               // Enable caching?
	'cache_path'    => __DIR__ . '/cache',  // Where to cache cookies? (Default: system tmp directory)
	'verbose'       => false                 // Enable verbose? (Good for debugging issues - doesn't effect cURL handle);
);
```
**Running the scraper**

Navigate to the scraper folder and run the following command to scrape  a page from purepng:
> $ php scraper.php 914 

The previous command will scrape the content from:
[https://purepng.com/photo/14](https://purepng.com/photo/14)

**Notes**
The website use DDoS attack prevention service, so it may take some time to scrape the first page, so, it is recommended to enable cache in the config file to reduce the scraping time.