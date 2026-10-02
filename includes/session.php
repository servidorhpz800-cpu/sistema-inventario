<?php

function iniciarSesionPersistente(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $duracionSesion = 10 * 365 * 24 * 60 * 60;
    ini_set('session.gc_maxlifetime', (string)$duracionSesion);
    ini_set('session.cookie_lifetime', (string)$duracionSesion);
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => $duracionSesion,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
    $parametrosCookie = session_get_cookie_params();
    setcookie(session_name(), session_id(), [
        'expires' => time() + $duracionSesion,
        'path' => $parametrosCookie['path'],
        'domain' => $parametrosCookie['domain'],
        'secure' => $parametrosCookie['secure'],
        'httponly' => $parametrosCookie['httponly'],
        'samesite' => $parametrosCookie['samesite'] ?? 'Lax',
    ]);
}