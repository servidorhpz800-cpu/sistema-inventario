<?php
$host   = getenv('DB_HOST');
$port   = getenv('DB_PORT') ?: '4000';
$dbname = getenv('DB_NAME');
$user   = getenv('DB_USER');
$pass   = getenv('DB_PASS');

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE                        => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE             => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT                        => 10,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT   => false,
    ];

    // Incluir certificado de sistema si existe en el contenedor Docker
    if (file_exists('/etc/ssl/certs/ca-certificates.crt')) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = '/etc/ssl/certs/ca-certificates.crt';
    }

    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("<h3>Error técnico de conexión:</h3><p>" . htmlspecialchars($e->getMessage()) . "</p>");
}
?>
