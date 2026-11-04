<?php
declare(strict_types=1);
date_default_timezone_set('America/Mexico_City');

function obtenerConexion(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $host = '127.0.0.1';
    $puerto = '3306';
    $bd = 'hotel_costa_azul';
    $user = 'root';
    $pass = '';

    $dsn = "mysql:host={$host};port={$puerto};dbname={$bd};charset=utf8mb4";
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }
}

// Inicializar la variable global $conexion para compatibilidad total
$conexion = obtenerConexion();