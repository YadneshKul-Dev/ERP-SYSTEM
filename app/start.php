<?php
// ============================================
// PHP Built-in Server Router
// ============================================

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve static files directly
$staticExtensions = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'ico', 'svg', 'woff', 'woff2', 'ttf', 'eot'];
$ext = pathinfo($uri, PATHINFO_EXTENSION);

if ($ext && in_array($ext, $staticExtensions)) {
    $filePath = __DIR__ . $uri;
    if (file_exists($filePath)) {
        return false;
    }
}

// Route to the correct PHP file
$filePath = __DIR__ . $uri;

// If the file exists, serve it
if (file_exists($filePath) && is_file($filePath)) {
    $_SERVER['SCRIPT_NAME'] = $uri;
    require $filePath;
    return true;
}

// Special case: if requesting / or /index.php without auth, show login
if ($uri === '/' || $uri === '/index.php') {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    require __DIR__ . '/index.php';
    return true;
}

// If file doesn't exist, show 404
http_response_code(404);
echo '<!DOCTYPE html><html><head><title>404 - Not Found</title>';
echo '<style>body{font-family:sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;background:#f1f5f9;}';
echo '.error{text-align:center;}h1{font-size:72px;color:#2563eb;margin:0;}p{color:#64748b;}</style></head>';
echo '<body><div class="error"><h1>404</h1><p>Page not found: ' . htmlspecialchars($uri) . '</p>';
echo '<a href="/" style="color:#2563eb;">Go to Home</a></div></body></html>';
