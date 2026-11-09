<?php
session_start();

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /Hotel-Costa-Azul/personal_login/login_personal.php");
    exit;
}

require_once '../../config/conexion.php';

$meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$mes_actual = $meses[date('n') - 1] . ' ' . date('Y');

$inicio_mes = "DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')";
$fin_mes    = "DATE_ADD($inicio_mes, INTERVAL 1 MONTH)";

// 1. Pagos del mes: total, por tipo y por canal en una sola consulta
$pagos = $conexion->query("
    SELECT SUM(monto) AS total,
           SUM(CASE WHEN tipo_pago = 'Anticipo'    THEN monto ELSE 0 END) AS anticipo,
           SUM(CASE WHEN tipo_pago = 'Liquidación' THEN monto ELSE 0 END) AS liquidacion,
           SUM(CASE WHEN canal_pago LIKE '%línea%' THEN monto ELSE 0 END) AS en_linea
    FROM pago
    WHERE fecha_hora >= $inicio_mes AND fecha_hora < $fin_mes
")->fetch(PDO::FETCH_ASSOC);

$total_ingresos       = (float) $pagos['total'];
$ingreso_anticipo     = (float) $pagos['anticipo'];
$ingreso_liquidacion  = (float) $pagos['liquidacion'];
$ingreso_en_linea     = (float) $pagos['en_linea'];
$ingreso_terminal     = $total_ingresos - $ingreso_en_linea;

// 2. Reservaciones del mes por estado
$reservas = $conexion->query("
    SELECT estado, COUNT(*)
    FROM reservacion
    WHERE estado IN ('Activa', 'Cancelada')
      AND fecha_registro >= $inicio_mes AND fecha_registro < $fin_mes
    GROUP BY estado
")->fetchAll(PDO::FETCH_KEY_PAIR);

$reservas_activas    = $reservas['Activa'] ?? 0;
$reservas_canceladas = $reservas['Cancelada'] ?? 0;

// 3. Ocupación por tipo de habitación (de aquí sale también el total general)
$tipos_habitacion_ocupacion = $conexion->query("
    SELECT tipo_habitacion AS tipo,
           SUM(disponible = 0) AS ocupadas,
           COUNT(*) AS total
    FROM habitacion
    GROUP BY tipo_habitacion
")->fetchAll(PDO::FETCH_ASSOC);

$total_hab   = array_sum(array_column($tipos_habitacion_ocupacion, 'total'));
$hab_ocupadas = array_sum(array_column($tipos_habitacion_ocupacion, 'ocupadas'));

$pct = fn($parte, $todo) => $todo > 0 ? round($parte / $todo * 100, 1) : 0;
$porcentaje_ocupacion = $pct($hab_ocupadas, $total_hab);
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
            <a href="../Tarifas/tari.php">Tarifas</a>
            <a href="../Politicas/poli.php">Políticas</a>
            <a href="../Usuarios/usuarios.php">Usuarios</a>
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
            <p class="page-subtitle">Ingresos y ocupación calculados con las reservaciones y pagos registrados (<?php echo $mes_actual; ?>)</p>

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
                        <?php foreach ($tipos_habitacion_ocupacion as $t): ?>
                            <div class="occupancy-item">
                                <span class="room-type"><?php echo htmlspecialchars($t['tipo']); ?></span>
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill" style="width: <?php echo $pct($t['ocupadas'], $t['total']); ?>%;"></div>
                                </div>
                                <span class="room-count"><?php echo $t['ocupadas']; ?> de <?php echo $t['total']; ?></span>
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
            <p class="summary-period">Periodo: este mes (<?php echo $mes_actual; ?>)</p>

            <!-- Ingresos por Tipo de Pago -->
            <div class="summary-group">
                <h4>Ingresos por tipo de pago</h4>
                <div class="summary-box">
                    <span>Anticipo</span>
                    <strong>$<?php echo number_format($ingreso_anticipo, 2); ?></strong>
                </div>
                <div class="summary-box">
                    <span>Liquidación</span>
                    <strong>$<?php echo number_format($ingreso_liquidacion, 2); ?></strong>
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