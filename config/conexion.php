<?php
/**
 * Archivo de Conexión a la Base de Datos - Hotel Costa Azul
 * Utiliza PDO para garantizar seguridad contra inyecciones SQL.
 */

$host = 'localhost';
$dbname = 'hotel_costa_azul';
$username = 'root';
$password = ''; // Ajusta según tu entorno local

try {
    $conexion = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Crear la base de datos si no existe
    $conexion->exec("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $conexion->exec("USE `$dbname`;");

} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
 ?>