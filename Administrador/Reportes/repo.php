<?php
// Solo el Administrador gestiona tarifas, políticas y reportes.
require_once '../../config/conexion.php';
require_once '../../config/sesion.php';
verificarAcceso('Admin');

// Inicializamos variables para los KPIs y reportes
$total_ingresos = 0;
$porcentaje_ocupacion = 0;
$reservas_activas = 0;
$reservas_canceladas = 0;

$ingreso_anticipo = 0;
$ingreso_liquidación = 0;
$ingreso_en_linea = 0;
$ingreso_terminal = 0;

$tipos_habitacion_ocupacion = [];

try {
    // 1. Ingresos totales del periodo (ej. mes actual)
    $stmt_ingresos = $conexion->query("SELECT SUM(monto) AS total FROM pago WHERE MONTH(fecha_hora) = MONTH(CURRENT_DATE()) AND YEAR(fecha_hora) = YEAR(CURRENT_DATE())");
    $res_ingresos = $stmt_ingresos->fetch(PDO::FETCH_ASSOC);
    $total_ingresos = $res_ingresos['total'] ?? 0;

    // 2. Conteo de Reservaciones Activas y Canceladas del mes
    $stmt_res_activas = $conexion->query("SELECT COUNT(*) AS total FROM reservacion WHERE estado = 'Activa' AND MONTH(fecha_registro) = MONTH(CURRENT_DATE()) AND YEAR(fecha_registro) = YEAR(CURRENT_DATE())");
    $reservas_activas = $stmt_res_activas->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $stmt_res_canceladas = $conexion->query("SELECT COUNT(*) AS total FROM reservacion WHERE estado = 'Cancelada' AND MONTH(fecha_registro) = MONTH(CURRENT_DATE()) AND YEAR(fecha_registro) = YEAR(CURRENT_DATE())");
    $reservas_canceladas = $stmt_res_canceladas->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 3. Ocupación general de hoy
    $stmt_total_hab = $conexion->query("SELECT COUNT(*) AS total FROM habitacion");
    $total_hab = $stmt_total_hab->fetch(PDO::FETCH_ASSOC)['total'] ?? 1;

    // La ocupación se calcula desde RESERVACION (no se guarda): reservaciones activas que cubren hoy.
    $stmt_hab_ocupadas = $conexion->query("SELECT COUNT(DISTINCT numero_habitacion) AS total FROM reservacion WHERE estado = 'Activa' AND CURRENT_DATE() >= fecha_entrada AND CURRENT_DATE() < fecha_salida");
    $hab_ocupadas = $stmt_hab_ocupadas->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $porcentaje_ocupacion = $total_hab > 0 ? round(($hab_ocupadas / $total_hab) * 100, 1) : 0;

    // 4. Ingresos por tipo de pago (Anticipo vs Liquidación)
    $stmt_pagos_tipo = $conexion->query("SELECT tipo_pago, SUM(monto) AS total FROM pago WHERE MONTH(fecha_hora) = MONTH(CURRENT_DATE()) AND YEAR(fecha_hora) = YEAR(CURRENT_DATE()) GROUP BY tipo_pago");
    while ($row = $stmt_pagos_tipo->fetch(PDO::FETCH_ASSOC)) {
        if (strtolower($row['tipo_pago']) === 'anticipo') {
            $ingreso_anticipo = $row['total'];
        } elseif (strtolower($row['tipo_pago']) === 'liquidacion' || strtolower($row['tipo_pago']) === 'liquidación') {
            $ingreso_liquidación = $row['total'];
        }
    }

    // 5. Ingresos por canal (En línea vs Terminal)
    $stmt_pagos_canal = $conexion->query("SELECT canal_pago AS canal, SUM(monto) AS total FROM pago WHERE MONTH(fecha_hora) = MONTH(CURRENT_DATE()) AND YEAR(fecha_hora) = YEAR(CURRENT_DATE()) GROUP BY canal_pago");
    while ($row = $stmt_pagos_canal->fetch(PDO::FETCH_ASSOC)) {
        if (stripos($row['canal'], 'pasarela') !== false) {
            $ingreso_en_linea = $row['total'];
        } elseif (stripos($row['canal'], 'terminal') !== false) {
            $ingreso_terminal = $row['total'];
        }
    }

    // 6. Ocupación por tipo de habitación (Sencilla, Doble, etc.)
    $stmt_tipos = $conexion->query("
        SELECT h.tipo_habitacion AS tipo,
               COUNT(DISTINCT r.numero_habitacion) AS ocupadas,
               COUNT(DISTINCT h.numero_habitacion) AS total
        FROM habitacion h
        LEFT JOIN reservacion r ON r.numero_habitacion = h.numero_habitacion
             AND r.estado = 'Activa'
             AND CURRENT_DATE() >= r.fecha_entrada AND CURRENT_DATE() < r.fecha_salida
        GROUP BY h.tipo_habitacion
    ");
    $tipos_habitacion_ocupacion = $stmt_tipos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('[Hotel Costa Azul] repo.php: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Costa Azul - Reportes</title>
    <link rel="stylesheet" href="repo.css">
    <!-- Chart.js para renderizar la gráfica de barras de ingresos -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

    <!-- Encabezado / Navbar -->
    <header class="navbar">
        <div class="logo">Hotel Costa Azul</div>
        <nav class="nav-links">
            <a href="repo.php" class="active">Reportes</a>
            <a href="tari.php">Tarifas</a>
            <a href="poli.php">Políticas</a>
        </nav>
        <div class="user-profile">
            <span class="user-name"><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></span>
            <span class="user-role"><?php echo ucfirst(htmlspecialchars($_SESSION['rol'] ?? 'Invitado')); ?></span>
        </div>
    </header>

    <!-- Contenido Principal -->
    <div class="main-layout">
        
        <!-- Panel Izquierdo: Gráficas y Métricas -->
        <main class="content-panel">
            <h1 class="page-title">Reportes</h1>
            <p class="page-subtitle">Ingresos y ocupación calculados con las reservaciones y pagos registrados (Mes: <?php echo date('F Y'); ?>)</p>

            <!-- Filtros de Período -->
            <div class="period-filters">
                <button class="filter-btn active">Este mes</button>
                <button class="filter-btn">Mes anterior</button>
                <button class="filter-btn">Rango de fechas</button>
            </div>

            <!-- Tarjetas de Métricas (KPIs dinámicos) -->
            <div class="kpi-grid">
                <div class="kpi-card card-green">
                    <span class="kpi-value">$<?php echo number_format($total_ingresos, 2); ?></span>
                    <span class="kpi-label">Ingresos del periodo</span>
                </div>
                <div class="kpi-card card-blue">
                    <span class="kpi-value"><?php echo $porcentaje_ocupacion; ?>%</span>
                    <span class="kpi-label">Ocupación hoy</span>
                </div>
                <div class="kpi-card card-yellow">
                    <span class="kpi-value"><?php echo $reservas_activas; ?></span>
                    <span class="kpi-label">Reservaciones activas</span>
                </div>
                <div class="kpi-card card-red">
                    <span class="kpi-value"><?php echo $reservas_canceladas; ?></span>
                    <span class="kpi-label">Canceladas</span>
                </div>
            </div>

            <!-- Gráfica: Ingresos por semana -->
            <section class="section-card">
                <h3>Ingresos por semana</h3>
                <div class="chart-container">
                    <canvas id="weeklyRevenueChart"></canvas>
                </div>
            </section>

            <!-- Barras de Ocupación por tipo de habitación (Dinámico) -->
            <section class="section-card">
                <h3>Ocupación por tipo de habitación (hoy)</h3>
                <div class="occupancy-list">
                    <?php if (count($tipos_habitacion_ocupacion) > 0): ?>
                        <?php foreach ($tipos_habitacion_ocupacion as $tipo_hab): ?>
                            <?php 
                                $ocupadas = $tipo_hab['ocupadas'];
                                $total_t = $tipo_hab['total'];
                                $porcentaje = ($total_t > 0) ? round(($ocupadas / $total_t) * 100, 1) : 0;
                            ?>
                            <div class="occupancy-item">
                                <span class="room-type"><?php echo htmlspecialchars($tipo_hab['tipo']); ?></span>
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill" style="width: <?php echo $porcentaje; ?>%;"></div>
                                </div>
                                <span class="room-count"><?php echo $ocupadas; ?> de <?php echo $total_t; ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #666;">No hay datos de habitaciones registrados.</p>
                    <?php endif; ?>
                </div>
            </section>
        </main>

        <!-- Panel Derecho: Resumen Side Card -->
        <aside class="summary-panel">
            <h2>Resumen</h2>
            <p class="summary-period">Periodo: este mes (<?php echo date('M Y'); ?>)</p>

            <!-- Ingresos por Tipo de Pago -->
            <div class="summary-group">
                <h4>Ingresos por tipo de pago</h4>
                <div class="summary-box">
                    <span>Anticipo</span>
                    <strong>$<?php echo number_format($ingreso_anticipo, 2); ?></strong>
                </div>
                <div class="summary-box">
                    <span>Liquidación</span>
                    <strong>$<?php echo number_format($ingreso_liquidación, 2); ?></strong>
                </div>
            </div>

            <!-- Ingresos por Canal -->
            <div class="summary-group">
                <h4>Ingresos por canal</h4>
                <div class="summary-box">
                    <span>Pasarela en línea</span>
                    <strong>$<?php echo number_format($ingreso_en_linea, 2); ?></strong>
                </div>
                <div class="summary-box">
                    <span>Terminal recepción</span>
                    <strong>$<?php echo number_format($ingreso_terminal, 2); ?></strong>
                </div>
            </div>

            <p class="disclaimer-text">
                Los reportes se calculan al consultarlos; no se guardan en la base de datos.
            </p>
        </aside>

    </div>

    <script src="repo.js"></script>
</body>
</html>