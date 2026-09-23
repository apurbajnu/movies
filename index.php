<?php
// ==========================================
// 1. LOAD CONFIGURATION ARRAY
// ==========================================
$configFile = __DIR__ . '/config.json';

if (!file_exists($configFile)) {
    die("<h2 style='text-align:center;'>Error: <code>config.json</code> not found.</h2>");
}

$collections = json_decode(file_get_contents($configFile), true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($collections)) {
    die("<h2 style='text-align:center;'>Error: <code>config.json</code> is invalid.</h2>");
}

// ==========================================
// BACKGROUND API: FETCH POSTER URL (For JS)
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'get_image') {
    error_reporting(0); 
    $folderUrl = isset($_GET['url']) ? trim($_GET['url']) : '';
    $defaultImage = 'https://placehold.co/400x600/e0e0e0/333333?text=No+Poster';

    if (!$folderUrl) exit($defaultImage);

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
    exit; 
}
// ==========================================

function fetchHtml($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    $html = curl_exec($ch);
    curl_close($ch);
    return $html;
}

// Get the requested collection ID and year from the URL
$colId = isset($_GET['col']) && is_numeric($_GET['col']) ? (int)$_GET['col'] : null;
$yearParam = isset($_GET['year']) ? $_GET['year'] : null;

// Validate if the selected collection exists in our JSON
$currentCol = ($colId !== null && isset($collections[$colId])) ? $collections[$colId] : null;

// Determine if this collection uses year folders safely (handles "false" strings, true, 1, 0, etc.)
$hasYears = true; // Default
if ($currentCol && isset($currentCol['hasYears'])) {
    $hasYears = filter_var($currentCol['hasYears'], FILTER_VALIDATE_BOOLEAN);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Movie Collections</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php
if ($colId === null || !$currentCol) {
    // ==========================================
    // STEP 1: SHOW ALL MASTER COLLECTIONS
    // ==========================================
    echo "<div class='movie-container'>";
    echo "<h1 class='title'>Select a Collection</h1>";
    echo "<div class='year-grid'>";
    
    foreach ($collections as $index => $collection) {
        echo "<a href='?col=$index' class='year-btn'>" . htmlspecialchars($collection['collectionTitle']) . "</a>";
    }
    
    echo "</div></div>";

} elseif ($hasYears && !$yearParam) {
    // ==========================================
    // STEP 2: SHOW YEARS (Only if hasYears is true)
    // ==========================================
    $host = rtrim($currentCol['host'], '/');
    $basePath = $currentCol['basePath'];
    
    echo "<div class='movie-container'>";
    echo "<a href='/' class='back-btn'>&larr; Back to Collections</a>";
    echo "<h2 class='title'>" . htmlspecialchars($currentCol['collectionTitle']) . "</h2>";
    
    $scrapeUrl = $host . $basePath;
    $html = fetchHtml($scrapeUrl);
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $links = $dom->getElementsByTagName('a');
    
    echo "<div class='year-grid'>";
    foreach ($links as $link) {
        $href = $link->getAttribute('href');
        if (substr($href, -1) === '/') {
            $folderName = trim(basename(urldecode($href)));
            
            // Clean up the base path to strictly identify the parent directory name
            $parentDirName = trim(basename(rtrim(urldecode($basePath), '/')));
            
            if ($folderName === '' || $folderName === 'Parent Directory' || strpos($href, 'h5ai') !== false || strpos($href, '?') === 0 || $folderName === $parentDirName) {
                continue;
            }
            
            $urlSafeYear = urlencode($folderName);
            echo "<a href='?col=$colId&year=$urlSafeYear' class='year-btn'>$folderName</a>";
        }
    }
    echo "</div></div>";

} else {
    // ==========================================
    // STEP 3: SHOW MOVIE POSTERS (Direct or via Year)
    // ==========================================
    $host = rtrim($currentCol['host'], '/');
    $basePath = $currentCol['basePath'];
    
    echo "<div class='movie-container'>";
    
    if ($hasYears) {
        echo "<a href='?col=$colId' class='back-btn'>&larr; Back to Years</a>";
        echo "<h2 class='title'>Movies from " . htmlspecialchars($yearParam) . "</h2>";
        $safeYear = str_replace(['%28', '%29'], ['(', ')'], rawurlencode($yearParam));
        $scrapeUrl = $host . $basePath . $safeYear . "/";
    } else {
        echo "<a href='/' class='back-btn'>&larr; Back to Collections</a>";
        echo "<h2 class='title'>" . htmlspecialchars($currentCol['collectionTitle']) . "</h2>";
        $scrapeUrl = $host . $basePath;
    }
    
    $html = fetchHtml($scrapeUrl);
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $links = $dom->getElementsByTagName('a');
    
    echo "<div class='movie-grid'>";
    foreach ($links as $link) {
        $href = $link->getAttribute('href');
        
        if (substr($href, -1) === '/') {
            $folderName = trim(basename(urldecode($href)));
            $parentDirName = trim(basename(rtrim(urldecode($basePath), '/')));
            
            // Skip system folders, the parent folder name, or the year folder name
            if (
                $folderName === '' || 
                $folderName === 'Parent Directory' || 
                strpos($href, 'h5ai') !== false || 
                strpos($href, '?') === 0 || 
                ($hasYears && $folderName === $yearParam) ||
                (!$hasYears && $folderName === $parentDirName)
            ) {
                continue;
            }
            
            $safeFolderName = str_replace(['%28', '%29'], ['(', ')'], rawurlencode($folderName));
            $movieFolderUrl = $scrapeUrl . $safeFolderName . "/";
            
            echo "<div class='movie-card'>";
            echo "<a href='" . htmlspecialchars($movieFolderUrl) . "' target='_blank' style='text-decoration: none; color: inherit; display: block;'>";
            echo "<img src='https://placehold.co/400x600/e0e0e0/333333?text=Loading...' data-folderurl='$movieFolderUrl' alt=\"" . htmlspecialchars($folderName) . "\" class='lazy-poster movie-poster' onerror=\"this.onerror=null; this.src='https://placehold.co/400x600/e0e0e0/333333?text=No+Poster';\">";
            echo "<p class='movie-title'>" . htmlspecialchars($folderName) . "</p>";
            echo "</a>";
            echo "</div>";
        }
    }
    echo "</div></div>";
}
?>

<script src="assets/js/app.js"></script>
</body>
</html>