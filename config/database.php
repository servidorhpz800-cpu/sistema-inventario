<?php
$localConfigPath = __DIR__ . '/database.local.php';
$localConfig = is_file($localConfigPath) ? require $localConfigPath : [];
if (!is_array($localConfig)) {
    throw new RuntimeException('config/database.local.php debe devolver un arreglo de configuración.');
}

$getSetting = static function (string $key, $default = '') use ($localConfig) {
    $environmentValue = getenv($key);
    if ($environmentValue !== false && $environmentValue !== '') {
        return $environmentValue;
    }

    return $localConfig[$key] ?? $default;
};

$host = (string)$getSetting('DB_HOST');
$port = (int)$getSetting('DB_PORT', 4000);
$user = (string)$getSetting('DB_USER');
$password = (string)$getSetting('DB_PASS');
$dbname = (string)$getSetting('DB_NAME');

if ($host === '' || $user === '' || $password === '' || $dbname === '') {
    throw new RuntimeException('Configura TiDB en config/database.local.php usando la plantilla database.local.example.php.');
}

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbname);
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

$sslCa = $getSetting('DB_SSL_CA', false);
if ($sslCa === false || $sslCa === '') {
    $sslCa = ini_get('openssl.cafile') ?: ini_get('curl.cainfo');
}

if ($sslCa === false || $sslCa === '' || !is_file($sslCa)) {
    throw new RuntimeException('Configura DB_SSL_CA con el certificado CA PEM de TiDB Cloud.');
}
if (!defined('PDO::MYSQL_ATTR_SSL_CA')) {
    throw new RuntimeException('La extensión PDO MySQL debe estar habilitada para conectar a TiDB Cloud.');
}

$options[constant('PDO::MYSQL_ATTR_SSL_CA')] = $sslCa;
if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
    $options[constant('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')] = true;
}

try {
    $pdo = new PDO($dsn, $user, $password, $options);
} catch (Throwable $exception) {
    error_log('Error al conectar a TiDB Cloud: ' . $exception->getMessage());
    http_response_code(500);
    exit('No se pudo conectar a la base de datos. Verifica el endpoint, usuario, contraseña y TLS.');
}
