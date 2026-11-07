<?php
declare(strict_types=1);
date_default_timezone_set('America/Mexico_City');

/**
 * Carga opcional de variables desde un archivo .env en la raíz del proyecto.
 * No sobrescribe variables que ya existan en el entorno del servidor.
 * Si no hay .env se usan los valores por defecto de XAMPP (ver abajo).
 */
function cargarEnv(string $ruta): void
{
    if (!is_readable($ruta)) {
        return;
    }
    foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || $linea[0] === '#' || strpos($linea, '=') === false) {
            continue;
        }
        [$clave, $valor] = explode('=', $linea, 2);
        $clave = trim($clave);
        $valor = trim($valor, " \t\"'");
        if (getenv($clave) === false) {
            putenv("{$clave}={$valor}");
        }
    }
}

function obtenerConexion(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    cargarEnv(__DIR__ . '/../.env');

    // Valores por defecto = XAMPP local (root, sin contraseña).
    $host   = getenv('DB_HOST') !== false ? getenv('DB_HOST') : '127.0.0.1';
    $puerto = getenv('DB_PORT') !== false ? getenv('DB_PORT') : '3306';
    $bd     = getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'esquemabd';
    $user   = getenv('DB_USER') !== false ? getenv('DB_USER') : 'root';
    $pass   = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

    $dsn = "mysql:host={$host};port={$puerto};dbname={$bd};charset=utf8mb4";
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        // El detalle técnico va al log del servidor; al usuario, un mensaje genérico.
        error_log('[Hotel Costa Azul] Error de conexión a la BD: ' . $e->getMessage());
        http_response_code(500);
        exit('No fue posible conectar con el sistema. Intenta de nuevo más tarde.');
    }
}

// Variable de conexión compartida por las pantallas que hacen require_once de este archivo.
$conexion = obtenerConexion();