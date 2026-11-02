<?php
session_start();
// Validación de seguridad por rol (ej. solo administradores o recepción)
if (!isset($_SESSION['rol']) || ($_SESSION['rol'] !== 'admin' && $_SESSION['rol'] !== 'recepcion')) {
    header("Location: ../auth/login.php");
    exit();
}

// Conexión a la base de datos (ajusta la ruta según tu estructura)
require_once '../config/conexion.php';

$mensaje_exito = '';
$mensaje_error = '';

// Procesar el formulario cuando se envía una nueva política
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar_politica') {
    $descripcion = trim($_POST['descripcion'] ?? '');
    $vigente_desde = $_POST['vigente_desde'] ?? '';

    if (!empty($descripcion) && !empty($vigente_desde)) {
        try {
            // 1. Opcional: Marcar la política actual como terminada (histórica) poniendo una fecha "hasta"
            $stmt_actualizar = $conexion->prepare("UPDATE politicas SET hasta = ? WHERE estado = 'Vigente'");
            $stmt_actualizar->execute([$vigente_desde]);

            // 2. Insertar la nueva política como 'Vigente'
            $stmt_insertar = $conexion->prepare("INSERT INTO politicas (descripcion, desde, hasta, estado) VALUES (?, ?, NULL, 'Vigente')");
            if ($stmt_insertar->execute([$descripcion, $vigente_desde])) {
                $mensaje_exito = "¡Nueva política guardada con éxito!";
            }
        } catch (PDOException $e) {
            $mensaje_error = "Error al guardar en la base de datos: " . $e->getMessage();
        }
    } else {
        $mensaje_error = "Por favor completa todos los campos del formulario.";
    }
}

// Consultar todas las políticas de la base de datos ordenadas por fecha
try {
    $stmt_politicas = $conexion->query("SELECT descripcion, desde, hasta, estado FROM politicas ORDER BY desde DESC");
    $lista_politicas = $stmt_politicas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $lista_politicas = [];
    $mensaje_error = "Error al cargar las políticas.";
}
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

    <!-- Encabezado / Navbar -->
    <header class="navbar">
        <div class="logo">Hotel Costa Azul</div>
        <nav class="nav-links">
            <a href="../Reportes/repo.php">Reportes</a>
            <a href="../Tarifas/tari.php">Tarifas</a>
            <a href="#" class="active">Políticas</a>
        </nav>
        <div class="user-profile">
            <!-- Datos dinámicos de sesión -->
            <span class="user-name"><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></span>
            <span class="user-role"><?php echo ucfirst(htmlspecialchars($_SESSION['rol'] ?? 'Invitado')); ?></span>
        </div>
    </header>

    <!-- Contenido Principal -->
    <div class="main-layout">
        
        <!-- Panel Izquierdo: Tabla de Políticas -->
        <main class="content-panel">
            <h1 class="page-title">Políticas de cancelación</h1>
            <p class="page-subtitle">Hoy · <?php echo date('d M Y'); ?> · reglas que se aplican a las nuevas reservaciones</p>

            <?php if (!empty($mensaje_exito)): ?>
                <div class="alert alert-success" style="color: green; margin-bottom: 15px; font-weight: bold;"><?php echo $mensaje_exito; ?></div>
            <?php endif; ?>
            <?php if (!empty($mensaje_error)): ?>
                <div class="alert alert-danger" style="color: red; margin-bottom: 15px; font-weight: bold;"><?php echo $mensaje_error; ?></div>
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
                        <?php if (count($lista_politicas) > 0): ?>
                            <?php foreach ($lista_politicas as $pol): ?>
                                <tr class="<?php echo ($pol['estado'] === 'Vigente') ? 'row-highlight' : ''; ?>">
                                    <td class="desc-cell"><?php echo htmlspecialchars($pol['descripcion']); ?></td>
                                    <td><?php echo htmlspecialchars(date('d M Y', strtotime($pol['desde']))); ?></td>
                                    <td><?php echo $pol['hasta'] ? htmlspecialchars(date('d M Y', strtotime($pol['hasta']))) : '—'; ?></td>
                                    <td>
                                        <span class="badge <?php echo ($pol['estado'] === 'Vigente') ? 'badge-vigente' : 'badge-historica'; ?>">
                                            <?php echo htmlspecialchars($pol['estado']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
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

        <!-- Panel Derecho: Formulario Nueva Política -->
        <aside class="form-panel">
            <h2>Nueva política</h2>
            <p class="form-subtitle">Al guardar, la política actual pasa a histórica.</p>

            <form id="policyForm" method="POST" action="">
                <input type="hidden" name="accion" value="guardar_politica">
                
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