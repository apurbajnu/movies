<?php
error_reporting(0); 

$folderUrl = isset($_GET['url']) ? trim($_GET['url']) : '';
$defaultImage = 'https://placehold.co/400x600/e0e0e0/333333?text=No+Poster';

if (!$folderUrl) {
    exit($defaultImage);
}

$ch = curl_init($folderUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
$html = curl_exec($ch);
curl_close($ch);

$imageUrl = $defaultImage;

if ($html) {
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $links = $dom->getElementsByTagName('a');
    
    foreach ($links as $link) {
        $href = $link->getAttribute('href');
        $parsedPath = parse_url($href, PHP_URL_PATH);
        $ext = $parsedPath ? strtolower(pathinfo($parsedPath, PATHINFO_EXTENSION)) : '';
        
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $imageFileName = trim(basename(urldecode($href)));
            $safeImageName = str_replace(['%28', '%29'], ['(', ')'], rawurlencode($imageFileName));
            $imageUrl = $folderUrl . $safeImageName;
            break; 
        }
    }
}

echo $imageUrl;
?>