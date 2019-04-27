<?php
require_once 'vendor/autoload.php';
require_once 'config.php';

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\TransferStats;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;
use PHPHtmlParser\Dom;
use CloudflareBypass\RequestMethod\CFCurl;


$db = new MysqliDb ('localhost', 'root', '', 'purepng');

error_reporting(0);

$args = $argv;

$id = $args[1];

if(!isset($id))
	die('Enter image id');


$images_path = $config['images_folder'];


if(checkPost($id, $db)){
	echo "post already scraped";
	die();
}

echo "scrape post....\r\n";
$data = scrapePost($id);


echo "download iamge....\r\n";

$image_name = base64_encode($data['title']).$id.'.png';
$image_name_path = $images_path.'/'. $image_name;
file_put_contents($image_name_path, simpleCurlRrequest($data['main_image_url'])['response']);
$data['image_name'] = $image_name;



echo "save to the datatabse\r\n";
insertPost($data, $db);

echo "done\r\n";






function scrapePost($post_id){
	$url = "https://purepng.com/photo/".$post_id;
	$curl_result  = simpleCurlRrequest($url);
	$response  = $curl_result['response'];


	//echo $response;


	$dom = new Dom;
	$dom->loadStr($response, []);

	$data = [];
	$data['purepng_id'] = $post_id;
	$data['status'] = $curl_result['http_code'];
	$data['username'] = strip_tags($dom->find('.text-username', 0)->innerHtml);
	$data['title'] = $dom->find('h1', 0)->innerHtml;
	$data['description'] = $dom->find('.description', 0)->innerHtml;



	$data['main_image_url'] = $dom->find('.img-responsive', 0)->src; 

	foreach ($dom->find('.arrowDownload', 0)->find('a') as $image) {
		$data['download_image_urls'][] = $image->href;
	}

	$block = $dom->find('.col-md-3', 0)->find('ul[class=list-group]', 0);

	if($block){
		//echo $block->innerHtml;

		$data['publish_on'] = $block->find('.list-group-item', 1)->find('span', 0)->innerHtml;
		$data['image_type'] = $block->find('.list-group-item', 2)->find('span', 0)->innerHtml;
		$data['resolution'] = explode('x', $block->find('.list-group-item', 3)->find('span', 0)->innerHtml);
		$data['category'] = strip_tags($block->find('.list-group-item', 4)->find('span', 0)->innerHtml);
		$data['file_size'] = $block->find('.list-group-item', 5)->find('span', 0)->innerHtml;


	}

	foreach ($dom->find('.colorPalette') as $colorPalette) {
		$data_title = "data-original-title";
		$data['color_palette'][] = str_replace(';', '', explode(': ', $colorPalette->style)[1]);
	}

	foreach ($dom->find('.tags') as $tag) {
		$data['tags'][] = $tag->innerHtml;
	}

	$featured_on = $dom->find('.icon-Medal', 0);
	if($featured_on){
		$data['featured_on'] = $featured_on->find('strong', 0)->innerHtml;
	}

	return $data;
}

function simpleCurlRrequest($url){
	global $config;
	$curl_cf_wrapper = new CFCurl($config['cloudflare_bypass_options']);

	$timeout = 50;
	$no_body = false;
	$type = 'GET';

	if(isset($options['timeout']))
		$timeout = $options['timeout'];

	if(isset($options['no_body']))
		$no_body = $options['no_body'];

	if(isset($options['type']))
		$type = $options['type'];	

	$headers = array(
	    "Cache-Control: no-cache",
	    "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36",
	);

	if(isset($options['content_type'])){
		$headers[] = "Content-Type: {$options['content_type']}";
	}

	//var_dump($headers);
	$curl = curl_init();

	curl_setopt_array($curl, array(
	  CURLOPT_URL => $url,
	  CURLOPT_RETURNTRANSFER => true,
	  CURLOPT_ENCODING => "",
	  CURLOPT_MAXREDIRS => 10,
	  CURLOPT_TIMEOUT => $timeout,
	  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	  CURLOPT_CUSTOMREQUEST => $type,
	  CURLOPT_SSL_VERIFYHOST => false,
	  CURLOPT_FOLLOWLOCATION => true,
	  CURLOPT_SSL_VERIFYPEER => false,
	  CURLOPT_HTTPHEADER => $headers,
	));

	if($no_body == true){
		curl_setopt($curl, CURLOPT_HEADER, true);
		curl_setopt($curl, CURLOPT_NOBODY, true);		
	}

	if(isset($options['post_body'])){
		curl_setopt($curl, CURLOPT_POSTFIELDS, $options['post_body']);
	}

	//$response = curl_exec($curl);

	$response =  $curl_cf_wrapper->exec($curl);
	$err = curl_error($curl);

	$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);


	if ($err) {
		//echo "Error: ". $err;
		curl_close($curl);
		throw new Exception($err);
	} else {
		
		$effective_url = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);

		curl_close($curl);

	 	return ['response' => $response, 'effective_url' => parse_url($effective_url), 'http_code' => $http_code];
	}		
}

function checkPost($purepng_id, $db){
	$db->where("purepng_id", $purepng_id);
	$post = $db->getOne("posts");

	if($post)
		return true;

	return false;

}


function insertPost($data, $db){

	$db->where ("name", $data['username']);
	$user = $db->getOne("users");
	$user_id = $user['id'];


	if(is_null($user['id'])){
		$user_id = $db->insert('users', ['name' => $data['username']]);
	}

	$db->where ("name", $data['category']);
	$category = $db->getOne("categories");
	$category_id = $category['id'];


	if(is_null($category['id'])){
		$category_id = $db->insert('categories', ['name' => $data['category']]);
	}




	$row = [
		'purepng_id' => $data['purepng_id'],
		'user_id' => $user_id,
		'title' => $data['title'],
		'description' => $data['description'],
		'main_image' => $data['image_name'],
		'image_type' => $data['image_type'],
		'category_id' =>$category_id,
		'image_width' => $data['resolution'][0],
		'image_height' => $data['resolution'][1],
	];

	$post_id = $db->insert('posts', $row);

	if($post_id){
		foreach ($data['tags'] as $tag_name) {
			$db->where ("name", $tag_name);
			$tag = $db->getOne("tags");
			$tag_id = $tag['id'];


			if(is_null($tag['id'])){
				$tag_id = $db->insert('tags', ['name' => $tag_name]);
			}		

			$db->insert('post_tags', ['post_id' => $post_id, 'tag_id' => $tag_id]);	
		}

		foreach ($data['color_palette'] as $color_palette) {
			$db->insert('post_color_palette', ['post_id' => $post_id, 'color' => $color_palette]);	
		}
		
	}

}