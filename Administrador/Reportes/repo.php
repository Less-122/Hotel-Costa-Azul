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

function formatoMoneda(float $monto): string {
    if ($monto < 0) {
        return '-$' . number_format(abs($monto), 2);
    }
    return '$' . number_format($monto, 2);
}

// --- Periodo: mes (por defecto), anterior o rango ---
$periodo_raw   = $_GET['periodo'] ?? 'mes';
$periodo       = is_string($periodo_raw) ? $periodo_raw : 'mes';
$mensaje_error = '';
$primer_dia    = new DateTime('first day of this month 00:00');

if ($periodo === 'anterior') {
    $desde = (clone $primer_dia)->modify('-1 month');
    $hasta = clone $primer_dia;                       // límite superior, no incluido
} elseif ($periodo === 'rango') {
    $raw_desde = $_GET['desde'] ?? '';
    $raw_hasta = $_GET['hasta'] ?? '';

    $d = null;
    $h = null;
    if (is_string($raw_desde) && is_string($raw_hasta)) {
        $d_tmp = DateTime::createFromFormat('!Y-m-d', trim($raw_desde));
        $h_tmp = DateTime::createFromFormat('!Y-m-d', trim($raw_hasta));
        if ($d_tmp && $d_tmp->format('Y-m-d') === trim($raw_desde)) {
            $d = $d_tmp;
        }
        if ($h_tmp && $h_tmp->format('Y-m-d') === trim($raw_hasta)) {
            $h = $h_tmp;
        }
    }

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

// Penalizaciones retenidas de pagos ya cobrados previamente: se muestran en Penalización
// pero no se suman de nuevo en el total neto ni en canales para evitar duplicar ingresos.
$es_retencion_previa = "(p.tipo_pago = 'Penalización' AND p.canal_pago = 'Retención')";

// 1. Movimientos registrados en pago dentro del periodo (separando penalizaciones y devoluciones)
$st = $conexion->prepare("
    SELECT COALESCE(SUM(CASE
               WHEN {$es_retencion_previa} THEN 0
               ELSE p.monto
           END), 0) AS total,
           COALESCE(SUM(CASE
               WHEN p.tipo_pago = 'Devolución' OR p.monto < 0 THEN p.monto
               ELSE 0
           END), 0) AS devoluciones,
           COALESCE(SUM(CASE
               WHEN p.tipo_pago = 'Penalización' THEN ABS(p.monto)
               ELSE 0
           END), 0) AS penalizacion,
           COALESCE(SUM(CASE
               WHEN p.tipo_pago = 'Anticipo' AND p.monto > 0 THEN p.monto
               ELSE 0
           END), 0) AS anticipo,
           COALESCE(SUM(CASE
               WHEN p.tipo_pago = 'Liquidación' AND p.monto > 0 AND p.canal_pago <> 'Sistema' THEN p.monto
               ELSE 0
           END), 0) AS liquidacion,
           COALESCE(SUM(CASE
               WHEN p.canal_pago LIKE '%línea%' AND p.monto > 0 AND NOT ({$es_retencion_previa}) THEN p.monto
               ELSE 0
           END), 0) AS en_linea
    FROM pago p
    WHERE p.fecha_hora >= :desde AND p.fecha_hora < :hasta
");
$st->execute($rango);
$pagos = $st->fetch(PDO::FETCH_ASSOC);

$total_ingresos       = (float) $pagos['total'];
$total_devoluciones   = (float) $pagos['devoluciones'];
$ingreso_penalizacion = (float) $pagos['penalizacion'];
$ingreso_anticipo     = (float) $pagos['anticipo'];
$ingreso_liquidacion  = (float) $pagos['liquidacion'];
$ingreso_en_linea     = (float) $pagos['en_linea'];
$ingreso_terminal     = $total_ingresos - $ingreso_en_linea;

// 2. Reservaciones registradas en el periodo, por estado
$st = $conexion->prepare("
    SELECT estado, COUNT(*)
    FROM reservacion
    WHERE estado IN ('Activa', 'En curso', 'Cancelada')
      AND fecha_registro >= :desde AND fecha_registro < :hasta
    GROUP BY estado
");
$st->execute($rango);
$reservas = $st->fetchAll(PDO::FETCH_KEY_PAIR);

$reservas_activas    = ($reservas['Activa'] ?? 0) + ($reservas['En curso'] ?? 0);
$reservas_canceladas = $reservas['Cancelada'] ?? 0;

// 3. Ocupación calculada por noches reservadas dentro del periodo seleccionado
$total_dias_periodo = max(1, $desde->diff($hasta)->days);
$total_habitaciones_hotel = (int) $conexion->query("SELECT COUNT(*) FROM habitacion WHERE estado_limpieza <> 'Mantenimiento'")->fetchColumn();
$capacitacion_total_noches = $total_dias_periodo * max(1, $total_habitaciones_hotel);

// Consultar reservaciones activas o en curso que cruzan el periodo para sumar sus noches dentro del rango
$st_noches = $conexion->prepare("
    SELECT fecha_entrada, fecha_salida
    FROM reservacion
    WHERE estado IN ('Activa', 'En curso')
      AND fecha_entrada < :hasta
      AND fecha_salida > :desde
");
$st_noches->execute($rango);
$lista_reservas_periodo = $st_noches->fetchAll(PDO::FETCH_ASSOC);

$noches_ocupadas_periodo = 0;
foreach ($lista_reservas_periodo as $res) {
    $f_ent = max(new DateTime($res['fecha_entrada']), clone $desde);
    $f_sal = min(new DateTime($res['fecha_salida']), clone $hasta);
    if ($f_ent < $f_sal) {
        $noches_ocupadas_periodo += $f_ent->diff($f_sal)->days;
    }
}

$pct = fn($parte, $todo) => $todo > 0 ? round($parte / $todo * 100, 1) : 0;
$porcentaje_ocupacion = $pct($noches_ocupadas_periodo, $capacitacion_total_noches);

// Ocupación desglosada por tipo de habitación para el periodo
$tipos_habitacion_lista = $conexion->query("SELECT DISTINCT tipo_habitacion FROM habitacion")->fetchAll(PDO::FETCH_COLUMN);
$tipos_habitacion_ocupacion = [];

$st_t_tot = $conexion->prepare("SELECT COUNT(*) FROM habitacion WHERE tipo_habitacion = ?");
$st_t_occ = $conexion->prepare("
    SELECT r.fecha_entrada, r.fecha_salida
    FROM reservacion r
    JOIN habitacion h ON r.numero_habitacion = h.numero_habitacion
    WHERE h.tipo_habitacion = ? AND r.estado IN ('Activa', 'En curso') AND r.fecha_entrada < ? AND r.fecha_salida > ?
");

foreach ($tipos_habitacion_lista as $tipo) {
    $st_t_tot->execute([$tipo]);
    $tot_tipo = (int) $st_t_tot->fetchColumn();

    $st_t_occ->execute([$tipo, $hasta->format('Y-m-d'), $desde->format('Y-m-d')]);
    $reservas_tipo = $st_t_occ->fetchAll(PDO::FETCH_ASSOC);

    $noches_tipo = 0;
    foreach ($reservas_tipo as $rt) {
        $fe = max(new DateTime($rt['fecha_entrada']), clone $desde);
        $fs = min(new DateTime($rt['fecha_salida']), clone $hasta);
        if ($fe < $fs) {
            $noches_tipo += $fe->diff($fs)->days;
        }
    }
    $max_noches_tipo = $total_dias_periodo * max(1, $tot_tipo);
    $tipos_habitacion_ocupacion[] = [
        'tipo' => $tipo,
        'ocupadas_noches' => $noches_tipo,
        'total_noches' => $max_noches_tipo
    ];
}

// 4. Ingresos por semana dentro del periodo según movimientos en pago (sin duplicar retención previa)
$st = $conexion->prepare("
    SELECT DATE(p.fecha_hora) AS dia,
           SUM(CASE WHEN {$es_retencion_previa} THEN 0 ELSE p.monto END) AS total
    FROM pago p
    WHERE p.fecha_hora >= :desde AND p.fecha_hora < :hasta
    GROUP BY DATE(p.fecha_hora)
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
        .monto-negativo { color: #dc2626 !important; font-weight: 700; }
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
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="user-role"><?php echo ucfirst(htmlspecialchars($_SESSION['rol'], ENT_QUOTES, 'UTF-8')); ?></span>
            </div>
            <a href="../../config/logout.php" class="btn-logout">Salir</a>
        </div>
    </header>

    <div class="main-layout">

        <main class="content-panel">
            <h1 class="page-title">Reportes</h1>
            <p class="page-subtitle">Ingresos y reservaciones del periodo: <?php echo htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8'); ?></p>

            <?php if ($mensaje_error): ?>
                <div style="color: red; margin-bottom: 15px; font-weight: bold;"><?php echo htmlspecialchars($mensaje_error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div class="period-filters">
                <a href="repo.php" class="filter-btn <?php echo $periodo === 'mes' ? 'active' : ''; ?>">Este mes</a>
                <a href="repo.php?periodo=anterior" class="filter-btn <?php echo $periodo === 'anterior' ? 'active' : ''; ?>">Mes anterior</a>
                <form method="GET" class="range-form">
                    <input type="hidden" name="periodo" value="rango">
                    <input type="date" name="desde" value="<?php echo $periodo === 'rango' ? htmlspecialchars($desde->format('Y-m-d'), ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                    <span>a</span>
                    <input type="date" name="hasta" value="<?php echo $periodo === 'rango' ? htmlspecialchars($ultimo->format('Y-m-d'), ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                    <button type="submit" class="filter-btn <?php echo $periodo === 'rango' ? 'active' : ''; ?>">Rango de fechas</button>
                </form>
            </div>

            <div class="kpi-grid">
                <div class="kpi-card <?php echo $total_ingresos < 0 ? 'card-red' : 'card-green'; ?>">
                    <span class="kpi-value <?php echo $total_ingresos < 0 ? 'monto-negativo' : ''; ?>"><?php echo formatoMoneda($total_ingresos); ?></span>
                    <span class="kpi-label">Ingresos del periodo</span>
                </div>
                <div class="kpi-card card-blue">
                    <span class="kpi-value"><?php echo $porcentaje_ocupacion; ?>%</span>
                    <span class="kpi-label">Ocupación del periodo</span>
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
                <h3>Ocupación por tipo de habitación (en el periodo)</h3>
                <div class="occupancy-list">
                    <?php foreach ($tipos_habitacion_ocupacion as $t): ?>
                        <?php $porc_tipo = $pct($t['ocupadas_noches'], $t['total_noches']); ?>
                        <div class="occupancy-item">
                            <span class="room-type"><?php echo htmlspecialchars($t['tipo'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <div class="progress-bar-bg">
                                <div class="progress-bar-fill" style="width: <?php echo $porc_tipo; ?>%;"></div>
                            </div>
                            <span class="room-count"><?php echo $porc_tipo; ?>% (<?php echo (int) $t['ocupadas_noches']; ?> de <?php echo (int) $t['total_noches']; ?> noches)</span>
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
            <p class="summary-period">Periodo: <?php echo htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8'); ?></p>

            <div class="summary-group">
                <h4>Ingresos por tipo de pago</h4>
                <div class="summary-box">
                    <span>Anticipo</span>
                    <strong class="<?php echo $ingreso_anticipo < 0 ? 'monto-negativo' : ''; ?>"><?php echo formatoMoneda($ingreso_anticipo); ?></strong>
                </div>
                <div class="summary-box">
                    <span>Liquidación</span>
                    <strong class="<?php echo $ingreso_liquidacion < 0 ? 'monto-negativo' : ''; ?>"><?php echo formatoMoneda($ingreso_liquidacion); ?></strong>
                </div>
                <div class="summary-box">
                    <span>Penalización</span>
                    <strong class="<?php echo $ingreso_penalizacion < 0 ? 'monto-negativo' : ''; ?>"><?php echo formatoMoneda($ingreso_penalizacion); ?></strong>
                </div>
                <div class="summary-box">
                    <span>Devoluciones</span>
                    <strong class="<?php echo $total_devoluciones < 0 ? 'monto-negativo' : ''; ?>"><?php echo formatoMoneda($total_devoluciones); ?></strong>
                </div>
            </div>

            <div class="summary-group">
                <h4>Ingresos por canal</h4>
                <div class="summary-box">
                    <span>Pasarela en línea</span>
                    <strong class="<?php echo $ingreso_en_linea < 0 ? 'monto-negativo' : ''; ?>"><?php echo formatoMoneda($ingreso_en_linea); ?></strong>
                </div>
                <div class="summary-box">
                    <span>Terminal recepción</span>
                    <strong class="<?php echo $ingreso_terminal < 0 ? 'monto-negativo' : ''; ?>"><?php echo formatoMoneda($ingreso_terminal); ?></strong>
                </div>
            </div>

            <p class="disclaimer-text">
                Los ingresos reflejan los movimientos registrados en el periodo (separando penalizaciones y devoluciones). La ocupación se calcula por noches reservadas dentro del periodo.
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
                        y: { beginAtZero: true, ticks: { callback: v => (v < 0 ? '-$' + Math.abs(v).toLocaleString('es-MX') : '$' + v.toLocaleString('es-MX')) } }
                    }
                }
            });
        }

        (() => {
            const UMBRAL_SLA_MS = 3000;
            const tInicioVista  = performance.now();

            window.addEventListener('load', () => {
                const nav = performance.getEntriesByType('navigation')[0];
                const ms  = nav
                    ? Math.round(nav.responseEnd - nav.startTime)
                    : Math.round(performance.now() - tInicioVista);

                console.info(`[Rendimiento repo.php] ${ms} ms | SLA <= 3000ms: ${ms <= UMBRAL_SLA_MS ? 'OK' : 'EXCEDIDO'}`);
                if (ms > UMBRAL_SLA_MS) {
                    console.warn(`[SLA >3s] La carga de repo.php tardó ${ms} ms.`);
                }
            });
        })();
    </script>
</body>
</html>