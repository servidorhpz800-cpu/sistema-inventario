<?php

// Archivo local privado; no lo subas a GitHub.
// Si falla con 1045, revisa usuario, contraseña y la IP permitida en TiDB Cloud.
// Si falla TLS, revisa DB_SSL_CA y confirma que el archivo PEM exista.
return [
    'DB_HOST' => 'gateway01.us-east-1.prod.aws.tidbcloud.com',
    'DB_PORT' => 4000,
    'DB_NAME' => 'compuser_inventario',
    'DB_USER' => '3FsN9LXsyrLQk2q.root',
    'DB_PASS' => 'sp2uxfn5ZUsBtAlo',
    'DB_SSL_CA' => 'C:\\xampp\\apache\\bin\\curl-ca-bundle.crt',
];
