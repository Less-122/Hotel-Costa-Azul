<?php
require_once '../../config/sesion.php';
verificarAcceso(['admin']);

require_once '../../config/conexion.php';

function fecha(string $f): string {
    $meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    $t = strtotime($f);
    return date('d', $t) . ' ' . $meses[date('n', $t) - 1] . ' ' . date('Y', $t);
}

$mensaje_exito = isset($_GET['ok']) ? "Nueva política guardada con éxito." : '';
$mensaje_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $descripcion   = trim($_POST['descripcion'] ?? '');
    $vigente_desde = $_POST['vigente_desde'] ?? '';

    if ($descripcion === '' || $vigente_desde === '') {
        $mensaje_error = "Completa todos los campos.";
    } else {
        try {
            // Las dos operaciones van juntas: si una falla, no se aplica ninguna
            $conexion->beginTransaction();

            $conexion->prepare("UPDATE politica_cancelacion SET vigente = 0, fecha_fin = ? WHERE vigente = 1")
                     ->execute([$vigente_desde]);

            $conexion->prepare("INSERT INTO politica_cancelacion (descripcion, vigente, fecha_inicio, fecha_fin) VALUES (?, 1, ?, NULL)")
                     ->execute([$descripcion, $vigente_desde]);

            $conexion->commit();

            // Evita que al refrescar la página se reenvíe el formulario
            header("Location: poli.php?ok=1");
            exit;
        } catch (PDOException $e) {
            $conexion->rollBack();
            $mensaje_error = "No se pudo guardar la política.";
        }
    }
}

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
        <div class="user-profile">
            <span class="user-name"><?php echo htmlspecialchars($_SESSION['nombre']); ?></span>
            <span class="user-role"><?php echo ucfirst(htmlspecialchars($_SESSION['rol'])); ?></span>
        </div>
    </header>

    <div class="main-layout">

        <main class="content-panel">
            <h1 class="page-title">Políticas de cancelación</h1>
            <p class="page-subtitle">Hoy · <?php echo fecha(date('Y-m-d')); ?> · reglas que se aplican a las nuevas reservaciones</p>

            <?php if ($mensaje_exito): ?>
                <div class="alert alert-success" style="color: green; margin-bottom: 15px; font-weight: bold;">
                    <?php echo htmlspecialchars($mensaje_exito); ?>
                </div>
            <?php endif; ?>
            <?php if ($mensaje_error): ?>
                <div class="alert alert-danger" style="color: red; margin-bottom: 15px; font-weight: bold;">
                    <?php echo htmlspecialchars($mensaje_error); ?>
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
                                <td class="desc-cell"><?php echo htmlspecialchars($pol['descripcion']); ?></td>
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
                    <textarea id="policyDesc" name="descripcion" rows="5" placeholder="Escribe las reglas..." required></textarea>
                </div>

                <div class="form-group">
                    <label for="startDate">Vigente desde</label>
                    <input type="date" id="startDate" name="vigente_desde" value="<?php echo date('Y-m-d'); ?>" required>
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