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
use Intervention\Image\ImageManagerStatic as Image;



$db = new MysqliDb ($config['database_host'], $config['database_username'], $config['database_password'], $config['database_db_name']);

error_reporting(1);

$args = $argv;

$id = $args[1];

$to_id = $id;

if(isset($args[2]))
    $to_id = $args[2];

if(!isset($id))
	die('Enter image id');


$images_path = $config['images_folder'];
$thumbnail_path = $config['thumbnail_folder'];

for($i = $id; $i <= $to_id; $i++){
    try{
        echo "scrape post....$i\r\n";
        if(checkPost($i, $db)){
            echo "post already scraped\r\n";
            continue;
        }

        
        $data = scrapePost($i);

        if($data == null){
            echo "no image ~\r\n";
            continue;
        }


        echo "download iamge....\r\n";

        $image_name = slug(time() . ' ' . $data['title']).'.png';//base64_encode($data['title']).$id.'.png';
        $image_name_path = $images_path.'/'. $image_name;
        file_put_contents($image_name_path, simpleCurlRrequest($data['main_image_url'])['response']);


    
        $thumbnail =  Image::make($image_name_path);

        $thumbnail->resize(null, 100, function ($constraint) {
            $constraint->aspectRatio();
        });

        $thumbnail->save($thumbnail_path.'/'.$image_name);


        $data['image_name'] = $image_name;



        echo "save to the datatabse\r\n";
        insertPost($data, $db);

        echo "done\r\n\r\n";  
    }
    catch(Exception $e){
        echo "error: " . $e->getMessage() . "\r\n\r\n";
    }      
}








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
    if($data['main_image_url'] == null)
        return null;
//var_dump($data['main_image_url']);
	foreach ($dom->find('.arrowDownload', 0)->find('a') as $image) {
		$data['download_image_urls'][] = $image->href;
	}

	$block = $dom->find('.col-md-3', 0)->find('ul[class=list-group]', 0);

	if($block){
		//echo $block->innerHtml;

		$data['publish_on'] = $block->find('.list-group-item', 1)->find('span', 0)->innerHtml;
		$data['image_type'] = $block->find('.list-group-item', 2)->find('span', 0)->innerHtml;
		$data['resolution'] = explode('x', $block->find('.list-group-item', 3)->find('span', 0)->innerHtml);
		$data['category'] = strip_tags($block->find('.list-group-item', 4)->find('span', 0)->find('a', 0)->title);
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

	$category_name = $data['category'];
	$db->where("slug", slug($category_name));
	$category = $db->getOne("categories");
	$category_id = $category['id'];


	if(is_null($category['id'])){
		$category_id = $db->insert('categories', ['name' => $category_name, 'slug' => slug($category_name)]);
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
				$tag_id = $db->insert('tags', ['name' => $tag_name, 'slug' => slug($tag_name)]);
			}		

			$db->insert('post_tag', ['post_id' => $post_id, 'tag_id' => $tag_id]);	
		}

		foreach ($data['color_palette'] as $color_palette) {
			$db->insert('post_color_palette', ['post_id' => $post_id, 'color' => $color_palette]);	
		}
		
	}

}



function slug($title, $separator = '-', $language = 'en')
{
    $title = $language ? ascii($title, $language) : $title;

    // Convert all dashes/underscores into separator
    $flip = $separator === '-' ? '_' : '-';

    $title = preg_replace('!['.preg_quote($flip).']+!u', $separator, $title);

    // Replace @ with the word 'at'
    $title = str_replace('@', $separator.'at'.$separator, $title);

    // Remove all characters that are not the separator, letters, numbers, or whitespace.
    $title = preg_replace('![^'.preg_quote($separator).'\pL\pN\s]+!u', '', lower($title));

    // Replace all separator characters and whitespace by a single separator
    $title = preg_replace('!['.preg_quote($separator).'\s]+!u', $separator, $title);

    return trim($title, $separator);
}

function ascii($value, $language = 'en')
{
    $languageSpecific = languageSpecificCharsArray($language);

    if (! is_null($languageSpecific)) {
        $value = str_replace($languageSpecific[0], $languageSpecific[1], $value);
    }

    foreach (charsArray() as $key => $val) {
        $value = str_replace($val, $key, $value);
    }

    return preg_replace('/[^\x20-\x7E]/u', '', $value);
}

function languageSpecificCharsArray($language)
{
    static $languageSpecific;

    if (! isset($languageSpecific)) {
        $languageSpecific = [
            'bg' => [
                ['х', 'Х', 'щ', 'Щ', 'ъ', 'Ъ', 'ь', 'Ь'],
                ['h', 'H', 'sht', 'SHT', 'a', 'А', 'y', 'Y'],
            ],
            'da' => [
                ['æ', 'ø', 'å', 'Æ', 'Ø', 'Å'],
                ['ae', 'oe', 'aa', 'Ae', 'Oe', 'Aa'],
            ],
            'de' => [
                ['ä',  'ö',  'ü',  'Ä',  'Ö',  'Ü'],
                ['ae', 'oe', 'ue', 'AE', 'OE', 'UE'],
            ],
            'ro' => [
                ['ă', 'â', 'î', 'ș', 'ț', 'Ă', 'Â', 'Î', 'Ș', 'Ț'],
                ['a', 'a', 'i', 's', 't', 'A', 'A', 'I', 'S', 'T'],
            ],
        ];
    }

    return $languageSpecific[$language] ?? null;
}

function lower($value)
{
    return mb_strtolower($value, 'UTF-8');
}

function charsArray()
{
    static $charsArray;

    if (isset($charsArray)) {
        return $charsArray;
    }

    return $charsArray = [
        '0'    => ['°', '₀', '۰', '０'],
        '1'    => ['¹', '₁', '۱', '１'],
        '2'    => ['²', '₂', '۲', '２'],
        '3'    => ['³', '₃', '۳', '３'],
        '4'    => ['⁴', '₄', '۴', '٤', '４'],
        '5'    => ['⁵', '₅', '۵', '٥', '５'],
        '6'    => ['⁶', '₆', '۶', '٦', '６'],
        '7'    => ['⁷', '₇', '۷', '７'],
        '8'    => ['⁸', '₈', '۸', '８'],
        '9'    => ['⁹', '₉', '۹', '９'],
        'a'    => ['à', 'á', 'ả', 'ã', 'ạ', 'ă', 'ắ', 'ằ', 'ẳ', 'ẵ', 'ặ', 'â', 'ấ', 'ầ', 'ẩ', 'ẫ', 'ậ', 'ā', 'ą', 'å', 'α', 'ά', 'ἀ', 'ἁ', 'ἂ', 'ἃ', 'ἄ', 'ἅ', 'ἆ', 'ἇ', 'ᾀ', 'ᾁ', 'ᾂ', 'ᾃ', 'ᾄ', 'ᾅ', 'ᾆ', 'ᾇ', 'ὰ', 'ά', 'ᾰ', 'ᾱ', 'ᾲ', 'ᾳ', 'ᾴ', 'ᾶ', 'ᾷ', 'а', 'أ', 'အ', 'ာ', 'ါ', 'ǻ', 'ǎ', 'ª', 'ა', 'अ', 'ا', 'ａ', 'ä'],
        'b'    => ['б', 'β', 'ب', 'ဗ', 'ბ', 'ｂ'],
        'c'    => ['ç', 'ć', 'č', 'ĉ', 'ċ', 'ｃ'],
        'd'    => ['ď', 'ð', 'đ', 'ƌ', 'ȡ', 'ɖ', 'ɗ', 'ᵭ', 'ᶁ', 'ᶑ', 'д', 'δ', 'د', 'ض', 'ဍ', 'ဒ', 'დ', 'ｄ'],
        'e'    => ['é', 'è', 'ẻ', 'ẽ', 'ẹ', 'ê', 'ế', 'ề', 'ể', 'ễ', 'ệ', 'ë', 'ē', 'ę', 'ě', 'ĕ', 'ė', 'ε', 'έ', 'ἐ', 'ἑ', 'ἒ', 'ἓ', 'ἔ', 'ἕ', 'ὲ', 'έ', 'е', 'ё', 'э', 'є', 'ə', 'ဧ', 'ေ', 'ဲ', 'ე', 'ए', 'إ', 'ئ', 'ｅ'],
        'f'    => ['ф', 'φ', 'ف', 'ƒ', 'ფ', 'ｆ'],
        'g'    => ['ĝ', 'ğ', 'ġ', 'ģ', 'г', 'ґ', 'γ', 'ဂ', 'გ', 'گ', 'ｇ'],
        'h'    => ['ĥ', 'ħ', 'η', 'ή', 'ح', 'ه', 'ဟ', 'ှ', 'ჰ', 'ｈ'],
        'i'    => ['í', 'ì', 'ỉ', 'ĩ', 'ị', 'î', 'ï', 'ī', 'ĭ', 'į', 'ı', 'ι', 'ί', 'ϊ', 'ΐ', 'ἰ', 'ἱ', 'ἲ', 'ἳ', 'ἴ', 'ἵ', 'ἶ', 'ἷ', 'ὶ', 'ί', 'ῐ', 'ῑ', 'ῒ', 'ΐ', 'ῖ', 'ῗ', 'і', 'ї', 'и', 'ဣ', 'ိ', 'ီ', 'ည်', 'ǐ', 'ი', 'इ', 'ی', 'ｉ'],
        'j'    => ['ĵ', 'ј', 'Ј', 'ჯ', 'ج', 'ｊ'],
        'k'    => ['ķ', 'ĸ', 'к', 'κ', 'Ķ', 'ق', 'ك', 'က', 'კ', 'ქ', 'ک', 'ｋ'],
        'l'    => ['ł', 'ľ', 'ĺ', 'ļ', 'ŀ', 'л', 'λ', 'ل', 'လ', 'ლ', 'ｌ'],
        'm'    => ['м', 'μ', 'م', 'မ', 'მ', 'ｍ'],
        'n'    => ['ñ', 'ń', 'ň', 'ņ', 'ŉ', 'ŋ', 'ν', 'н', 'ن', 'န', 'ნ', 'ｎ'],
        'o'    => ['ó', 'ò', 'ỏ', 'õ', 'ọ', 'ô', 'ố', 'ồ', 'ổ', 'ỗ', 'ộ', 'ơ', 'ớ', 'ờ', 'ở', 'ỡ', 'ợ', 'ø', 'ō', 'ő', 'ŏ', 'ο', 'ὀ', 'ὁ', 'ὂ', 'ὃ', 'ὄ', 'ὅ', 'ὸ', 'ό', 'о', 'و', 'θ', 'ို', 'ǒ', 'ǿ', 'º', 'ო', 'ओ', 'ｏ', 'ö'],
        'p'    => ['п', 'π', 'ပ', 'პ', 'پ', 'ｐ'],
        'q'    => ['ყ', 'ｑ'],
        'r'    => ['ŕ', 'ř', 'ŗ', 'р', 'ρ', 'ر', 'რ', 'ｒ'],
        's'    => ['ś', 'š', 'ş', 'с', 'σ', 'ș', 'ς', 'س', 'ص', 'စ', 'ſ', 'ს', 'ｓ'],
        't'    => ['ť', 'ţ', 'т', 'τ', 'ț', 'ت', 'ط', 'ဋ', 'တ', 'ŧ', 'თ', 'ტ', 'ｔ'],
        'u'    => ['ú', 'ù', 'ủ', 'ũ', 'ụ', 'ư', 'ứ', 'ừ', 'ử', 'ữ', 'ự', 'û', 'ū', 'ů', 'ű', 'ŭ', 'ų', 'µ', 'у', 'ဉ', 'ု', 'ူ', 'ǔ', 'ǖ', 'ǘ', 'ǚ', 'ǜ', 'უ', 'उ', 'ｕ', 'ў', 'ü'],
        'v'    => ['в', 'ვ', 'ϐ', 'ｖ'],
        'w'    => ['ŵ', 'ω', 'ώ', 'ဝ', 'ွ', 'ｗ'],
        'x'    => ['χ', 'ξ', 'ｘ'],
        'y'    => ['ý', 'ỳ', 'ỷ', 'ỹ', 'ỵ', 'ÿ', 'ŷ', 'й', 'ы', 'υ', 'ϋ', 'ύ', 'ΰ', 'ي', 'ယ', 'ｙ'],
        'z'    => ['ź', 'ž', 'ż', 'з', 'ζ', 'ز', 'ဇ', 'ზ', 'ｚ'],
        'aa'   => ['ع', 'आ', 'آ'],
        'ae'   => ['æ', 'ǽ'],
        'ai'   => ['ऐ'],
        'ch'   => ['ч', 'ჩ', 'ჭ', 'چ'],
        'dj'   => ['ђ', 'đ'],
        'dz'   => ['џ', 'ძ'],
        'ei'   => ['ऍ'],
        'gh'   => ['غ', 'ღ'],
        'ii'   => ['ई'],
        'ij'   => ['ĳ'],
        'kh'   => ['х', 'خ', 'ხ'],
        'lj'   => ['љ'],
        'nj'   => ['њ'],
        'oe'   => ['ö', 'œ', 'ؤ'],
        'oi'   => ['ऑ'],
        'oii'  => ['ऒ'],
        'ps'   => ['ψ'],
        'sh'   => ['ш', 'შ', 'ش'],
        'shch' => ['щ'],
        'ss'   => ['ß'],
        'sx'   => ['ŝ'],
        'th'   => ['þ', 'ϑ', 'ث', 'ذ', 'ظ'],
        'ts'   => ['ц', 'ც', 'წ'],
        'ue'   => ['ü'],
        'uu'   => ['ऊ'],
        'ya'   => ['я'],
        'yu'   => ['ю'],
        'zh'   => ['ж', 'ჟ', 'ژ'],
        '(c)'  => ['©'],
        'A'    => ['Á', 'À', 'Ả', 'Ã', 'Ạ', 'Ă', 'Ắ', 'Ằ', 'Ẳ', 'Ẵ', 'Ặ', 'Â', 'Ấ', 'Ầ', 'Ẩ', 'Ẫ', 'Ậ', 'Å', 'Ā', 'Ą', 'Α', 'Ά', 'Ἀ', 'Ἁ', 'Ἂ', 'Ἃ', 'Ἄ', 'Ἅ', 'Ἆ', 'Ἇ', 'ᾈ', 'ᾉ', 'ᾊ', 'ᾋ', 'ᾌ', 'ᾍ', 'ᾎ', 'ᾏ', 'Ᾰ', 'Ᾱ', 'Ὰ', 'Ά', 'ᾼ', 'А', 'Ǻ', 'Ǎ', 'Ａ', 'Ä'],
        'B'    => ['Б', 'Β', 'ब', 'Ｂ'],
        'C'    => ['Ç', 'Ć', 'Č', 'Ĉ', 'Ċ', 'Ｃ'],
        'D'    => ['Ď', 'Ð', 'Đ', 'Ɖ', 'Ɗ', 'Ƌ', 'ᴅ', 'ᴆ', 'Д', 'Δ', 'Ｄ'],
        'E'    => ['É', 'È', 'Ẻ', 'Ẽ', 'Ẹ', 'Ê', 'Ế', 'Ề', 'Ể', 'Ễ', 'Ệ', 'Ë', 'Ē', 'Ę', 'Ě', 'Ĕ', 'Ė', 'Ε', 'Έ', 'Ἐ', 'Ἑ', 'Ἒ', 'Ἓ', 'Ἔ', 'Ἕ', 'Έ', 'Ὲ', 'Е', 'Ё', 'Э', 'Є', 'Ə', 'Ｅ'],
        'F'    => ['Ф', 'Φ', 'Ｆ'],
        'G'    => ['Ğ', 'Ġ', 'Ģ', 'Г', 'Ґ', 'Γ', 'Ｇ'],
        'H'    => ['Η', 'Ή', 'Ħ', 'Ｈ'],
        'I'    => ['Í', 'Ì', 'Ỉ', 'Ĩ', 'Ị', 'Î', 'Ï', 'Ī', 'Ĭ', 'Į', 'İ', 'Ι', 'Ί', 'Ϊ', 'Ἰ', 'Ἱ', 'Ἳ', 'Ἴ', 'Ἵ', 'Ἶ', 'Ἷ', 'Ῐ', 'Ῑ', 'Ὶ', 'Ί', 'И', 'І', 'Ї', 'Ǐ', 'ϒ', 'Ｉ'],
        'J'    => ['Ｊ'],
        'K'    => ['К', 'Κ', 'Ｋ'],
        'L'    => ['Ĺ', 'Ł', 'Л', 'Λ', 'Ļ', 'Ľ', 'Ŀ', 'ल', 'Ｌ'],
        'M'    => ['М', 'Μ', 'Ｍ'],
        'N'    => ['Ń', 'Ñ', 'Ň', 'Ņ', 'Ŋ', 'Н', 'Ν', 'Ｎ'],
        'O'    => ['Ó', 'Ò', 'Ỏ', 'Õ', 'Ọ', 'Ô', 'Ố', 'Ồ', 'Ổ', 'Ỗ', 'Ộ', 'Ơ', 'Ớ', 'Ờ', 'Ở', 'Ỡ', 'Ợ', 'Ø', 'Ō', 'Ő', 'Ŏ', 'Ο', 'Ό', 'Ὀ', 'Ὁ', 'Ὂ', 'Ὃ', 'Ὄ', 'Ὅ', 'Ὸ', 'Ό', 'О', 'Θ', 'Ө', 'Ǒ', 'Ǿ', 'Ｏ', 'Ö'],
        'P'    => ['П', 'Π', 'Ｐ'],
        'Q'    => ['Ｑ'],
        'R'    => ['Ř', 'Ŕ', 'Р', 'Ρ', 'Ŗ', 'Ｒ'],
        'S'    => ['Ş', 'Ŝ', 'Ș', 'Š', 'Ś', 'С', 'Σ', 'Ｓ'],
        'T'    => ['Ť', 'Ţ', 'Ŧ', 'Ț', 'Т', 'Τ', 'Ｔ'],
        'U'    => ['Ú', 'Ù', 'Ủ', 'Ũ', 'Ụ', 'Ư', 'Ứ', 'Ừ', 'Ử', 'Ữ', 'Ự', 'Û', 'Ū', 'Ů', 'Ű', 'Ŭ', 'Ų', 'У', 'Ǔ', 'Ǖ', 'Ǘ', 'Ǚ', 'Ǜ', 'Ｕ', 'Ў', 'Ü'],
        'V'    => ['В', 'Ｖ'],
        'W'    => ['Ω', 'Ώ', 'Ŵ', 'Ｗ'],
        'X'    => ['Χ', 'Ξ', 'Ｘ'],
        'Y'    => ['Ý', 'Ỳ', 'Ỷ', 'Ỹ', 'Ỵ', 'Ÿ', 'Ῠ', 'Ῡ', 'Ὺ', 'Ύ', 'Ы', 'Й', 'Υ', 'Ϋ', 'Ŷ', 'Ｙ'],
        'Z'    => ['Ź', 'Ž', 'Ż', 'З', 'Ζ', 'Ｚ'],
        'AE'   => ['Æ', 'Ǽ'],
        'Ch'   => ['Ч'],
        'Dj'   => ['Ђ'],
        'Dz'   => ['Џ'],
        'Gx'   => ['Ĝ'],
        'Hx'   => ['Ĥ'],
        'Ij'   => ['Ĳ'],
        'Jx'   => ['Ĵ'],
        'Kh'   => ['Х'],
        'Lj'   => ['Љ'],
        'Nj'   => ['Њ'],
        'Oe'   => ['Œ'],
        'Ps'   => ['Ψ'],
        'Sh'   => ['Ш'],
        'Shch' => ['Щ'],
        'Ss'   => ['ẞ'],
        'Th'   => ['Þ'],
        'Ts'   => ['Ц'],
        'Ya'   => ['Я'],
        'Yu'   => ['Ю'],
        'Zh'   => ['Ж'],
        ' '    => ["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83", "\xE2\x80\x84", "\xE2\x80\x85", "\xE2\x80\x86", "\xE2\x80\x87", "\xE2\x80\x88", "\xE2\x80\x89", "\xE2\x80\x8A", "\xE2\x80\xAF", "\xE2\x81\x9F", "\xE3\x80\x80", "\xEF\xBE\xA0"],
    ];
}