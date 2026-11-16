<?php
require_once '../../config/sesion.php';
verificarAcceso('admin');

require_once '../../config/conexion.php';

$meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$abrev = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];

function fecha(string $f): string {
    global $abrev;
    $t = strtotime($f);
    return date('d', $t) . ' ' . $abrev[date('n', $t) - 1] . ' ' . date('Y', $t);
}

// --- Periodo: mes (por defecto), anterior o rango ---
$periodo       = $_GET['periodo'] ?? 'mes';
$mensaje_error = '';
$primer_dia    = new DateTime('first day of this month 00:00');

if ($periodo === 'anterior') {
    $desde = (clone $primer_dia)->modify('-1 month');
    $hasta = clone $primer_dia;                       // límite superior, no incluido
} elseif ($periodo === 'rango') {
    $d = DateTime::createFromFormat('!Y-m-d', $_GET['desde'] ?? '');
    $h = DateTime::createFromFormat('!Y-m-d', $_GET['hasta'] ?? '');
    if ($d && $h && $d <= $h && $d->diff($h)->days <= 365) {
        $desde = $d;
        $hasta = (clone $h)->modify('+1 day');
    } else {
        $mensaje_error = "Elige un rango válido (la fecha inicial no puede ser posterior a la final, máximo un año).";
        $periodo = 'mes';
    }
} else {
    $periodo = 'mes';
}
if ($periodo === 'mes') {
    $desde = clone $primer_dia;
    $hasta = (clone $primer_dia)->modify('+1 month');
}

$ultimo = (clone $hasta)->modify('-1 day');
$etiqueta = ($periodo === 'rango')
    ? fecha($desde->format('Y-m-d')) . ' al ' . fecha($ultimo->format('Y-m-d'))
    : $meses[$desde->format('n') - 1] . ' ' . $desde->format('Y');

$rango = ['desde' => $desde->format('Y-m-d'), 'hasta' => $hasta->format('Y-m-d')];

// 1. Pagos del periodo: total, por tipo y por canal
$st = $conexion->prepare("
    SELECT COALESCE(SUM(monto), 0) AS total,
           COALESCE(SUM(CASE WHEN tipo_pago = 'Anticipo'    THEN monto ELSE 0 END), 0) AS anticipo,
           COALESCE(SUM(CASE WHEN tipo_pago = 'Liquidación' THEN monto ELSE 0 END), 0) AS liquidacion,
           COALESCE(SUM(CASE WHEN canal_pago LIKE '%línea%' THEN monto ELSE 0 END), 0) AS en_linea
    FROM pago
    WHERE fecha_hora >= :desde AND fecha_hora < :hasta
");
$st->execute($rango);
$pagos = $st->fetch(PDO::FETCH_ASSOC);

$total_ingresos      = (float) $pagos['total'];
$ingreso_anticipo    = (float) $pagos['anticipo'];
$ingreso_liquidacion = (float) $pagos['liquidacion'];
$ingreso_en_linea    = (float) $pagos['en_linea'];
$ingreso_terminal    = $total_ingresos - $ingreso_en_linea;

// 2. Reservaciones registradas en el periodo, por estado
$st = $conexion->prepare("
    SELECT estado, COUNT(*)
    FROM reservacion
    WHERE estado IN ('Activa', 'Cancelada')
      AND fecha_registro >= :desde AND fecha_registro < :hasta
    GROUP BY estado
");
$st->execute($rango);
$reservas = $st->fetchAll(PDO::FETCH_KEY_PAIR);

$reservas_activas    = $reservas['Activa'] ?? 0;
$reservas_canceladas = $reservas['Cancelada'] ?? 0;

// 3. Ocupación de hoy por tipo (las habitaciones en mantenimiento no cuentan como ocupadas)
$tipos_habitacion_ocupacion = $conexion->query("
    SELECT tipo_habitacion AS tipo,
           SUM(disponible = 0 AND estado_limpieza <> 'Mantenimiento') AS ocupadas,
           COUNT(*) AS total
    FROM habitacion
    GROUP BY tipo_habitacion
")->fetchAll(PDO::FETCH_ASSOC);

$total_hab    = array_sum(array_column($tipos_habitacion_ocupacion, 'total'));
$hab_ocupadas = array_sum(array_column($tipos_habitacion_ocupacion, 'ocupadas'));

$pct = fn($parte, $todo) => $todo > 0 ? round($parte / $todo * 100, 1) : 0;
$porcentaje_ocupacion = $pct($hab_ocupadas, $total_hab);

// 4. Ingresos por semana dentro del periodo (bloques de 7 días desde la fecha inicial)
$st = $conexion->prepare("
    SELECT DATE(fecha_hora) AS dia, SUM(monto) AS total
    FROM pago
    WHERE fecha_hora >= :desde AND fecha_hora < :hasta
    GROUP BY DATE(fecha_hora)
");
$st->execute($rango);
$por_dia = $st->fetchAll(PDO::FETCH_KEY_PAIR);

$dias = $desde->diff($hasta)->days;
$etiquetas_semana = [];
$valores_semana   = [];
for ($i = 0; $i < $dias; $i += 7) {
    $fin   = min($i + 6, $dias - 1);
    $total = 0;
    for ($j = $i; $j <= $fin; $j++) {
        $total += (float) ($por_dia[(clone $desde)->modify("+$j days")->format('Y-m-d')] ?? 0);
    }
    $a = (clone $desde)->modify("+$i days");
    $b = (clone $desde)->modify("+$fin days");
    $etiquetas_semana[] = $a->format('d') . '-' . $b->format('d') . ' ' . $abrev[$b->format('n') - 1];
    $valores_semana[]   = round($total, 2);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Costa Azul - Reportes</title>
    <link rel="stylesheet" href="repo.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        a.filter-btn { text-decoration: none; display: inline-block; }
        .range-form { display: inline-flex; align-items: center; gap: 8px; margin-left: 12px; font-size: 13px; }
        .range-form input { padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; }
    </style>
</head>
<body>

    <header class="navbar">
        <div class="logo">Hotel Costa Azul</div>
        <nav class="nav-links">
            <a href="repo.php" class="active">Reportes</a>
            <a href="../Tarifas/tari.php">Tarifas</a>
            <a href="../Politicas/poli.php">Políticas</a>
            <a href="../Usuarios/usuarios.php">Usuarios</a>
        </nav>
        <div class="user-area">
            <div class="user-profile">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['nombre']); ?></span>
                <span class="user-role"><?php echo ucfirst(htmlspecialchars($_SESSION['rol'])); ?></span>
            </div>
            <a href="../../config/logout.php" class="btn-logout">Salir</a>
        </div>
    </header>

    <div class="main-layout">

        <main class="content-panel">
            <h1 class="page-title">Reportes</h1>
            <p class="page-subtitle">Ingresos y reservaciones del periodo: <?php echo htmlspecialchars($etiqueta); ?></p>

            <?php if ($mensaje_error): ?>
                <div style="color: red; margin-bottom: 15px; font-weight: bold;"><?php echo htmlspecialchars($mensaje_error); ?></div>
            <?php endif; ?>

            <div class="period-filters">
                <a href="repo.php" class="filter-btn <?php echo $periodo === 'mes' ? 'active' : ''; ?>">Este mes</a>
                <a href="repo.php?periodo=anterior" class="filter-btn <?php echo $periodo === 'anterior' ? 'active' : ''; ?>">Mes anterior</a>
                <form method="GET" class="range-form">
                    <input type="hidden" name="periodo" value="rango">
                    <input type="date" name="desde" value="<?php echo $periodo === 'rango' ? $desde->format('Y-m-d') : ''; ?>" required>
                    <span>a</span>
                    <input type="date" name="hasta" value="<?php echo $periodo === 'rango' ? $ultimo->format('Y-m-d') : ''; ?>" required>
                    <button type="submit" class="filter-btn <?php echo $periodo === 'rango' ? 'active' : ''; ?>">Rango de fechas</button>
                </form>
            </div>

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

            <section class="section-card">
                <h3>Ingresos por semana</h3>
                <div class="chart-container">
                    <canvas id="weeklyRevenueChart"></canvas>
                </div>
            </section>

            <section class="section-card">
                <h3>Ocupación por tipo de habitación (hoy)</h3>
                <div class="occupancy-list">
                    <?php foreach ($tipos_habitacion_ocupacion as $t): ?>
                        <div class="occupancy-item">
                            <span class="room-type"><?php echo htmlspecialchars($t['tipo']); ?></span>
                            <div class="progress-bar-bg">
                                <div class="progress-bar-fill" style="width: <?php echo $pct($t['ocupadas'], $t['total']); ?>%;"></div>
                            </div>
                            <span class="room-count"><?php echo (int) $t['ocupadas']; ?> de <?php echo (int) $t['total']; ?></span>
                        </div>
                    <?php endforeach; ?>

                    <?php if (!$tipos_habitacion_ocupacion): ?>
                        <p style="text-align: center; color: #666;">No hay datos de habitaciones registrados.</p>
                    <?php endif; ?>
                </div>
            </section>
        </main>

        <aside class="summary-panel">
            <h2>Resumen</h2>
            <p class="summary-period">Periodo: <?php echo htmlspecialchars($etiqueta); ?></p>

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

    <script>
        if (typeof Chart !== 'undefined') {
            new Chart(document.getElementById('weeklyRevenueChart'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($etiquetas_semana); ?>,
                    datasets: [{
                        label: 'Ingresos',
                        data: <?php echo json_encode($valores_semana); ?>,
                        backgroundColor: '#0d2746',
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { callback: v => '$' + v.toLocaleString('es-MX') } }
                    }
                }
            });
        }
    </script>
</body>
</html>