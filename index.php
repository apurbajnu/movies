 <?php
// ==========================================
// 1. LOAD CONFIGURATIONS & SAVED MOVIES
// ==========================================
$configFile = __DIR__ . '/config.json';
$savedFile = __DIR__ . '/saved_movies.json';

if (!file_exists($configFile)) {
    die("<h2 style='text-align:center;'>Error: <code>config.json</code> not found.</h2>");
}

$collections = json_decode(file_get_contents($configFile), true);
$savedMovies = file_exists($savedFile) ? json_decode(file_get_contents($savedFile), true) : [];

if (json_last_error() !== JSON_ERROR_NONE || !is_array($collections)) {
    die("<h2 style='text-align:center;'>Error: <code>config.json</code> is invalid.</h2>");
}

// ==========================================
// BACKGROUND API 1: FETCH POSTER URL
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
// BACKGROUND API 2: FETCH & CACHE OMDB DATA
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'get_omdb') {
    error_reporting(0);
    header('Content-Type: application/json');
    
    $title = isset($_GET['t']) ? trim($_GET['t']) : '';
    $year = isset($_GET['y']) ? trim($_GET['y']) : '';
    $apiKey = isset($_GET['apikey']) ? trim($_GET['apikey']) : '';
    
    if (!$title || !$apiKey) {
        echo json_encode(["Response" => "False", "Error" => "Missing parameters"]);
        exit;
    }

    $cacheFile = __DIR__ . '/omdb_cache.json';
    $cacheData = file_exists($cacheFile) ? json_decode(file_get_contents($cacheFile), true) : [];
    $cacheKey = strtolower(preg_replace('/[^a-z0-9]/i', '', $title . $year));

    if (isset($cacheData[$cacheKey])) {
        echo json_encode($cacheData[$cacheKey]);
        exit;
    }

    $omdbUrl = "https://www.omdbapi.com/?t=" . urlencode($title) . "&apikey=" . urlencode($apiKey) . "&plot=full" . ($year ? "&y=" . urlencode($year) : "");
    $ch = curl_init($omdbUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response) {
        $data = json_decode($response, true);
        if ($data && isset($data['Response']) && $data['Response'] === "True") {
            $cacheData[$cacheKey] = $data;
            file_put_contents($cacheFile, json_encode($cacheData, JSON_PRETTY_PRINT));
        }
        echo $response;
    } else {
        echo json_encode(["Response" => "False", "Error" => "Network error"]);
    }
    exit;
}

// ==========================================
// BACKGROUND API 3: TOGGLE SAVE MOVIE
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'toggle_save') {
    error_reporting(0);
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['url'])) exit(json_encode(['status' => 'error']));

    $id = md5($data['url']); // Unique ID based on the movie's folder URL
    
    if (isset($savedMovies[$id])) {
        unset($savedMovies[$id]);
        $status = 'removed';
    } else {
        $savedMovies[$id] = $data;
        $status = 'added';
    }
    
    file_put_contents($savedFile, json_encode($savedMovies, JSON_PRETTY_PRINT));
    echo json_encode(['status' => $status]);
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

// SETUP VIEW VARIABLES
$colId = isset($_GET['col']) ? $_GET['col'] : null;
$yearParam = isset($_GET['year']) ? $_GET['year'] : null;
$isSavedView = ($colId === 'saved');

if ($isSavedView) {
    $currentCol = ['collectionTitle' => 'My Saved Movies', 'hasYears' => false, 'movieApi' => true, 'omdbApiKey' => $collections[0]['omdbApiKey'] ?? ''];
} else {
    $numericColId = is_numeric($colId) ? (int)$colId : null;
    $currentCol = ($numericColId !== null && isset($collections[$numericColId])) ? $collections[$numericColId] : null;
}

$hasYears = true; 
$movieApi = false;
$omdbApiKey = '';

if ($currentCol) {
    $hasYears = isset($currentCol['hasYears']) ? filter_var($currentCol['hasYears'], FILTER_VALIDATE_BOOLEAN) : true;
    $movieApi = isset($currentCol['movieApi']) ? filter_var($currentCol['movieApi'], FILTER_VALIDATE_BOOLEAN) : false;
    $omdbApiKey = $currentCol['omdbApiKey'] ?? '';
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
<body data-movieapi="<?= $movieApi ? 'true' : 'false' ?>" data-omdbkey="<?= htmlspecialchars($omdbApiKey) ?>">

<?php
if ($colId === null || !$currentCol) {
    // ==========================================
    // STEP 1: SHOW ALL MASTER COLLECTIONS + SAVED BUTTON
    // ==========================================
    echo "<div class='movie-container'><h1 class='title'>Select a Collection</h1><div class='year-grid'>";
    
    // Add the "My Saved Movies" button at the very top
    echo "<a href='?col=saved' class='year-btn' style='background: #e67e22; color: #fff; border: 2px solid #d35400;'>★ My Saved Movies</a>";
    
    foreach ($collections as $index => $collection) {
        echo "<a href='?col=$index' class='year-btn'>" . htmlspecialchars($collection['collectionTitle']) . "</a>";
    }
    echo "</div></div>";

} elseif ($hasYears && !$yearParam && !$isSavedView) {
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
            $parentDirName = trim(basename(rtrim(urldecode($basePath), '/')));
            if ($folderName === '' || $folderName === 'Parent Directory' || strpos($href, 'h5ai') !== false || strpos($href, '?') === 0 || $folderName === $parentDirName) continue;
            
            echo "<a href='?col=$colId&year=" . urlencode($folderName) . "' class='year-btn'>$folderName</a>";
        }
    }
    echo "</div></div>";

} else {
    // ==========================================
    // STEP 3: SHOW MOVIE POSTERS (Standard OR Saved View)
    // ==========================================
    echo "<div class='movie-container'>";
    
    if ($isSavedView) {
        echo "<a href='/' class='back-btn'>&larr; Back to Collections</a>";
        echo "<h2 class='title'>My Saved Movies</h2>";
        echo "<div class='movie-grid'>";
        
        if (empty($savedMovies)) {
            echo "<h3 style='color: #666; width: 100%; text-align: center; margin-top: 50px;'>You haven't saved any movies yet.</h3>";
        } 
        
        // Loop through the local JSON file instead of scraping
        foreach (array_reverse($savedMovies) as $id => $movie) {
            echo "<div class='movie-card' data-title='" . htmlspecialchars($movie['title']) . "' data-year='" . htmlspecialchars($movie['year']) . "'>";
            echo "<div class='save-btn saved' data-url='" . htmlspecialchars($movie['url']) . "' data-title='" . htmlspecialchars($movie['title']) . "' data-year='" . htmlspecialchars($movie['year']) . "'>★</div>";
            echo "<a href='" . htmlspecialchars($movie['url']) . "' target='_blank' style='text-decoration: none; color: inherit; display: block;'>";
            echo "<img src='" . htmlspecialchars($movie['poster']) . "' alt=\"" . htmlspecialchars($movie['title']) . "\" class='movie-poster' onerror=\"this.onerror=null; this.src='https://placehold.co/400x600/e0e0e0/333333?text=No+Poster';\">";
            echo "<p class='movie-title'>" . htmlspecialchars($movie['title']) . "</p>";
            echo "</a></div>";
        }
        echo "</div></div>";

    } else {
        // Normal web scraping view
        $host = rtrim($currentCol['host'], '/');
        $basePath = $currentCol['basePath'];
        
        if ($hasYears) {
            echo "<a href='?col=$colId' class='back-btn'>&larr; Back to Years</a>";
            echo "<h2 class='title'>Movies from " . htmlspecialchars($yearParam) . "</h2>";
            $scrapeUrl = $host . $basePath . str_replace(['%28', '%29'], ['(', ')'], rawurlencode($yearParam)) . "/";
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
            $isFolder = (substr($href, -1) === '/');
            $parsedPath = parse_url($href, PHP_URL_PATH);
$ext = $parsedPath ? strtolower(pathinfo($parsedPath, PATHINFO_EXTENSION)) : '';
            $isVideo = in_array($ext, ['mp4', 'mkv', 'avi', 'mov', 'webm']);
            
            if ($isFolder || $isVideo) {
                $itemName = trim(basename(urldecode($href)));
                $parentDirName = trim(basename(rtrim(urldecode($basePath), '/')));
                
                if ($itemName === '' || $itemName === 'Parent Directory' || strpos($href, 'h5ai') !== false || strpos($href, '?') === 0 || ($hasYears && $itemName === $yearParam) || (!$hasYears && $itemName === $parentDirName)) continue;
                
                $itemUrl = $scrapeUrl . str_replace(['%28', '%29'], ['(', ')'], rawurlencode($itemName)) . ($isFolder ? "/" : "");
                
                $cleanTitle = $itemName;
                $cleanYear = '';
                if (preg_match('/^(.*?)\s*\((\d{4})\)/', $itemName, $matches)) {
                    $cleanTitle = trim($matches[1]);
                    $cleanYear = $matches[2];
                }

                // Check if this specific movie is in our saved JSON file
                $isSavedClass = isset($savedMovies[md5($itemUrl)]) ? 'saved' : '';

                echo "<div class='movie-card' data-title='" . htmlspecialchars($cleanTitle) . "' data-year='" . htmlspecialchars($cleanYear) . "'>";
                // Inject the Save Star Button
                echo "<div class='save-btn $isSavedClass' data-url='" . htmlspecialchars($itemUrl) . "' data-title='" . htmlspecialchars($cleanTitle) . "' data-year='" . htmlspecialchars($cleanYear) . "'>★</div>";
                
                echo "<a href='" . htmlspecialchars($itemUrl) . "' target='_blank' style='text-decoration: none; color: inherit; display: block;'>";
                
                if ($isFolder) {
                    $savedPoster = isset($savedMovies[md5($itemUrl)]) ? $savedMovies[md5($itemUrl)]['poster'] : '';
                    if ($savedPoster) {
                        echo "<img src='$savedPoster' class='movie-poster'>"; // Skip lazy load if we already know the poster
                    } else {
                        echo "<img src='https://placehold.co/400x600/e0e0e0/333333?text=Loading...' data-folderurl='$itemUrl' alt=\"" . htmlspecialchars($itemName) . "\" class='lazy-poster movie-poster' onerror=\"this.onerror=null; this.src='https://placehold.co/400x600/e0e0e0/333333?text=No+Poster';\">";
                    }
                } else {
                    echo "<img src='https://placehold.co/400x600/2c3e50/ffffff?text=Video+File' alt=\"" . htmlspecialchars($itemName) . "\" class='movie-poster'>";
                }
                
                echo "<p class='movie-title'>" . htmlspecialchars($itemName) . "</p>";
                echo "</a></div>";
            }
        }
        echo "</div></div>";
    }
}
?>

<script src="assets/js/app.js"></script>
</body>
</html>