<?php
require_once '../../config/sesion.php';
verificarAcceso('admin');

require_once '../../config/conexion.php';

function fecha(string $f): string {
    $meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    $t = strtotime($f);
    if ($t === false) {
        return $f;
    }
    return date('d', $t) . ' ' . $meses[date('n', $t) - 1] . ' ' . date('Y', $t);
}

$hoy_str       = date('Y-m-d');
$mensaje_exito = isset($_GET['ok']) ? "Nueva política guardada con éxito." : '';
$mensaje_error = '';

$form = [
    'descripcion'   => '',
    'vigente_desde' => $hoy_str,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['descripcion']   = trim((string) ($_POST['descripcion'] ?? ''));
    $form['vigente_desde'] = trim((string) ($_POST['vigente_desde'] ?? ''));

    $dt_nueva = DateTime::createFromFormat('!Y-m-d', $form['vigente_desde']);
    $fecha_valida = $dt_nueva && $dt_nueva->format('Y-m-d') === $form['vigente_desde'];
    $dt_hoy = new DateTime($hoy_str);

    if ($form['descripcion'] === '' || $form['vigente_desde'] === '') {
        $mensaje_error = "Completa todos los campos.";
    } elseif (mb_strlen($form['descripcion']) > 500) {
        $mensaje_error = "La descripción no puede exceder los 500 caracteres.";
    } elseif (!$fecha_valida) {
        $mensaje_error = "La fecha de inicio no tiene un formato válido (AAAA-MM-DD).";
    } elseif ($dt_nueva > $dt_hoy) {
        $mensaje_error = "La fecha de inicio no puede ser futura porque la política entra en vigor al guardarse.";
    } else {
        try {
            // Las operaciones van juntas y con bloqueo para validar coherencia con la política vigente
            $conexion->beginTransaction();

            $st_vig = $conexion->query(
                "SELECT id_politica, fecha_inicio FROM politica_cancelacion WHERE vigente = 1 ORDER BY id_politica DESC LIMIT 1 FOR UPDATE"
            );
            $politica_actual = $st_vig->fetch(PDO::FETCH_ASSOC);

            if ($politica_actual && !empty($politica_actual['fecha_inicio'])) {
                $dt_inicio_actual = DateTime::createFromFormat('!Y-m-d', substr((string) $politica_actual['fecha_inicio'], 0, 10));
                if ($dt_inicio_actual && $dt_nueva < $dt_inicio_actual) {
                    $conexion->rollBack();
                    $mensaje_error = "La fecha de vigencia (" . fecha($form['vigente_desde']) . ") no puede ser anterior al inicio de la política actual (" . fecha($politica_actual['fecha_inicio']) . ").";
                    goto render_poli;
                }
            }

            $conexion->prepare("UPDATE politica_cancelacion SET vigente = 0, fecha_fin = ? WHERE vigente = 1")
                     ->execute([$form['vigente_desde']]);

            $conexion->prepare("INSERT INTO politica_cancelacion (descripcion, vigente, fecha_inicio, fecha_fin) VALUES (?, 1, ?, NULL)")
                     ->execute([$form['descripcion'], $form['vigente_desde']]);

            $conexion->commit();

            // Evita que al refrescar la página se reenvíe el formulario
            header("Location: poli.php?ok=1");
            exit;
        } catch (PDOException $e) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            error_log('Políticas - guardar: ' . $e->getMessage());
            $mensaje_error = "No se pudo guardar la política.";
        }
    }
}

render_poli:

$lista_politicas = $conexion->query(
    "SELECT descripcion, fecha_inicio, fecha_fin, vigente
     FROM politica_cancelacion
     ORDER BY fecha_inicio DESC, id_politica DESC"
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Costa Azul - Políticas de Cancelación</title>
    <link rel="stylesheet" href="poli.css">
</head>
<body>

    <header class="navbar">
        <div class="logo">Hotel Costa Azul</div>
        <nav class="nav-links">
            <a href="../Reportes/repo.php">Reportes</a>
            <a href="../Tarifas/tari.php">Tarifas</a>
            <a href="poli.php" class="active">Políticas</a>
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
            <h1 class="page-title">Políticas de cancelación</h1>
            <p class="page-subtitle">Hoy · <?php echo fecha($hoy_str); ?> · reglas que se aplican a las nuevas reservaciones</p>

            <?php if ($mensaje_exito): ?>
                <div class="alert alert-success" style="color: green; margin-bottom: 15px; font-weight: bold;">
                    <?php echo htmlspecialchars($mensaje_exito, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            <?php if ($mensaje_error): ?>
                <div class="alert alert-danger" style="color: red; margin-bottom: 15px; font-weight: bold;">
                    <?php echo htmlspecialchars($mensaje_error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <div class="table-card">
                <table class="policies-table">
                    <thead>
                        <tr>
                            <th>DESCRIPCIÓN</th>
                            <th>DESDE</th>
                            <th>HASTA</th>
                            <th>ESTADO</th>
                        </tr>
                    </thead>
                    <tbody id="policiesTableBody">
                        <?php foreach ($lista_politicas as $pol): ?>
                            <tr class="<?php echo $pol['vigente'] ? 'row-highlight' : ''; ?>">
                                <td class="desc-cell"><?php echo htmlspecialchars($pol['descripcion'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo fecha($pol['fecha_inicio']); ?></td>
                                <td><?php echo $pol['fecha_fin'] ? fecha($pol['fecha_fin']) : '—'; ?></td>
                                <td>
                                    <span class="badge <?php echo $pol['vigente'] ? 'badge-vigente' : 'badge-historica'; ?>">
                                        <?php echo $pol['vigente'] ? 'Vigente' : 'Histórica'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$lista_politicas): ?>
                            <tr>
                                <td colspan="4" style="text-align: center;">No hay políticas registradas todavía.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <p class="table-footer-note">
                    Cada reservación conserva la política que estaba vigente cuando se hizo, aunque después cambie.
                </p>
            </div>
        </main>

        <aside class="form-panel">
            <h2>Nueva política</h2>
            <p class="form-subtitle">Al guardar, la política actual pasa a histórica.</p>

            <form id="policyForm" method="POST">
                <div class="form-group">
                    <label for="policyDesc">Descripción de reglas y penalizaciones</label>
                    <textarea id="policyDesc" name="descripcion" rows="5" maxlength="500" placeholder="Escribe las reglas..." required><?php echo htmlspecialchars($form['descripcion'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="startDate">Vigente desde</label>
                    <input type="date" id="startDate" name="vigente_desde" max="<?php echo htmlspecialchars($hoy_str, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($form['vigente_desde'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <button type="submit" class="btn-submit">Guardar nueva política</button>
            </form>

            <p class="disclaimer-text">
                Una política guardada no se edita: para cambiarla se crea una nueva.
            </p>
        </aside>

    </div>

</body>
</html>