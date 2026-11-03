<?php
/**
 * Config/conexion.php
 * Conexión única a MariaDB con PDO. Todas las pantallas (.php) la incluyen así:
 
 *     require_once __DIR__ . '/../../Config/conexion.php';   // ajustar los ../ según la carpeta
 *     $pdo = obtenerConexion();
 *
 * Valores por defecto = XAMPP en local (usuario root, sin contraseña).
 * En otro equipo o servidor se pueden cambiar con variables de entorno
 * (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS) sin tocar este archivo.
 */
declare(strict_types=1);

date_default_timezone_set('America/Mexico_City');

function obtenerConexion(): PDO
{
    static $pdo = null;               // una sola conexión por petición
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host   = getenv('DB_HOST') ?: '127.0.0.1';
    $puerto = getenv('DB_PORT') ?: '3306';
    $bd     = getenv('DB_NAME') ?: 'hotel_costa_azul';
    $user   = getenv('DB_USER') ?: 'root';
    $pass   = getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '';

    $dsn = "mysql:host={$host};port={$puerto};dbname={$bd};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // los errores lanzan excepción
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // filas como arreglo asociativo
            PDO::ATTR_EMULATE_PREPARES   => false,                    // consultas preparadas reales
        ]);
        // Misma hora en PHP y en MariaDB (México central, UTC-6 sin horario de verano).
        $pdo->exec("SET time_zone = '-06:00'");
    } catch (PDOException $e) {
        // El detalle técnico va al log del servidor; al usuario no se le muestran credenciales.
        error_log('Conexión a la BD fallida: ' . $e->getMessage());
        http_response_code(500);
        exit('No se pudo conectar con la base de datos. Intenta de nuevo más tarde.');
    }

    return $pdo;
}