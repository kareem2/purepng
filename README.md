

**Installing**

- Run `composer install` command to install libraries.
- Create the database using `purepng.sql` file.
- Create a folder for images.
- Create a folder for chache, name it `cache`.

**Configuration**

`config.php` file contains the main config parameter for the scraper, you can change the datatbse credentialsfrom it, here is a sample of configuration parameters:

`database_host`: database host IP.

`database_db_name`: database name.

`database_username`: db username.

`database_password`: db password.

`php_timezone`: preferred server timezone, default: UTC.

`db_timezone`: preferred database timezone, default: '+00:00'.

`thumbnail_height` prefereed thumbnail height, default: 200. This parameter will used to create photos thumbnails that will be used for photo preview.

`images_folder`: main images folder, the scraper will save the images into that folder, example: '../purepng/public/uploads/large'.

`thumbnail_folder` thumbnail folder, example: '../purepng/public/uploads/thumbnail'.

`avatar_folder`: users avatars folder, example: '../purepng/public/img/avatars'.


`cloudflare_bypass_options`: is used for bypassing cloudflare protection.

```
$config['cloudflare_bypass_options'] = 	    
array( 
	'max_retries'   => 5,                   // How many times to try and get clearance?
	'cache'         => true,               // Enable caching?
	'cache_path'    => __DIR__ . '/cache',  // Where to cache cookies? (Default: system tmp directory)
	'verbose'       => false                 // Enable verbose? (Good for debugging issues - doesn't effect cURL handle);
);
```
**Running the scraper**

Navigate to the scraper folder and run the following command to scrape a page from purepng:
> $ php scraper.php 14
The previous command will scrape the content from:
[https://purepng.com/photo/14](https://purepng.com/photo/14)

You can also pass range of photos IDs like:
> $ php scraper.php 1 1000
This command will scrape photos starting from 1 to 1000



**Notes**
The website use DDoS attack prevention service, so it may take some time to scrape the first page, so, it is recommended to enable cache in the config file to reduce the scraping time.