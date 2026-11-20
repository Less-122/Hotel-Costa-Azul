<?php
require_once '../../config/sesion.php';
verificarAcceso(['recepcionista', 'admin']);

require_once '../../config/conexion.php';

const MAX_DIAS_ANTICIPACION = 365;
const MAX_NOCHES_ESTANCIA   = 90;

function fecha(string $f, bool $con_anio = false): string {
    $meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    $t = strtotime($f);
    return date('d', $t) . ' ' . $meses[date('n', $t) - 1] . ($con_anio ? ' ' . date('Y', $t) : '');
}

/**
 * Valida fechas de entrada y salida bajo las mismas reglas que Reser.php:
 * formato Y-m-d válido, no anterior a hoy, máximo 365 días de anticipación,
 * salida posterior a entrada y máximo 90 noches de estancia.
 */
function validarFechas(string $entrada, string $salida): string {
    $e = DateTime::createFromFormat('!Y-m-d', $entrada);
    $s = DateTime::createFromFormat('!Y-m-d', $salida);

    if (!$e || !$s || $e->format('Y-m-d') !== $entrada || $s->format('Y-m-d') !== $salida) {
        return "Las fechas no son válidas.";
    }

    $hoy_dt    = new DateTime('today');
    $max_fecha = (clone $hoy_dt)->modify('+' . MAX_DIAS_ANTICIPACION . ' days');

    if ($e < $hoy_dt) {
        return "La fecha de entrada no puede ser anterior a hoy.";
    }
    if ($e > $max_fecha || $s > $max_fecha) {
        return "Solo se puede reservar con hasta " . MAX_DIAS_ANTICIPACION . " días de anticipación (máximo " . $max_fecha->format('Y-m-d') . ").";
    }
    if ($s <= $e) {
        return "La salida debe ser posterior a la entrada.";
    }

    $noches_calc = (int) $e->diff($s)->days;
    if ($noches_calc > MAX_NOCHES_ESTANCIA) {
        return "La estancia máxima permitida es de " . MAX_NOCHES_ESTANCIA . " noches.";
    }

    return '';
}

/**
 * Extrae las horas mínimas de anticipación a partir de la descripción de la política.
 * Soporta textos como "48 horas antes", "72 hrs", "3 días antes", etc.
 */
function obtenerHorasAnticipacion(string $descripcion, int $horas_defecto = 72): int {
    if (preg_match('/(\d+)\s*(?:hora|horas|hr|hrs)\b/iu', $descripcion, $m)) {
        return (int) $m[1];
    }
    if (preg_match('/(\d+)\s*(?:d[ií]a|d[ií]as)\b/iu', $descripcion, $m)) {
        return ((int) $m[1]) * 24;
    }
    return $horas_defecto;
}

/**
 * Determina si la política contempla el cobro de la primera noche tras vencer el plazo gratuito.
 */
function politicaCobraPrimeraNoche(string $descripcion): bool {
    return (bool) preg_match('/primera\s+noche|1\s*noche|una\s+noche/iu', $descripcion);
}

/**
 * Valida nombres reales permitiendo letras (incluyendo acentos y ñ),
 * abreviaturas con punto (ej. Ma., J.), partículas en minúscula (de, del, la, etc.),
 * guiones (García-López) y apóstrofos (O'Neil / O’Neil), sin números ni espacios extra.
 */
function validarNombreFormato(string $nombre): bool {
    if (!preg_match('/^[^\s]+( [^\s]+)*$/u', $nombre)) {
        return false;
    }

    $particulasMinuscula = ['de', 'del', 'la', 'las', 'los', 'y', 'e', 'da', 'dos', 'van', 'von'];
    $palabras = explode(' ', $nombre);

    foreach ($palabras as $palabra) {
        $palabraMinuscula = mb_strtolower($palabra, 'UTF-8');
        if (in_array($palabraMinuscula, $particulasMinuscula, true)) {
            if ($palabra !== $palabraMinuscula) {
                return false;
            }
            continue;
        }

        $normalizada = str_replace('’', "'", $palabra);
        $patronSegmento = '(?:\p{Lu}\.|\p{Lu}\p{Ll}+\.?)';
        $patronPalabra  = '/^' . $patronSegmento . "(?:[-']" . $patronSegmento . ')*$/u';

        if (!preg_match($patronPalabra, $normalizada)) {
            return false;
        }
    }

    return true;
}

$id_usuario    = (int) $_SESSION['id_usuario'];
$mensaje_exito = '';
$mensaje_error = '';

// Avisos tras guardar, cancelar, registrar entrada o finalizar (se redirige después del POST para que F5 no lo repita)
if (($_GET['ok'] ?? '') === '1' && preg_match('/^[A-Z0-9]{5}$/', $_GET['codigo'] ?? '')) {
    $mensaje_exito = "¡Reservación creada con éxito! Código asignado: " . $_GET['codigo'];
} elseif (($_GET['entrada'] ?? '') === '1') {
    $hab_ci = preg_match('/^[0-9A-Za-z]{1,5}$/', $_GET['hab'] ?? '') ? $_GET['hab'] : '';
    $mensaje_exito = "Entrada registrada · Reservación marcada como En curso" . ($hab_ci !== '' ? " y habitación {$hab_ci} marcada como Ocupada." : ".");
} elseif (($_GET['finalizada'] ?? '') === '1') {
    $hab_co = preg_match('/^[0-9A-Za-z]{1,5}$/', $_GET['hab'] ?? '') ? $_GET['hab'] : '';
    $mensaje_exito = "Reservación marcada como Finalizada" . ($hab_co !== '' ? " · Habitación {$hab_co} enviada a limpieza (Sucia)." : ".");
} elseif (($_GET['cancelada'] ?? '') === '1') {
    $pen_aplicada = isset($_GET['penalizacion']) ? (float) $_GET['penalizacion'] : 0.0;
    $dev_aplicada = isset($_GET['devolucion']) ? (float) $_GET['devolucion'] : 0.0;

    $mensaje_exito = "Reservación cancelada correctamente.";
    if ($pen_aplicada > 0) {
        $mensaje_exito .= " Penalización aplicada: $" . number_format($pen_aplicada, 2) . " (primera noche).";
    }
    if ($dev_aplicada > 0) {
        $mensaje_exito .= " Se registró una devolución de -$" . number_format($dev_aplicada, 2) . ".";
    }
}

// Política de cancelación vigente (respaldo solo si una reservación no tiene id_politica asignado)
$desc_politica_pol = 'Se permite cancelar sin penalización hasta 72 horas antes de la fecha de entrada.';
try {
    $st_pol = $conexion->query(
        "SELECT descripcion FROM politica_cancelacion WHERE vigente = 1 ORDER BY id_politica DESC LIMIT 1"
    );
    if ($st_pol && ($pol = $st_pol->fetch(PDO::FETCH_ASSOC)) && !empty($pol['descripcion'])) {
        $desc_politica_pol = $pol['descripcion'];
    }
} catch (PDOException $e) {
    error_log('Recepción - política cancelación: ' . $e->getMessage());
}

$tipos_habitacion = $conexion->query("SELECT DISTINCT tipo_habitacion FROM habitacion ORDER BY tipo_habitacion")
                             ->fetchAll(PDO::FETCH_COLUMN);

$hoy           = date('Y-m-d');
$max_fecha_str = (new DateTime('today'))->modify('+' . MAX_DIAS_ANTICIPACION . ' days')->format('Y-m-d');

$form = [
    'nombre_huesped'  => '',
    'telefono'        => '',
    'correo'          => '',
    'fecha_entrada'   => $hoy,
    'fecha_salida'    => date('Y-m-d', strtotime('+1 day')),
    'tipo_habitacion' => $tipos_habitacion[0] ?? 'Sencilla',
    'notas'           => '',
];

// Variables para el buscador de disponibilidad en recepción
$disp_busqueda_activa = false;
$disp_resultado = [];
$busq_entrada = trim((string) ($_GET['disp_entrada'] ?? $hoy));
$busq_salida  = trim((string) ($_GET['disp_salida'] ?? date('Y-m-d', strtotime('+1 day'))));

if (isset($_GET['buscar_disponibilidad'])) {
    $error_busq = validarFechas($busq_entrada, $busq_salida);
    if ($error_busq !== '') {
        $mensaje_error = $error_busq;
    } else {
        $disp_busqueda_activa = true;
        $cond_hoy_disp = ($busq_entrada === $hoy) ? " AND h.disponible = 1" : "";
        try {
            $st_disp = $conexion->prepare("
                SELECT h.tipo_habitacion,
                       MIN(t.precio_noche) AS precio_noche,
                       COUNT(h.numero_habitacion) AS total_tipo,
                       SUM(
                           CASE WHEN NOT EXISTS (
                               SELECT 1 FROM reservacion r
                               WHERE r.numero_habitacion = h.numero_habitacion
                                 AND r.estado IN ('Activa', 'En curso')
                                 AND r.fecha_entrada < ?
                                 AND r.fecha_salida  > ?
                           ) AND h.estado_limpieza <> 'Mantenimiento'{$cond_hoy_disp} THEN 1 ELSE 0 END
                       ) AS disponibles_rango
                FROM habitacion h
                LEFT JOIN tarifa t ON h.tipo_habitacion = t.tipo_habitacion AND t.vigente = 1
                GROUP BY h.tipo_habitacion
            ");
            $st_disp->execute([$busq_salida, $busq_entrada]);
            $disp_resultado = $st_disp->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Recepción - buscador disponibilidad: ' . $e->getMessage());
            $mensaje_error = "Error al consultar la disponibilidad.";
        }
    }
}

$accion = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrfToken($_POST['csrf_token'] ?? null)) {
        $mensaje_error = "Error de validación de seguridad. Por favor, recarga la página e inténtalo de nuevo.";
    } else {
        $accion = $_POST['accion'] ?? '';
    }
}

// 0a. Registrar entrada del huésped: pasa la reservación a 'En curso' y marca la habitación como ocupada (disponible = 0)
if ($accion === 'registrar_entrada') {
    $id_res_ci = (int) ($_POST['id_reservacion'] ?? 0);

    try {
        $conexion->beginTransaction();

        $st_ci = $conexion->prepare("
            SELECT r.id_reservacion, r.numero_habitacion, r.estado, r.notas,
                   h.estado_limpieza
            FROM reservacion r
            JOIN habitacion h ON h.numero_habitacion = r.numero_habitacion
            WHERE r.id_reservacion = ?
            FOR UPDATE
        ");
        $st_ci->execute([$id_res_ci]);
        $res_ci = $st_ci->fetch(PDO::FETCH_ASSOC);

        if (!$res_ci || $res_ci['estado'] !== 'Activa') {
            throw new RuntimeException("Solo se puede registrar la entrada en reservaciones Activas.");
        }
        if (strtolower($res_ci['estado_limpieza']) === 'mantenimiento') {
            throw new RuntimeException("No se puede registrar entrada: la habitación " . $res_ci['numero_habitacion'] . " está en Mantenimiento.");
        }

        $nota_ci = "[Entrada registrada: " . date('Y-m-d H:i') . "]";
        $notas_nuevas = trim(($res_ci['notas'] ?? '') . "\n" . $nota_ci);

        $st_upd_ci = $conexion->prepare("
            UPDATE reservacion
            SET estado = 'En curso',
                notas = ?,
                modificado_por = ?,
                fecha_modificacion = NOW()
            WHERE id_reservacion = ? AND estado = 'Activa'
        ");
        $st_upd_ci->execute([$notas_nuevas, $id_usuario, $id_res_ci]);

        if ($st_upd_ci->rowCount() !== 1) {
            throw new RuntimeException("No se pudo actualizar el estado a En curso.");
        }

        $conexion->prepare("
            UPDATE habitacion
            SET disponible = 0
            WHERE numero_habitacion = ? AND estado_limpieza <> 'Mantenimiento'
        ")->execute([$res_ci['numero_habitacion']]);

        $conexion->commit();
        header("Location: recep.php?entrada=1&hab=" . urlencode($res_ci['numero_habitacion']));
        exit;
    } catch (RuntimeException $e) {
        if ($conexion->inTransaction()) { $conexion->rollBack(); }
        $mensaje_error = $e->getMessage();
    } catch (PDOException $e) {
        if ($conexion->inTransaction()) { $conexion->rollBack(); }
        error_log('Recepción - registrar entrada: ' . $e->getMessage());
        $mensaje_error = "No se pudo registrar la entrada.";
    }
}

// 0b. Finalizar reservación (Salida): pasa a 'Finalizada', libera para futuras reservas y marca habitación Sucia
if ($accion === 'finalizar_reservacion') {
    $id_res_co = (int) ($_POST['id_reservacion'] ?? 0);

    try {
        $conexion->beginTransaction();

        $st_co = $conexion->prepare("
            SELECT r.id_reservacion, r.numero_habitacion, r.estado, r.notas,
                   h.estado_limpieza
            FROM reservacion r
            JOIN habitacion h ON h.numero_habitacion = r.numero_habitacion
            WHERE r.id_reservacion = ?
            FOR UPDATE
        ");
        $st_co->execute([$id_res_co]);
        $res_co = $st_co->fetch(PDO::FETCH_ASSOC);

        if (!$res_co || !in_array($res_co['estado'], ['Activa', 'En curso'], true)) {
            throw new RuntimeException("Solo se pueden finalizar reservaciones Activas o En curso.");
        }

        $nota_co = "[Salida / Finalizada: " . date('Y-m-d H:i') . "]";
        $notas_nuevas = trim(($res_co['notas'] ?? '') . "\n" . $nota_co);

        $st_upd_res = $conexion->prepare("
            UPDATE reservacion
            SET estado = 'Finalizada',
                notas = ?,
                modificado_por = ?,
                fecha_modificacion = NOW()
            WHERE id_reservacion = ? AND estado IN ('Activa', 'En curso')
        ");
        $st_upd_res->execute([$notas_nuevas, $id_usuario, $id_res_co]);

        if ($st_upd_res->rowCount() !== 1) {
            throw new RuntimeException("No se pudo actualizar el estado de la reservación a Finalizada.");
        }

        $es_mant_co = (strtolower($res_co['estado_limpieza']) === 'mantenimiento');
        if ($es_mant_co) {
            $conexion->prepare("UPDATE habitacion SET disponible = 0 WHERE numero_habitacion = ?")
                     ->execute([$res_co['numero_habitacion']]);
        } else {
            $conexion->prepare("UPDATE habitacion SET estado_limpieza = 'Sucia', disponible = 1 WHERE numero_habitacion = ?")
                     ->execute([$res_co['numero_habitacion']]);
        }

        $conexion->commit();
        header("Location: recep.php?finalizada=1&hab=" . urlencode($res_co['numero_habitacion']));
        exit;
    } catch (RuntimeException $e) {
        if ($conexion->inTransaction()) { $conexion->rollBack(); }
        $mensaje_error = $e->getMessage();
    } catch (PDOException $e) {
        if ($conexion->inTransaction()) { $conexion->rollBack(); }
        error_log('Recepción - finalizar: ' . $e->getMessage());
        $mensaje_error = "No se pudo finalizar la reservación.";
    }
}

// 1. Cancelar una reservación (RF-09) aplicando la política de esa reservación (RN-01)
if ($accion === 'cancelar_reservacion') {
    $id_reservacion_cancelar = (int) ($_POST['id_reservacion'] ?? 0);

    try {
        $st_res = $conexion->prepare("
            SELECT r.id_reservacion, r.fecha_entrada, r.estado, r.notas,
                   t.precio_noche,
                   p.descripcion AS politica_descripcion
            FROM reservacion r
            JOIN tarifa t ON r.id_tarifa = t.id_tarifa
            LEFT JOIN politica_cancelacion p ON r.id_politica = p.id_politica
            WHERE r.id_reservacion = ?
        ");
        $st_res->execute([$id_reservacion_cancelar]);
        $info_res = $st_res->fetch(PDO::FETCH_ASSOC);

        if (!$info_res || $info_res['estado'] !== 'Activa') {
            $mensaje_error = "No se pudo cancelar: solo se pueden cancelar reservaciones en estado Activa.";
        } else {
            $desc_res = !empty($info_res['politica_descripcion'])
                ? $info_res['politica_descripcion']
                : $desc_politica_pol;
            $horas_limite_res = obtenerHorasAnticipacion($desc_res, 72);
            $precio_noche_res = round((float) $info_res['precio_noche'], 2);

            $ahora = new DateTime();
            $fecha_entrada_dt = new DateTime($info_res['fecha_entrada'] . ' 00:00:00');
            $segundos_restantes = $fecha_entrada_dt->getTimestamp() - $ahora->getTimestamp();
            $horas_restantes = $segundos_restantes / 3600;

            $monto_penalizacion = 0.0;
            $bloqueado = false;

            if ($segundos_restantes <= 0) {
                $bloqueado = true;
                $mensaje_error = "No se puede cancelar: la fecha de entrada ya pasó o la reservación ya dio inicio.";
            } elseif ($horas_restantes < $horas_limite_res) {
                if (politicaCobraPrimeraNoche($desc_res)) {
                    $monto_penalizacion = $precio_noche_res;
                } else {
                    $bloqueado = true;
                    $etiqueta_plazo = ($horas_limite_res % 24 === 0 && $horas_limite_res >= 24)
                        ? ($horas_limite_res / 24) . " día(s)"
                        : $horas_limite_res . " hora(s)";
                    $mensaje_error = "No se puede cancelar: el plazo límite según la política ({$etiqueta_plazo} antes) ha expirado.";
                }
            }

            if (!$bloqueado) {
                $conexion->beginTransaction();

                $st_pagado = $conexion->prepare("SELECT COALESCE(SUM(monto), 0) FROM pago WHERE id_reservacion = ?");
                $st_pagado->execute([$id_reservacion_cancelar]);
                $ya_pagado = round((float) $st_pagado->fetchColumn(), 2);

                $monto_reembolso = ($ya_pagado > 0)
                    ? max(0.0, round($ya_pagado - $monto_penalizacion, 2))
                    : 0.0;

                $notas_extra = [];

                if ($monto_penalizacion > 0) {
                    $notas_extra[] = "[Penalización por cancelación tardía: $" . number_format($monto_penalizacion, 2) . " (1ra noche)]";

                    $cargo_a_registrar = max(0.0, round($monto_penalizacion - $ya_pagado, 2));
                    if ($cargo_a_registrar > 0) {
                        $st_cargo = $conexion->prepare(
                            "INSERT INTO pago (monto, fecha_hora, tipo_pago, canal_pago, id_reservacion, id_recepcionista)
                             VALUES (?, NOW(), 'Liquidación', 'Recepción', ?, ?)"
                        );
                        $st_cargo->execute([$cargo_a_registrar, $id_reservacion_cancelar, $id_usuario]);
                    }
                }

                if ($monto_reembolso > 0) {
                    $monto_negativo = -abs($monto_reembolso);
                    $st_dev = $conexion->prepare(
                        "INSERT INTO pago (monto, fecha_hora, tipo_pago, canal_pago, id_reservacion, id_recepcionista)
                         VALUES (?, NOW(), 'Liquidación', 'Recepción', ?, ?)"
                    );
                    $st_dev->execute([$monto_negativo, $id_reservacion_cancelar, $id_usuario]);
                    $notas_extra[] = "[Devolución registrada: -$" . number_format($monto_reembolso, 2) . "]";
                }

                $notas_actualizadas = trim(
                    ($info_res['notas'] ?? '') .
                    (!empty($notas_extra) ? "\n" . implode("\n", $notas_extra) : '')
                );

                $st = $conexion->prepare(
                    "UPDATE reservacion
                     SET estado = 'Cancelada',
                         notas = NULLIF(?, ''),
                         modificado_por = ?,
                         fecha_modificacion = NOW()
                     WHERE id_reservacion = ? AND estado = 'Activa'"
                );
                $st->execute([$notas_actualizadas, $id_usuario, $id_reservacion_cancelar]);

                if ($st->rowCount() === 1) {
                    $conexion->commit();
                    $url_ok = "recep.php?cancelada=1";
                    if ($monto_penalizacion > 0) {
                        $url_ok .= "&penalizacion=" . urlencode((string) $monto_penalizacion);
                    }
                    if ($monto_reembolso > 0) {
                        $url_ok .= "&devolucion=" . urlencode((string) $monto_reembolso);
                    }
                    header("Location: " . $url_ok);
                    exit;
                }

                $conexion->rollBack();
                $mensaje_error = "No se pudo completar la cancelación.";
            }
        }
    } catch (PDOException $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        error_log('Recepción - cancelar: ' . $e->getMessage());
        $mensaje_error = "No se pudo cancelar la reservación debido a un error en la base de datos.";
    }
}

// 2. Crear una reservación desde Recepción
if ($accion === 'nueva_reservacion') {
    foreach ($form as $campo => $_) {
        $form[$campo] = trim($_POST[$campo] ?? $form[$campo]);
    }
    $form['notas'] = mb_substr($form['notas'], 0, 255);

    $error_fechas = validarFechas($form['fecha_entrada'], $form['fecha_salida']);

    if ($form['nombre_huesped'] === '' || $form['telefono'] === '' || $form['fecha_entrada'] === '' || $form['fecha_salida'] === '') {
        $mensaje_error = "Por favor completa todos los campos obligatorios.";
    } elseif (mb_strlen($form['nombre_huesped']) > 100) {
        $mensaje_error = "El nombre es demasiado largo (máximo 100 caracteres).";
    } elseif (!validarNombreFormato($form['nombre_huesped'])) {
        $mensaje_error = "El nombre solo puede contener letras, puntos, partículas en minúscula (de, del, la), guiones o apóstrofos (ej. María de la Cruz, García-López, O’Neil).";
    } elseif (!preg_match('/^\d{10}$/', $form['telefono'])) {
        $mensaje_error = "El teléfono debe tener 10 dígitos.";
    } elseif ($form['correo'] !== '' && (!filter_var($form['correo'], FILTER_VALIDATE_EMAIL) || strlen($form['correo']) > 80)) {
        $mensaje_error = "El correo electrónico no es válido.";
    } elseif ($error_fechas !== '') {
        $mensaje_error = $error_fechas;
    } elseif (!in_array($form['tipo_habitacion'], $tipos_habitacion, true)) {
        $mensaje_error = "Tipo de habitación no válido.";
    } else {
        try {
            $conexion->beginTransaction();

            $conexion->prepare("SELECT numero_habitacion FROM habitacion WHERE tipo_habitacion = ? FOR UPDATE")
                     ->execute([$form['tipo_habitacion']]);

            $id_huesped = null;
            if ($form['correo'] !== '') {
                $st = $conexion->prepare("SELECT id_usuario, rol FROM usuario WHERE correo = ?");
                $st->execute([$form['correo']]);
                $existente = $st->fetch(PDO::FETCH_ASSOC);
                if ($existente) {
                    if (strtolower($existente['rol']) !== 'huesped') {
                        throw new RuntimeException("Ese correo pertenece a personal del hotel, no a un huésped.");
                    }
                    $id_huesped = (int) $existente['id_usuario'];
                }
            }
            if ($id_huesped === null) {
                $correo_cuenta = $form['correo'] !== '' ? $form['correo'] : 'sin-correo-' . bin2hex(random_bytes(6)) . '@costaazul.invalid';
                $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                $conexion->prepare("INSERT INTO usuario (nombre, contacto, correo, password, rol) VALUES (?, ?, ?, ?, 'Huesped')")
                         ->execute([$form['nombre_huesped'], $form['telefono'], $correo_cuenta, $hash]);
                $id_huesped = (int) $conexion->lastInsertId();
            }

            $solo_libres_hoy = ($form['fecha_entrada'] === $hoy) ? " AND h.disponible = 1" : "";
            $st = $conexion->prepare("
                SELECT h.numero_habitacion
                FROM habitacion h
                WHERE h.tipo_habitacion = ?
                  AND h.estado_limpieza <> 'Mantenimiento'{$solo_libres_hoy}
                  AND NOT EXISTS (
                        SELECT 1 FROM reservacion r
                        WHERE r.numero_habitacion = h.numero_habitacion
                          AND r.estado IN ('Activa', 'En curso')
                          AND r.fecha_entrada < ?
                          AND r.fecha_salida  > ?
                  )
                ORDER BY h.numero_habitacion
                LIMIT 1
            ");
            $st->execute([$form['tipo_habitacion'], $form['fecha_salida'], $form['fecha_entrada']]);
            $numero_habitacion = $st->fetchColumn();
            if ($numero_habitacion === false) {
                throw new RuntimeException("No hay habitaciones " . $form['tipo_habitacion'] . " disponibles en esas fechas.");
            }

            $st = $conexion->prepare("SELECT id_tarifa FROM tarifa WHERE tipo_habitacion = ? AND vigente = 1 LIMIT 1");
            $st->execute([$form['tipo_habitacion']]);
            $id_tarifa   = $st->fetchColumn();
            $id_politica = $conexion->query("SELECT id_politica FROM politica_cancelacion WHERE vigente = 1 ORDER BY id_politica DESC LIMIT 1")->fetchColumn();
            if ($id_tarifa === false || $id_politica === false) {
                throw new RuntimeException("No hay tarifa o política de cancelación vigente. Avisa al administrador.");
            }

            $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            $existe   = $conexion->prepare("SELECT 1 FROM reservacion WHERE numero_confirmacion = ?");
            do {
                $codigo = '';
                for ($i = 0; $i < 5; $i++) {
                    $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
                }
                $existe->execute([$codigo]);
            } while ($existe->fetchColumn());

            $conexion->prepare("
                INSERT INTO reservacion
                    (numero_confirmacion, fecha_entrada, fecha_salida, estado, notas, canal,
                     id_huesped, id_recepcionista, numero_habitacion, id_tarifa, id_politica)
                VALUES (?, ?, ?, 'Activa', ?, 'Recepción', ?, ?, ?, ?, ?)
            ")->execute([
                $codigo, $form['fecha_entrada'], $form['fecha_salida'],
                $form['notas'] !== '' ? $form['notas'] : null,
                $id_huesped, $id_usuario, $numero_habitacion, $id_tarifa, $id_politica,
            ]);

            $conexion->commit();
            header("Location: recep.php?ok=1&codigo=" . $codigo);
            exit;
        } catch (RuntimeException $e) {
            if ($conexion->inTransaction()) { $conexion->rollBack(); }
            $mensaje_error = $e->getMessage();
        } catch (PDOException $e) {
            if ($conexion->inTransaction()) { $conexion->rollBack(); }
            error_log('Recepción - nueva reservación: ' . $e->getMessage());
            $mensaje_error = "No se pudo guardar la reservación. Inténtalo de nuevo.";
        }
    }
}

// 3. Lista de reservaciones (las 50 más recientes)
$reservaciones = $conexion->query("
    SELECT r.id_reservacion,
           r.numero_confirmacion AS codigo,
           u.nombre              AS nombre_huesped,
           r.fecha_entrada, r.fecha_salida,
           h.tipo_habitacion, r.numero_habitacion,
           r.canal, r.estado, r.notas,
           t.precio_noche,
           COALESCE((SELECT SUM(monto) FROM pago WHERE id_reservacion = r.id_reservacion), 0) AS total_pagado,
           p.descripcion         AS politica_descripcion
    FROM reservacion r
    JOIN usuario u    ON u.id_usuario = r.id_huesped
    JOIN habitacion h ON h.numero_habitacion = r.numero_habitacion
    JOIN tarifa t     ON r.id_tarifa = t.id_tarifa
    LEFT JOIN politica_cancelacion p ON r.id_politica = p.id_politica
    ORDER BY r.fecha_registro DESC, r.id_reservacion DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

// 4. Contadores de habitaciones
$stats = $conexion->query("
    SELECT SUM(disponible = 1 AND estado_limpieza <> 'Mantenimiento') AS libres,
           SUM(disponible = 0 AND estado_limpieza <> 'Mantenimiento') AS ocupadas,
           SUM(estado_limpieza = 'Sucia')         AS sucias,
           SUM(estado_limpieza = 'Mantenimiento') AS mantenimiento
    FROM habitacion
")->fetch(PDO::FETCH_ASSOC);

$nombre_usuario = $_SESSION['nombre'] ?? 'Recepción';
$rol_usuario    = ucfirst($_SESSION['rol'] ?? 'Recepcionista');
$ahora_dt       = new DateTime();

$min_salida_busq = date('Y-m-d', strtotime('+1 day'));
$dt_busq_ent = DateTime::createFromFormat('!Y-m-d', $busq_entrada);
if ($dt_busq_ent && $dt_busq_ent->format('Y-m-d') === $busq_entrada) {
    $min_salida_busq = (clone $dt_busq_ent)->modify('+1 day')->format('Y-m-d');
}

$min_salida_form = date('Y-m-d', strtotime('+1 day'));
$dt_form_ent = DateTime::createFromFormat('!Y-m-d', $form['fecha_entrada']);
if ($dt_form_ent && $dt_form_ent->format('Y-m-d') === $form['fecha_entrada']) {
    $min_salida_form = (clone $dt_form_ent)->modify('+1 day')->format('Y-m-d');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hotel Costa Azul - Panel de Recepción</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="recep.css">
</head>
<body>

  <header class="navbar">
    <div class="nav-brand">Hotel Costa Azul</div>
    <nav class="nav-links">
      <a href="recep.php" class="active">Reservaciones</a>
      <a href="../Habitaciones/hab.php">Habitaciones</a>
      <a href="../Pagos/pagos.php">Pagos</a>
    </nav>
    <div class="user-profile">
      <div class="user-avatar"><i class="fa-regular fa-user"></i></div>
      <div class="user-info">
        <span class="user-name"><?php echo htmlspecialchars($nombre_usuario, ENT_QUOTES, 'UTF-8'); ?></span>
        <span class="user-role"><?php echo htmlspecialchars($rol_usuario, ENT_QUOTES, 'UTF-8'); ?></span>
      </div>
      <a href="../../config/logout.php" title="Cerrar sesión" style="margin-left: 14px; color: inherit;"><i class="fa-solid fa-right-from-bracket"></i></a>
    </div>
  </header>

  <main class="main-container">

    <section class="left-column">

      <div class="header-title-area">
        <h1>Reservaciones</h1>
        <p>Hoy · <?php echo fecha($hoy, true); ?> · incluye reservas en línea y en recepción</p>
      </div>

      <?php if ($mensaje_exito): ?>
          <div class="alert alert-success" style="color: green; margin-bottom: 15px; font-weight: bold;"><?php echo htmlspecialchars($mensaje_exito, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>
      <?php if ($mensaje_error): ?>
          <div class="alert alert-danger" style="color: red; margin-bottom: 15px; font-weight: bold;"><?php echo htmlspecialchars($mensaje_error, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <div class="filter-toolbar">
        <div class="search-box">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" id="searchInput" placeholder="Buscar por nombre o código" onkeyup="filtrarReservaciones()">
        </div>
        <div class="filter-pills">
          <button class="pill active" onclick="filtrarPorEstado('todas', this)">Todas</button>
          <button class="pill" onclick="filtrarPorEstado('activa', this)">Activas</button>
          <button class="pill" onclick="filtrarPorEstado('en curso', this)">En curso</button>
          <button class="pill" onclick="filtrarPorEstado('finalizada', this)">Finalizadas</button>
          <button class="pill" onclick="filtrarPorEstado('cancelada', this)">Canceladas</button>
        </div>
      </div>

      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>CÓDIGO</th>
              <th>HUÉSPED</th>
              <th>ENTRADA</th>
              <th>SALIDA</th>
              <th>HABITACIÓN</th>
              <th>CANAL</th>
              <th>ESTADO</th>
              <th>NOTAS</th>
              <th>ACCIONES</th>
            </tr>
          </thead>
          <tbody id="reservationsTableBody">
            <?php foreach ($reservaciones as $res): ?>
              <?php
                $estado_raw   = strtolower($res['estado']);
                $estado_class = ($estado_raw === 'cancelada') ? 'cancelled' : (($estado_raw === 'finalizada') ? 'finalized' : 'active');
                $monto_pagado = (float) $res['total_pagado'];
                $nota_texto   = $res['notas'] ?? '';
                $desc_fila    = !empty($res['politica_descripcion']) ? $res['politica_descripcion'] : $desc_politica_pol;

                $horas_limite_fila = obtenerHorasAnticipacion($desc_fila, 72);
                $entrada_fila_dt   = new DateTime($res['fecha_entrada'] . ' 00:00:00');
                $seg_restantes_fila = $entrada_fila_dt->getTimestamp() - $ahora_dt->getTimestamp();
                $horas_restantes_fila = $seg_restantes_fila / 3600;
                $penalizacion_fila = ($seg_restantes_fila > 0 && $horas_restantes_fila < $horas_limite_fila && politicaCobraPrimeraNoche($desc_fila))
                    ? round((float) $res['precio_noche'], 2)
                    : 0.0;
              ?>
              <tr class="res-row" data-estado="<?php echo htmlspecialchars($estado_raw, ENT_QUOTES, 'UTF-8'); ?>">
                <td><b><?php echo htmlspecialchars($res['codigo'], ENT_QUOTES, 'UTF-8'); ?></b></td>
                <td class="guest-cell"><?php echo htmlspecialchars($res['nombre_huesped'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo fecha($res['fecha_entrada']); ?></td>
                <td><?php echo fecha($res['fecha_salida']); ?></td>
                <td><?php echo htmlspecialchars($res['tipo_habitacion'] . ' · ' . $res['numero_habitacion'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($res['canal'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><span class="status-badge <?php echo $estado_class; ?>"><?php echo htmlspecialchars($res['estado'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                <td>
                  <?php if (!empty($nota_texto)): ?>
                    <button type="button" class="btn-view-note" onclick="mostrarNotaModal('<?php echo htmlspecialchars($res['codigo'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo htmlspecialchars(json_encode($nota_texto), ENT_QUOTES, 'UTF-8'); ?>)" style="background: #f1f5f9; border: 1px solid #cbd5e1; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 12px; color: #334155;" title="Ver nota">
                      <i class="fa-regular fa-note-sticky" style="color: #d97706;"></i> Ver nota
                    </button>
                  <?php else: ?>
                    <span style="color: #94a3b8; font-size: 12px;">Sin nota</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($estado_raw === 'activa'): ?>
                    <div style="display: flex; flex-direction: column; align-items: flex-start; gap: 3px;">
                      <form method="POST" style="margin: 0;">
                        <?php echo campoCsrf(); ?>
                        <input type="hidden" name="accion" value="registrar_entrada">
                        <input type="hidden" name="id_reservacion" value="<?php echo (int) $res['id_reservacion']; ?>">
                        <button type="submit" style="background: transparent; border: none; padding: 0; color: #0f2540; font-weight: 600; font-size: 13px; cursor: pointer; text-align: left;">Registrar entrada</button>
                      </form>
                      <form method="POST" style="margin: 0;" onsubmit="return confirm('¿Finalizar reservación <?php echo htmlspecialchars($res['codigo'], ENT_QUOTES, 'UTF-8'); ?> y marcar habitación <?php echo htmlspecialchars($res['numero_habitacion'], ENT_QUOTES, 'UTF-8'); ?> como Sucia?');">
                        <?php echo campoCsrf(); ?>
                        <input type="hidden" name="accion" value="finalizar_reservacion">
                        <input type="hidden" name="id_reservacion" value="<?php echo (int) $res['id_reservacion']; ?>">
                        <button type="submit" class="btn-cancel-row" style="background: transparent; border: none; padding: 0; color: #1e293b; font-weight: 500; font-size: 13px; cursor: pointer; text-align: left;">Finalizar</button>
                      </form>
                      <form method="POST" style="margin: 0;" onsubmit="return confirmarCancelacion(this, '<?php echo htmlspecialchars($res['codigo'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo $monto_pagado; ?>, <?php echo $penalizacion_fila; ?>, <?php echo htmlspecialchars(json_encode($desc_fila), ENT_QUOTES, 'UTF-8'); ?>);">
                        <?php echo campoCsrf(); ?>
                        <input type="hidden" name="accion" value="cancelar_reservacion">
                        <input type="hidden" name="id_reservacion" value="<?php echo (int) $res['id_reservacion']; ?>">
                        <button type="submit" class="btn-cancel-row" style="padding: 0; font-size: 13px;">Cancelar</button>
                      </form>
                    </div>
                  <?php elseif ($estado_raw === 'en curso'): ?>
                    <form method="POST" style="margin: 0;" onsubmit="return confirm('¿Finalizar estadía de <?php echo htmlspecialchars($res['codigo'], ENT_QUOTES, 'UTF-8'); ?> y marcar habitación <?php echo htmlspecialchars($res['numero_habitacion'], ENT_QUOTES, 'UTF-8'); ?> como Sucia?');">
                      <?php echo campoCsrf(); ?>
                      <input type="hidden" name="accion" value="finalizar_reservacion">
                      <input type="hidden" name="id_reservacion" value="<?php echo (int) $res['id_reservacion']; ?>">
                      <button type="submit" style="background: transparent; border: none; padding: 0; color: #0f2540; font-weight: 600; font-size: 13px; cursor: pointer; text-align: left;">Finalizar</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>

            <?php if (!$reservaciones): ?>
              <tr>
                <td colspan="9" style="text-align: center; padding: 20px;">No hay reservaciones registradas en el sistema.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>

        <div class="table-footer">
          <span class="note-legend"><i class="fa-regular fa-note-sticky"></i> Notas y solicitudes especiales accesibles en cualquier dispositivo</span>
          <span class="pagination-info"><?php echo count($reservaciones); ?> reservaciones</span>
        </div>
      </div>

      <div class="stats-grid">
        <div class="stat-card border-green">
          <span class="stat-number"><?php echo (int) $stats['libres']; ?></span>
          <span class="stat-label">Libres</span>
        </div>
        <div class="stat-card border-red">
          <span class="stat-number"><?php echo (int) $stats['ocupadas']; ?></span>
          <span class="stat-label">Ocupadas</span>
        </div>
        <div class="stat-card border-yellow">
          <span class="stat-number"><?php echo (int) $stats['sucias']; ?></span>
          <span class="stat-label">Sucias</span>
        </div>
        <div class="stat-card border-gray">
          <span class="stat-number"><?php echo (int) $stats['mantenimiento']; ?></span>
          <span class="stat-label">Mantenimiento</span>
        </div>
      </div>

    </section>

    <aside class="right-column">

      <!-- Buscador de Disponibilidad por Fechas para Recepción -->
      <div class="reservation-card-box" style="margin-bottom: 25px;">
        <div class="new-res-header">
          <h2>Consultar disponibilidad</h2>
          <span class="channel-badge" style="background: #eef2f7; color: #1e293b;">Fechas</span>
        </div>

        <form method="GET">
          <div class="form-row" style="display: flex; gap: 10px; margin-bottom: 12px;">
            <div class="input-group" style="flex: 1; margin-bottom: 0;">
              <label>Entrada</label>
              <input type="date" id="disp_entrada" name="disp_entrada" min="<?php echo htmlspecialchars($hoy, ENT_QUOTES, 'UTF-8'); ?>" max="<?php echo htmlspecialchars($max_fecha_str, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($busq_entrada, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="input-group" style="flex: 1; margin-bottom: 0;">
              <label>Salida</label>
              <input type="date" id="disp_salida" name="disp_salida" min="<?php echo htmlspecialchars($min_salida_busq, ENT_QUOTES, 'UTF-8'); ?>" max="<?php echo htmlspecialchars($max_fecha_str, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($busq_salida, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
          </div>
          <button type="submit" name="buscar_disponibilidad" value="1" class="btn-search" style="width: 100%; padding: 10px; background: #0f172a; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">
            <i class="fa-solid fa-magnifying-glass"></i> Verificar disponibilidad
          </button>
        </form>

        <?php if ($disp_busqueda_activa): ?>
          <div class="availability-results" style="margin-top: 15px; border-top: 1px solid #e2e8f0; padding-top: 12px;">
            <p style="font-size: 13px; color: #64748b; margin-bottom: 8px;">Disponibilidad del <b><?php echo fecha($busq_entrada); ?></b> al <b><?php echo fecha($busq_salida); ?></b>:</p>
            <?php foreach ($disp_resultado as $res_disp): ?>
              <div style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px dashed #f1f5f9; font-size: 13px;">
                <span><b><?php echo htmlspecialchars($res_disp['tipo_habitacion'], ENT_QUOTES, 'UTF-8'); ?></b><br><small style="color: #64748b;">$<?php echo number_format((float)$res_disp['precio_noche'], 2); ?> / noche</small></span>
                <span style="font-weight: 600; color: <?php echo $res_disp['disponibles_rango'] > 0 ? '#16a34a' : '#dc2626'; ?>;">
                  <?php echo (int)$res_disp['disponibles_rango']; ?> libres
                </span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Tarjeta de Nueva Reservación -->
      <div class="reservation-card-box">
        <div class="new-res-header">
          <h2>Nueva reservación</h2>
          <span class="channel-badge">Canal: Recepción</span>
        </div>

        <form id="newReservationForm" method="POST">
          <?php echo campoCsrf(); ?>
          <input type="hidden" name="accion" value="nueva_reservacion">

          <div class="input-group">
            <label>Nombre del huésped</label>
            <input type="text" name="nombre_huesped" maxlength="100"
                   title="Letras, partículas en minúscula (de, del, la), guiones o apóstrofos (ej. María de la Cruz, García-López, O’Neil)."
                   value="<?php echo htmlspecialchars($form['nombre_huesped'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nombre completo" required>
          </div>

          <div class="input-group">
            <label>Teléfono</label>
            <input type="tel" name="telefono" placeholder="10 dígitos" pattern="[0-9]{10}" maxlength="10" value="<?php echo htmlspecialchars($form['telefono'], ENT_QUOTES, 'UTF-8'); ?>" required>
          </div>

          <div class="input-group">
            <label>Correo (opcional)</label>
            <input type="email" name="correo" maxlength="80" value="<?php echo htmlspecialchars($form['correo'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Si ya tiene cuenta, se usa esa">
          </div>

          <div class="form-row">
            <div class="input-group">
              <label>Entrada</label>
              <input type="date" id="form_fecha_entrada" name="fecha_entrada" min="<?php echo htmlspecialchars($hoy, ENT_QUOTES, 'UTF-8'); ?>" max="<?php echo htmlspecialchars($max_fecha_str, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($form['fecha_entrada'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="input-group">
              <label>Salida</label>
              <input type="date" id="form_fecha_salida" name="fecha_salida" min="<?php echo htmlspecialchars($min_salida_form, ENT_QUOTES, 'UTF-8'); ?>" max="<?php echo htmlspecialchars($max_fecha_str, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($form['fecha_salida'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
          </div>

          <div class="input-group">
            <label>Tipo de habitación</label>
            <div class="select-wrapper">
              <select name="tipo_habitacion" required>
                <?php foreach ($tipos_habitacion as $tipo): ?>
                  <option value="<?php echo htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $tipo === $form['tipo_habitacion'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="input-group">
            <label>Notas (opcional)</label>
            <textarea name="notas" maxlength="255" placeholder="Solicitudes especiales..."><?php echo htmlspecialchars($form['notas'], ENT_QUOTES, 'UTF-8'); ?></textarea>
          </div>

          <button type="submit" class="btn-save-reservation">Guardar reservación</button>

          <p class="system-timestamp">Se guardan fecha, hora y tu usuario (<?php echo htmlspecialchars($nombre_usuario, ENT_QUOTES, 'UTF-8'); ?>).</p>
        </form>

      </div>
    </aside>

  </main>

  <!-- Modal para ver Notas y Solicitudes Especiales -->
  <div id="notaModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
    <div style="background: #fff; width: 90%; max-width: 450px; padding: 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
      <h3 id="modalNotaTitulo" style="margin-top: 0; color: #0f172a; font-size: 18px; margin-bottom: 12px;">Solicitudes especiales</h3>
      <p id="modalNotaContenido" style="color: #334155; font-size: 14px; background: #f8fafc; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0; line-height: 1.5; white-space: pre-wrap; margin-bottom: 20px;"></p>
      <div style="text-align: right;">
        <button type="button" onclick="cerrarNotaModal()" style="background: #0f172a; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 500;">Cerrar</button>
      </div>
    </div>
  </div>

  <script>
    function sumarDiasISO(fechaISO, dias) {
      const partes = fechaISO.split('-');
      if (partes.length !== 3) return null;
      const d = new Date(Date.UTC(Number(partes[0]), Number(partes[1]) - 1, Number(partes[2]) + dias));
      if (Number.isNaN(d.getTime())) return null;
      return d.toISOString().slice(0, 10);
    }

    function vincularRangoFechas(idEntrada, idSalida) {
      const inputEntrada = document.getElementById(idEntrada);
      const inputSalida  = document.getElementById(idSalida);
      if (!inputEntrada || !inputSalida) return;

      const actualizar = () => {
        if (!inputEntrada.value) return;
        const minSal = sumarDiasISO(inputEntrada.value, 1);
        const maxSalPorEstancia = sumarDiasISO(inputEntrada.value, <?php echo MAX_NOCHES_ESTANCIA; ?>);
        if (!minSal) return;

        inputSalida.min = minSal;
        const maxGlobal = <?php echo json_encode($max_fecha_str); ?>;
        const maxEfectivo = (maxSalPorEstancia && maxSalPorEstancia < maxGlobal) ? maxSalPorEstancia : maxGlobal;
        inputSalida.max = maxEfectivo;

        if (inputSalida.min > inputSalida.max) {
          inputSalida.min = inputSalida.max;
        }
        if (inputSalida.value && inputSalida.value < inputSalida.min) {
          inputSalida.value = inputSalida.min;
        }
        if (inputSalida.value && inputSalida.value > inputSalida.max) {
          inputSalida.value = inputSalida.max;
        }
      };

      actualizar();
      inputEntrada.addEventListener('change', actualizar);
    }

    vincularRangoFechas('disp_entrada', 'disp_salida');
    vincularRangoFechas('form_fecha_entrada', 'form_fecha_salida');

    function filtrarReservaciones() {
      const query = document.getElementById('searchInput').value.toLowerCase();
      document.querySelectorAll('#reservationsTableBody tr.res-row').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
      });
    }

    function filtrarPorEstado(estado, btn) {
      document.querySelectorAll('.filter-pills .pill').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      document.querySelectorAll('#reservationsTableBody tr.res-row').forEach(row => {
        row.style.display = (estado === 'todas' || row.dataset.estado === estado) ? '' : 'none';
      });
    }

    function confirmarCancelacion(form, codigo, montoPagado, penalizacion, politicaDesc) {
      let mensaje = `¿Estás seguro de cancelar la reservación ${codigo}?\n\nPolítica de Cancelación:\n- ${politicaDesc}`;
      if (penalizacion > 0) {
        mensaje += `\n\nPenalización aplicable: $${penalizacion.toFixed(2)} (primera noche).`;
      }
      if (montoPagado > 0) {
        const reembolso = Math.max(0, montoPagado - penalizacion);
        mensaje += `\n\nPagos registrados: $${montoPagado.toFixed(2)} · Devolución a registrar: -$${reembolso.toFixed(2)}.`;
      }
      return confirm(mensaje);
    }

    function mostrarNotaModal(codigo, nota) {
      document.getElementById('modalNotaTitulo').textContent = `Notas - Reservación ${codigo}`;
      document.getElementById('modalNotaContenido').textContent = nota;
      document.getElementById('notaModal').style.display = 'flex';
    }

    function cerrarNotaModal() {
      document.getElementById('notaModal').style.display = 'none';
    }
  </script>
</body>
</html>