<?php
/**
 * Lógica compartida de reservaciones: fechas, política de cancelación,
 * cancelación / no-show (con su asiento en el libro de pagos), avisos flash
 * y listado de reservaciones del huésped.
 *
 * Estados de reservación válidos (los mismos que el CHECK de la BD):
 *   Activa, En curso, Finalizada, Cancelada.
 * "Ocupada" NO es un estado de reservación: es solo cómo se muestra una
 * habitación con disponible = 0.
 *
 * Modelo del libro de pagos al cancelar:
 *   - Anticipo / Liquidación suman (> 0).
 *   - Devolución resta (< 0) y es lo único que se inserta al cancelar.
 *   - La penalización retenida NO se inserta como fila: es lo que queda
 *     neto en pago tras la devolución. La parte de penalización que no
 *     estaba pagada se anota en las notas como "pendiente de cobro".
 */

const ESTADOS_RESERVACION    = ['Activa', 'En curso', 'Finalizada', 'Cancelada'];
const ESTADOS_ABIERTOS       = ['Activa', 'En curso'];
const HORAS_POLITICA_DEFECTO = 72;

function fecha(string $f, bool $con_anio = false): string {
    $meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    $t = strtotime($f);
    if ($t === false) {
        return $f;
    }
    return date('d', $t) . ' ' . $meses[date('n', $t) - 1] . ($con_anio ? ' ' . date('Y', $t) : '');
}

/** Respaldo: extrae horas de anticipación del texto de la política. */
function obtenerHorasAnticipacion(string $descripcion, int $horas_defecto = HORAS_POLITICA_DEFECTO): int {
    $texto = trim($descripcion);
    if ($texto === '') {
        return $horas_defecto;
    }

    $palabrasNumero = [
        'un' => 1, 'una' => 1, 'uno' => 1, 'dos' => 2, 'tres' => 3, 'cuatro' => 4,
        'cinco' => 5, 'seis' => 6, 'siete' => 7, 'ocho' => 8, 'nueve' => 9,
        'diez' => 10, 'doce' => 12, 'quince' => 15, 'veinticuatro' => 24,
        'cuarenta y ocho' => 48, 'setenta y dos' => 72,
    ];

    if (preg_match('/(\d+)\s*(?:hora|horas|hr|hrs|h)\b/iu', $texto, $m)) {
        $horas = (int) $m[1];
        return $horas > 0 ? $horas : $horas_defecto;
    }
    if (preg_match('/(\d+)\s*(?:d[ií]a|d[ií]as)\b/iu', $texto, $m)) {
        $dias = (int) $m[1];
        return $dias > 0 ? $dias * 24 : $horas_defecto;
    }
    if (preg_match('/(\d+)\s*(?:semana|semanas)\b/iu', $texto, $m)) {
        $semanas = (int) $m[1];
        return $semanas > 0 ? $semanas * 24 * 7 : $horas_defecto;
    }

    $alternancia = implode('|', array_map(
        static fn(string $p): string => preg_quote($p, '/'),
        array_keys($palabrasNumero)
    ));

    if (preg_match('/\b(' . $alternancia . ')\s+(?:hora|horas|hr|hrs)\b/iu', $texto, $m)) {
        $clave = mb_strtolower(preg_replace('/\s+/', ' ', trim($m[1])), 'UTF-8');
        if (isset($palabrasNumero[$clave])) {
            return $palabrasNumero[$clave];
        }
    }
    if (preg_match('/\b(' . $alternancia . ')\s+(?:d[ií]a|d[ií]as)\b/iu', $texto, $m)) {
        $clave = mb_strtolower(preg_replace('/\s+/', ' ', trim($m[1])), 'UTF-8');
        if (isset($palabrasNumero[$clave])) {
            return $palabrasNumero[$clave] * 24;
        }
    }

    return $horas_defecto;
}

/** Respaldo: deduce del texto si la política cobra la primera noche. */
function politicaCobraPrimeraNoche(string $descripcion): bool {
    $texto = trim($descripcion);
    if ($texto === '') {
        return false;
    }
    if (preg_match('/\b(?:sin|no)\s+(?:se\s+)?(?:cobra|cobrar|aplica|aplicar)\b[^.;]*\b(?:primera|1ra|1a|1|una|un)\s+noche\b/iu', $texto)) {
        return false;
    }
    return (bool) preg_match('/\b(?:primera|1ra|1a|1|una|un)\s+noche\b/iu', $texto);
}

/**
 * Reglas de la política de una fila. Usa las columnas; solo si vienen NULL
 * recurre al texto.
 * Campos esperados: horas_anticipacion, cobra_primera_noche, politica_descripcion.
 */
function reglasPolitica(array $fila, string $descripcion_respaldo = ''): array {
    $desc = !empty($fila['politica_descripcion']) ? (string) $fila['politica_descripcion'] : $descripcion_respaldo;
    $horas = (isset($fila['horas_anticipacion']) && $fila['horas_anticipacion'] !== null)
        ? (int) $fila['horas_anticipacion']
        : obtenerHorasAnticipacion($desc);
    $cobra = (isset($fila['cobra_primera_noche']) && $fila['cobra_primera_noche'] !== null)
        ? (bool) (int) $fila['cobra_primera_noche']
        : politicaCobraPrimeraNoche($desc);
    return ['descripcion' => $desc, 'horas' => $horas, 'cobra' => $cobra];
}

function etiquetaPlazo(int $horas): string {
    return ($horas % 24 === 0 && $horas >= 24) ? ($horas / 24) . ' día(s)' : $horas . ' hora(s)';
}

function nochesEstancia(string $entrada, string $salida): int {
    return max(1, (int) (new DateTime($entrada))->diff(new DateTime($salida))->days);
}

function costoEstancia(float $precio_noche, string $entrada, string $salida): float {
    return round(nochesEstancia($entrada, $salida) * round($precio_noche, 2), 2);
}

/**
 * Decide si una reservación puede cancelarse y con qué penalización.
 * Función pura (no toca la BD) para poder probarla.
 *
 * $r: estado, fecha_entrada, precio_noche y los campos de reglasPolitica().
 * $noshow = true: la entrada ya pasó y el huésped no llegó; se cobra la
 * primera noche sin importar la política.
 */
function evaluarCancelacion(array $r, DateTime $ahora, bool $noshow = false): array {
    $reglas = reglasPolitica($r);
    $precio = round((float) $r['precio_noche'], 2);
    $out = [
        'puede' => false, 'motivo' => '', 'penalizacion' => 0.0,
        'fuera_plazo' => false, 'reglas' => $reglas,
    ];

    if ($r['estado'] !== 'Activa') {
        $out['motivo'] = 'La reservación ya se encuentra ' . mb_strtolower((string) $r['estado'], 'UTF-8') . '.';
        return $out;
    }

    if ($noshow) {
        if ($r['fecha_entrada'] >= $ahora->format('Y-m-d')) {
            $out['motivo'] = 'Solo se puede marcar no-show cuando la fecha de entrada ya pasó.';
            return $out;
        }
        $out['puede'] = true;
        $out['fuera_plazo'] = true;
        $out['penalizacion'] = $precio;
        return $out;
    }

    $entrada = new DateTime($r['fecha_entrada'] . ' 00:00:00');
    $segundos = $entrada->getTimestamp() - $ahora->getTimestamp();

    if ($segundos <= 0) {
        $out['motivo'] = 'No se puede cancelar: la fecha de entrada ya pasó o la reservación ya dio inicio.';
    } elseif (($segundos / 3600) < $reglas['horas']) {
        if ($reglas['cobra']) {
            $out['puede'] = true;
            $out['fuera_plazo'] = true;
            $out['penalizacion'] = $precio;
        } else {
            $out['motivo'] = 'No se puede cancelar: el plazo límite según la política ('
                           . etiquetaPlazo($reglas['horas']) . ' antes) ha expirado.';
        }
    } else {
        $out['puede'] = true;
    }
    return $out;
}

/** Reparte lo pagado entre penalización retenida y devolución. */
function liquidarCancelacion(float $pagado, float $penalizacion): array {
    $pagado = max(0.0, round($pagado, 2));
    $penalizacion = max(0.0, round($penalizacion, 2));
    return [
        'retenido'  => round(min($pagado, $penalizacion), 2),
        'reembolso' => round(max(0.0, $pagado - $penalizacion), 2),
        'pendiente' => round(max(0.0, $penalizacion - $pagado), 2),
    ];
}

/**
 * Cancela (o marca no-show) dentro de una transacción que bloquea la
 * reservación y recalcula todo con lo que hay en la BD en ese momento.
 *
 * $opts: id_actor (int, quién modifica), id_huesped (int|null, exige que la
 *        reservación sea de ese huésped), id_staff (int|null, se guarda en
 *        pago.id_recepcionista), canal ('Sistema'|'Recepción'), noshow (bool).
 * Lanza RuntimeException con mensaje para el usuario si no procede, y
 * PDOException si falla la BD (el llamador la registra en el log).
 */
function ejecutarCancelacion(PDO $c, int $id_reservacion, array $opts): array {
    $id_actor   = (int) ($opts['id_actor'] ?? 0);
    $id_huesped = $opts['id_huesped'] ?? null;
    $id_staff   = $opts['id_staff'] ?? null;
    $canal      = ($opts['canal'] ?? 'Sistema') === 'Recepción' ? 'Recepción' : 'Sistema';
    $noshow     = !empty($opts['noshow']);

    $c->beginTransaction();
    try {
        $st = $c->prepare("SELECT id_huesped FROM reservacion WHERE id_reservacion = ? FOR UPDATE");
        $st->execute([$id_reservacion]);
        $dueno = $st->fetchColumn();
        if ($dueno === false || ($id_huesped !== null && (int) $dueno !== (int) $id_huesped)) {
            throw new RuntimeException('La reservación no existe o no pertenece a tu cuenta.');
        }

        $st = $c->prepare(
            "SELECT r.estado, r.fecha_entrada, r.notas, t.precio_noche,
                    p.horas_anticipacion, p.cobra_primera_noche, p.descripcion AS politica_descripcion
             FROM reservacion r
             JOIN tarifa t ON t.id_tarifa = r.id_tarifa
             LEFT JOIN politica_cancelacion p ON p.id_politica = r.id_politica
             WHERE r.id_reservacion = ?"
        );
        $st->execute([$id_reservacion]);
        $r = $st->fetch(PDO::FETCH_ASSOC);

        $eval = evaluarCancelacion($r, new DateTime(), $noshow);
        if (!$eval['puede']) {
            throw new RuntimeException($eval['motivo']);
        }

        $st = $c->prepare("SELECT COALESCE(SUM(monto), 0) FROM pago WHERE id_reservacion = ?");
        $st->execute([$id_reservacion]);
        $pagado = round((float) $st->fetchColumn(), 2);

        $pen = $eval['penalizacion'];
        $liq = liquidarCancelacion($pagado, $pen);

        $notas = [];
        if ($pen > 0) {
            $notas[] = ($noshow ? '[No-show: penalización de $' : '[Penalización por cancelación tardía: $')
                     . number_format($pen, 2) . ' (1ra noche)]';
            if ($liq['retenido'] > 0) {
                $notas[] = '[Penalización retenida de los pagos: $' . number_format($liq['retenido'], 2) . ']';
            }
            if ($liq['pendiente'] > 0) {
                $notas[] = '[Penalización pendiente de cobro: $' . number_format($liq['pendiente'], 2) . ']';
            }
        }

        if ($liq['reembolso'] > 0) {
            $c->prepare(
                "INSERT INTO pago (monto, fecha_hora, tipo_pago, canal_pago, id_reservacion, id_recepcionista)
                 VALUES (?, NOW(), 'Devolución', ?, ?, ?)"
            )->execute([number_format(-$liq['reembolso'], 2, '.', ''), $canal, $id_reservacion, $id_staff]);
            $notas[] = '[Devolución registrada: -$' . number_format($liq['reembolso'], 2) . ']';
        }

        $notas_finales = trim(($r['notas'] ?? '') . (!empty($notas) ? "\n" . implode("\n", $notas) : ''));

        $st = $c->prepare(
            "UPDATE reservacion
             SET estado = 'Cancelada', notas = NULLIF(?, ''),
                 modificado_por = ?, fecha_modificacion = NOW()
             WHERE id_reservacion = ? AND estado = 'Activa'"
        );
        $st->execute([$notas_finales, $id_actor, $id_reservacion]);
        if ($st->rowCount() !== 1) {
            throw new RuntimeException('No se pudo completar la cancelación. Verifica el estado de la reservación.');
        }

        $c->commit();
        return ['penalizacion' => $pen] + $liq;
    } catch (Throwable $e) {
        if ($c->inTransaction()) {
            $c->rollBack();
        }
        throw $e;
    }
}

function mensajeCancelacion(array $res, bool $noshow = false): string {
    $m = $noshow ? 'Reservación marcada como no-show y cancelada.' : 'La reservación ha sido cancelada correctamente.';
    if ($res['penalizacion'] > 0) {
        $m .= ' Penalización aplicada: $' . number_format($res['penalizacion'], 2) . ' (primera noche).';
        if ($res['pendiente'] > 0) {
            $m .= ' Quedan $' . number_format($res['pendiente'], 2) . ' pendientes de cobro.';
        }
    }
    if ($res['reembolso'] > 0) {
        $m .= ' Se registró una devolución de -$' . number_format($res['reembolso'], 2) . '.';
    }
    return $m;
}

/** Aviso de una sola vez guardado en sesión (no viaja en la URL). */
function flashSet(string $tipo, string $mensaje): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['flash'] = ['tipo' => $tipo, 'msg' => $mensaje];
}

function flashGet(): ?array {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($f) ? $f : null;
}

/** Reservaciones de un huésped con total, pagado y saldo (abiertas primero). */
function reservacionesDelHuesped(PDO $c, int $id_huesped): array {
    $st = $c->prepare(
        "SELECT r.id_reservacion, r.numero_confirmacion, r.estado, r.fecha_entrada, r.fecha_salida,
                r.numero_habitacion, h.tipo_habitacion, t.precio_noche,
                COALESCE((SELECT SUM(monto) FROM pago WHERE id_reservacion = r.id_reservacion), 0) AS pagado
         FROM reservacion r
         JOIN tarifa t     ON t.id_tarifa = r.id_tarifa
         JOIN habitacion h ON h.numero_habitacion = r.numero_habitacion
         WHERE r.id_huesped = ?
         ORDER BY (r.estado IN ('Activa', 'En curso')) DESC, r.fecha_entrada DESC, r.id_reservacion DESC
         LIMIT 100"
    );
    $st->execute([$id_huesped]);
    $filas = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($filas as &$f) {
        $f['costo']  = costoEstancia((float) $f['precio_noche'], $f['fecha_entrada'], $f['fecha_salida']);
        $f['pagado'] = round((float) $f['pagado'], 2);
        $f['saldo']  = in_array($f['estado'], ESTADOS_ABIERTOS, true)
            ? max(0.0, round($f['costo'] - $f['pagado'], 2))
            : 0.0;
    }
    unset($f);
    return $filas;
}