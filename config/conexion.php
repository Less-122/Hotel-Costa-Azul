<?php
declare(strict_types=1);
date_default_timezone_set('America/Mexico_City');

$GLOBALS['__inicio_peticion'] = microtime(true);
const SLA_TIEMPO_RESPUESTA_SEG = 3.0;

/**
 * Evalúa si una operación o petición excedió el umbral de 3 segundos (3000 ms).
 */
function evaluarUmbral3s(float $inicioSeg, string $etiqueta = '', bool $enviarCabeceras = false): array {
    $duracionSeg = max(0.0, microtime(true) - $inicioSeg);
    $duracionMs = round($duracionSeg * 1000, 2);
    $excede = $duracionSeg > SLA_TIEMPO_RESPUESTA_SEG;
    $estado = $excede ? 'EXCEDIDO' : 'OK';

    if ($enviarCabeceras && !headers_sent()) {
        header("X-Response-Time: {$duracionMs}ms");
        header("X-SLA-3s-Status: {$estado}");
    }

    if ($excede) {
        $contexto = $etiqueta !== ''
            ? $etiqueta
            : (($_SERVER['REQUEST_METHOD'] ?? 'GET') . ' ' . ($_SERVER['REQUEST_URI'] ?? 'cli'));

        error_log(sprintf(
            "[Hotel Costa Azul | SLA >3s] %.3fs (%sms) | %s",
            $duracionSeg,
            $duracionMs,
            $contexto
        ));
    }

    return [
        'duracion_seg' => $duracionSeg,
        'duracion_ms'  => $duracionMs,
        'excede_3s'    => $excede,
        'estado_sla'   => $estado,
    ];
}

// Registra automáticamente el tiempo total de respuesta al terminar el script
register_shutdown_function(function (): void {
    $inicio = (float) ($GLOBALS['__inicio_peticion'] ?? microtime(true));
    evaluarUmbral3s($inicio, '', true);
});

function obtenerConexion(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // Valores por defecto pensados solo para desarrollo local; en producción definir DB_* en el entorno
    $host   = (string) ($_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1');
    $puerto = (string) ($_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? getenv('DB_PORT') ?: '3306');
    $bd     = (string) ($_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? getenv('DB_NAME') ?: 'hotel_costa_azul');
    $user   = (string) ($_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? getenv('DB_USER') ?: 'root');
    $pass   = (string) ($_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? getenv('DB_PASS') ?? '');

    $dsn = "mysql:host={$host};port={$puerto};dbname={$bd};charset=utf8mb4";
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 3, // Límite de 3s en la conexión
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log('[Hotel Costa Azul | Error de conexión BD] ' . $e->getMessage());
        http_response_code(503);
        die("No se pudo establecer conexión con el servicio de base de datos. Intenta de nuevo más tarde.");
    }
}

// Inicializar la variable global $conexion para compatibilidad total
$conexion = obtenerConexion();