<?php
/**
 * Centralized Database Connection
 * Loads credentials from .env file
 */

// Load environment variables from .env file
function loadEnv($path) {
    if (!file_exists($path)) {
        throw new Exception(".env file not found at: $path");
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }
            
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// Try to find .env file in multiple locations
$envPath = null;
$possiblePaths = [
    __DIR__ . '/../.env',
    dirname(__DIR__) . '/.env',
    __DIR__ . '/.env'
];

foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        $envPath = $path;
        break;
    }
}

if (!$envPath) {
    die("خطأ: ملف .env غير موجود. يرجى إنشاء ملف .env في المجلد الرئيسي للمشروع.");
}

// Load environment variables
loadEnv($envPath);

// Get database credentials from environment variables
$host = $_ENV['DB_HOST'] ?? getenv('DB_HOST');
$dbname = $_ENV['DB_NAME'] ?? getenv('DB_NAME');
$username = $_ENV['DB_USER'] ?? getenv('DB_USER');
$password = $_ENV['DB_PASS'] ?? getenv('DB_PASS');
$charset = $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?? 'utf8mb4';

// Validate that all required credentials are present
if (empty($host) || empty($dbname) || empty($username) || empty($password)) {
    die("خطأ: بيانات الاتصال بقاعدة البيانات غير مكتملة في ملف .env");
}

// Create PDO connection
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=$charset",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES $charset COLLATE {$charset}_unicode_ci"
        ]
    );
} catch (PDOException $e) {
    die("خطأ في الاتصال بقاعدة البيانات");
}