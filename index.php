<?php
// ==========================================
// BACKGROUND API: FETCH POSTER URL (Runs only for JS)
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'get_image') {
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
    exit; // Stop execution here so it doesn't print HTML
}
// ==========================================

$host = "http://172.16.50.14";
$basePath = "/DHAKA-FLIX-14/Hindi%20Movies/";

function fetchHtml($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    $html = curl_exec($ch);
    curl_close($ch);
    return $html;
}

$yearParam = isset($_GET['year']) ? $_GET['year'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Local Movie Collection</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php
if (!$yearParam) {
    // STATE 1: SHOW ALL YEAR FOLDERS
    echo "<h1 class='title'>Hindi Movies Collection</h1>";
    
    $html = fetchHtml($host . $basePath);
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $links = $dom->getElementsByTagName('a');
    
    echo "<div class='year-grid'>";
    foreach ($links as $link) {
        $href = $link->getAttribute('href');
        $decodedHref = urldecode($href);
        $folderName = trim(basename($decodedHref));
        
        if (strpos($folderName, '(') === 0 && substr($href, -1) === '/') {
            $urlSafeFolder = urlencode($folderName);
            echo "<a href='?year=$urlSafeFolder' class='year-btn'>$folderName</a>";
        }
    }
    echo "</div>";

} else {
    // STATE 2: SHOW MOVIE POSTERS
    echo "<div class='movie-container'>";
    echo "<a href='/' class='back-btn'>&larr; Back to Years</a>";
    echo "<h2 class='title'>Movies from " . htmlspecialchars($yearParam) . "</h2>";
    
    $safeYear = str_replace(['%28', '%29'], ['(', ')'], rawurlencode($yearParam));
    $scrapeUrl = $host . $basePath . $safeYear . "/";
    
    $html = fetchHtml($scrapeUrl);
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $links = $dom->getElementsByTagName('a');
    
    echo "<div class='movie-grid'>";
    foreach ($links as $link) {
        $href = $link->getAttribute('href');
        
        if (substr($href, -1) === '/') {
            $decodedHref = urldecode($href);
            $folderName = trim(basename($decodedHref));
            
            if (
                $folderName === '' || 
                $folderName === $yearParam || 
                $folderName === 'Hindi Movies' || 
                strpos($href, 'h5ai') !== false || 
                strpos($href, '?') === 0
            ) {
                continue;
            }
            
            $safeFolderName = str_replace(['%28', '%29'], ['(', ')'], rawurlencode($folderName));
            $movieFolderUrl = $scrapeUrl . $safeFolderName . "/";
            
            echo "<div class='movie-card'>";
            echo "<img src='https://placehold.co/400x600/e0e0e0/333333?text=Loading...' data-folderurl='$movieFolderUrl' alt=\"" . htmlspecialchars($folderName) . "\" class='lazy-poster movie-poster' onerror=\"this.onerror=null; this.src='https://placehold.co/400x600/e0e0e0/333333?text=No+Poster';\">";
            echo "<p class='movie-title'>" . htmlspecialchars($folderName) . "</p>";
            echo "</div>";
        }
    }
    echo "</div></div>";
}
?>

<script src="assets/js/app.js"></script>
</body>
</html>