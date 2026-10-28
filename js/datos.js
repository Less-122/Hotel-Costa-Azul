/* =====================================================================
   Hotel Costa Azul · js/datos.js
   Capa de datos COMPARTIDA por todas las pantallas (localStorage).

   - Usa los MISMOS nombres de tablas y campos del modelo de BD (U2-E2):
     USUARIO, HABITACION, RESERVACION, PAGO, TARIFA, POLITICA_CANCELACION.
   - Cuando exista MySQL, cada función de DB.* se reemplaza por una
     llamada al backend; las pantallas no deberían tener que cambiar.
   - Cada función indica el RF / RNF / RN que sustenta.
   - La interfaz NUNCA escribe en localStorage directamente: siempre
     pasa por DB.* (respeta la arquitectura en capas del documento 4+1).
   ===================================================================== */
(function (global) {
  'use strict';

  /* Fecha de la demostración (lo que se muestra como "Hoy" en el sistema). */
  const FECHA_DEMO = '2026-11-12';

  const PREFIJO = 'hca_';
  const VERSION_DATOS = '3'; // súbela para forzar que se vuelvan a cargar los datos iniciales

  /* ---------- Raíz del sitio (para redirecciones con rutas relativas) ---------- */
  const RAIZ = (function () {
    try {
      const src = document.currentScript && document.currentScript.src;
      if (src) return new URL('../', src).href; // .../js/datos.js -> .../
    } catch (e) { /* sin efecto */ }
    return '/';
  })();

  /* ---------- Almacenamiento seguro (puede fallar en modo privado) ---------- */
  const memoria = {};
  function leer(clave) {
    try {
      const v = localStorage.getItem(PREFIJO + clave);
      return v === null ? null : JSON.parse(v);
    } catch (e) {
      return memoria[clave] === undefined ? null : JSON.parse(memoria[clave]);
    }
  }
  function escribir(clave, valor) {
    const texto = JSON.stringify(valor);
    try { localStorage.setItem(PREFIJO + clave, texto); }
    catch (e) { memoria[clave] = texto; }
  }
  function leerSesion() {
    try { return JSON.parse(sessionStorage.getItem(PREFIJO + 'sesion')); }
    catch (e) { return memoria.sesion ? JSON.parse(memoria.sesion) : null; }
  }
  function escribirSesion(valor) {
    try {
      if (valor === null) sessionStorage.removeItem(PREFIJO + 'sesion');
      else sessionStorage.setItem(PREFIJO + 'sesion', JSON.stringify(valor));
    } catch (e) { memoria.sesion = valor === null ? null : JSON.stringify(valor); }
  }

  /* ---------- Utilidades de fecha y dinero ---------- */
  const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
  const MESES_LARGO = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

  function aUTC(iso) { const [a, m, d] = iso.split('-').map(Number); return Date.UTC(a, m - 1, d); }
  function aISO(ms) { return new Date(ms).toISOString().slice(0, 10); }
  function sumarDias(iso, n) { return aISO(aUTC(iso) + n * 86400000); }
  function noches(entrada, salida) { return Math.round((aUTC(salida) - aUTC(entrada)) / 86400000); }
  function fmtFecha(iso) { if (!iso) return '—'; const [a, m, d] = iso.split('-'); return `${Number(d)} ${MESES[m - 1]} ${a}`; }
  function fmtFechaLarga(iso) { const [a, m, d] = iso.split('-'); return `${Number(d)} de ${MESES_LARGO[m - 1]} de ${a}`; }
  function fmtFechaHora(texto) { if (!texto) return '—'; return `${fmtFecha(texto.slice(0, 10))}, ${texto.slice(11, 16)}`; }
  function fmtMoneda(n, decimales) {
    const d = decimales ? 2 : 0;
    return '$' + Number(n).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });
  }
  /* RNF-02 / RNF-08: marca de tiempo automática. La fecha es FECHA_DEMO; la hora, la real. */
  function ahora() {
    const h = new Date();
    const p = (x) => String(x).padStart(2, '0');
    return `${FECHA_DEMO} ${p(h.getHours())}:${p(h.getMinutes())}:${p(h.getSeconds())}`;
  }
  /* Dos rangos [e1,s1) y [e2,s2) se traslapan si e1 < s2 y e2 < s1. */
  function traslapan(e1, s1, e2, s2) { return e1 < s2 && e2 < s1; }

  /* ---------- Datos iniciales (demostración) ---------- */
  function datosIniciales() {
    /* Contraseñas en claro SOLO por ser demo de localStorage; en MySQL serán hash (RNF-09). */
    const usuarios = [
      { id_usuario: 1, nombre: 'Sofía Reyes',       contacto: '7770000001', correo: 'admin01',      password: 'Costa2026', rol: 'Admin' },
      { id_usuario: 2, nombre: 'Marta Pérez',       contacto: '7770000002', correo: 'recepcion01',  password: 'Costa2026', rol: 'Recepcionista' },
      { id_usuario: 3, nombre: 'Elena R.',          contacto: '7770000003', correo: 'camarista01',  password: 'Costa2026', rol: 'Camarista' },
      { id_usuario: 4, nombre: 'Ana López Torres',  contacto: '7771234567', correo: null, password: null, rol: 'Huesped' },
      { id_usuario: 5, nombre: 'Luis Ortega',       contacto: '7772345678', correo: null, password: null, rol: 'Huesped' },
      { id_usuario: 6, nombre: 'María López Pérez', contacto: '7773456789', correo: null, password: null, rol: 'Huesped' },
      { id_usuario: 7, nombre: 'Carlos Mendoza',    contacto: '7774567890', correo: null, password: null, rol: 'Huesped' },
      { id_usuario: 8, nombre: 'Jorge Ramírez',     contacto: '7775678901', correo: null, password: null, rol: 'Huesped' }
    ];

    /* 10 habitaciones; el primer dígito es el piso. Solo tipos Sencilla y Doble. */
    const limpieza = { 101: 'Limpia', 102: 'Sucia', 103: 'Limpia', 104: 'Limpia', 105: 'Mantenimiento',
                       201: 'Limpia', 202: 'Sucia', 203: 'Limpia', 204: 'Sucia', 205: 'Limpia' };
    const habitaciones = Object.keys(limpieza).map((n) => ({
      numero_habitacion: n,
      tipo_habitacion: Number(n) < 200 ? 'Sencilla' : 'Doble',
      estado_limpieza: limpieza[n],
      disponible: n !== '105'
    }));

    const tarifas = [
      { id_tarifa: 1, tipo_habitacion: 'Sencilla', precio_noche: 800,  vigente: false, fecha_inicio: '2026-01-01', fecha_fin: '2026-06-30' },
      { id_tarifa: 2, tipo_habitacion: 'Doble',    precio_noche: 1100, vigente: false, fecha_inicio: '2026-01-01', fecha_fin: '2026-06-30' },
      { id_tarifa: 3, tipo_habitacion: 'Sencilla', precio_noche: 850,  vigente: true,  fecha_inicio: '2026-07-01', fecha_fin: null },
      { id_tarifa: 4, tipo_habitacion: 'Doble',    precio_noche: 1250, vigente: true,  fecha_inicio: '2026-07-01', fecha_fin: null }
    ];

    const politicas = [
      { id_politica: 1, descripcion: 'Cancelación gratuita hasta 24 horas antes de la fecha de entrada. Después, se cobra la primera noche.', vigente: false, fecha_inicio: '2026-01-01', fecha_fin: '2026-06-30' },
      { id_politica: 2, descripcion: 'Cancelación gratuita hasta 48 horas antes de la fecha de entrada. Después, se cobra la primera noche.', vigente: true,  fecha_inicio: '2026-07-01', fecha_fin: null }
    ];

    const R = (id, code, e, s, estado, notas, reg, canal, huesp, recep, hab, tarifa) => ({
      id_reservacion: id, numero_confirmacion: code, fecha_entrada: e, fecha_salida: s, estado: estado,
      notas: notas, fecha_registro: reg, canal: canal, id_huesped: huesp, id_recepcionista: recep,
      numero_habitacion: hab, id_tarifa: tarifa, id_politica: 2
    });
    const reservaciones = [
      R(1, 'AX7B9', '2026-11-12', '2026-11-14', 'Activa',    'Llegada tarde y cama extra.', '2026-11-05 10:12:00', 'En línea',  4, null, '101', 3),
      R(2, 'K3M2P', '2026-11-12', '2026-11-15', 'Activa',    '',                            '2026-11-06 16:40:00', 'Recepción', 5, 2,    '201', 4),
      R(3, 'R8T4Q', '2026-11-10', '2026-11-13', 'Activa',    'Alergia a las plumas.',       '2026-11-02 09:05:00', 'En línea',  6, null, '202', 4),
      R(4, 'B7N5X', '2026-11-12', '2026-11-14', 'Cancelada', '',                            '2026-11-04 12:30:00', 'Recepción', 7, 2,    '102', 3),
      R(5, 'H2D6W', '2026-11-20', '2026-11-22', 'Activa',    '',                            '2026-11-08 18:20:00', 'En línea',  8, null, '103', 3)
    ];

    const P = (id, monto, fh, tipo, canal, res, recep) => ({
      id_pago: id, monto: monto, fecha_hora: fh, tipo_pago: tipo, canal_pago: canal, id_reservacion: res, id_recepcionista: recep
    });
    const pagos = [
      P(1, 1000, '2026-11-05 10:15:00', 'Anticipo',    'Pasarela en línea',  1, null),
      P(2, 1000, '2026-11-06 16:45:00', 'Anticipo',    'Terminal recepción', 2, 2),
      P(3, 1250, '2026-11-02 09:10:00', 'Anticipo',    'Pasarela en línea',  3, null),
      P(4, 2500, '2026-11-10 14:00:00', 'Liquidación', 'Terminal recepción', 3, 2)
    ];

    return { usuarios, habitaciones, tarifas, politicas, reservaciones, pagos };
  }

  function sembrar() {
    const d = datosIniciales();
    Object.keys(d).forEach((k) => escribir(k, d[k]));
    escribir('version', VERSION_DATOS);
  }
  function asegurarDatos() {
    if (leer('version') !== VERSION_DATOS || !leer('habitaciones')) sembrar();
  }

  /* ---------- Acceso a tablas ---------- */
  const tabla = (n) => { asegurarDatos(); return leer(n) || []; };
  const siguienteId = (arr, campo) => arr.reduce((m, x) => Math.max(m, x[campo]), 0) + 1;

  const DB = {
    FECHA_DEMO, RAIZ,
    util: { noches, sumarDias, fmtFecha, fmtFechaLarga, fmtFechaHora, fmtMoneda, traslapan, ahora },

    /* Restaura los datos de demostración (útil para ensayar la exposición). */
    reiniciarDatos() { sembrar(); escribirSesion(null); },

    usuarios: () => tabla('usuarios'),
    habitaciones: () => tabla('habitaciones'),
    reservaciones: () => tabla('reservaciones'),
    pagos: () => tabla('pagos'),
    tarifas: () => tabla('tarifas'),
    politicas: () => tabla('politicas'),
    usuarioPorId(id) { return this.usuarios().find((u) => u.id_usuario === id) || null; },

    /* ===================== SESIÓN · RNF-09, RNF-03, RNF-08 ===================== */
    /* I-01 Inicio de sesión (solo personal). El huésped NO tiene cuenta: usa su código. */
    iniciarSesion(usuario, password) {
      const u = this.usuarios().find((x) =>
        x.rol !== 'Huesped' && x.correo && x.correo.toLowerCase() === String(usuario).trim().toLowerCase() && x.password === password);
      if (!u) return { ok: false, error: 'credenciales' };
      escribirSesion({ id_usuario: u.id_usuario, nombre: u.nombre, rol: u.rol });
      return { ok: true, usuario: u, destino: this.rutaPorRol(u.rol) };
    },
    sesion() { return leerSesion(); },
    cerrarSesion() { escribirSesion(null); global.location.href = RAIZ + 'Principal/index.html'; },
    rutaPorRol(rol) {
      return RAIZ + ({ Admin: 'Administrador/Reportes/repo.html', Recepcionista: 'Hotel_Recep/Recepcion/recep.html', Camarista: 'Camarista/Cama.html' }[rol] || 'Principal/index.html');
    },
    /* RNF-09: cada pantalla interna llama esto al cargar. Si no hay sesión del rol correcto, regresa al login. */
    requerirRol(roles) {
      const s = leerSesion();
      if (!s || roles.indexOf(s.rol) === -1) {
        global.location.replace(RAIZ + 'Login/login.html');
        return null;
      }
      return s;
    },

    /* ===================== TARIFAS Y POLÍTICAS · RF-14 ===================== */
    tarifaVigente(tipo) { return this.tarifas().find((t) => t.vigente && t.tipo_habitacion === tipo) || null; },
    politicaVigente() { return this.politicas().find((p) => p.vigente) || null; },

    /* RF-14 + historicidad: nunca se edita; se inserta una nueva y la anterior pasa a histórica. */
    crearTarifa(tipo, precio, desde) {
      if (!tipo || !(Number(precio) > 0) || !desde) return { ok: false, error: 'campos' };
      const ts = this.tarifas();
      const fin = sumarDias(desde, -1);
      ts.forEach((t) => { if (t.vigente && t.tipo_habitacion === tipo) { t.vigente = false; t.fecha_fin = fin < t.fecha_inicio ? t.fecha_inicio : fin; } });
      const nueva = { id_tarifa: siguienteId(ts, 'id_tarifa'), tipo_habitacion: tipo, precio_noche: Number(precio), vigente: true, fecha_inicio: desde, fecha_fin: null };
      ts.push(nueva); escribir('tarifas', ts);
      return { ok: true, tarifa: nueva };
    },
    crearPolitica(descripcion, desde) {
      if (!String(descripcion).trim() || !desde) return { ok: false, error: 'campos' };
      const ps = this.politicas();
      const fin = sumarDias(desde, -1);
      ps.forEach((p) => { if (p.vigente) { p.vigente = false; p.fecha_fin = fin < p.fecha_inicio ? p.fecha_inicio : fin; } });
      const nueva = { id_politica: siguienteId(ps, 'id_politica'), descripcion: String(descripcion).trim(), vigente: true, fecha_inicio: desde, fecha_fin: null };
      ps.push(nueva); escribir('politicas', ps);
      return { ok: true, politica: nueva };
    },

    /* ===================== HABITACIONES · RF-07, RF-08 ===================== */
    /* RF-07 (CU-07): la camarista actualiza el estado de limpieza. */
    actualizarLimpieza(numero, estado) {
      if (['Limpia', 'Sucia', 'Mantenimiento'].indexOf(estado) === -1) return { ok: false, error: 'estado' };
      const hs = this.habitaciones(); const h = hs.find((x) => x.numero_habitacion === String(numero));
      if (!h) return { ok: false, error: 'no_existe' };
      h.estado_limpieza = estado; escribir('habitaciones', hs);
      return { ok: true, habitacion: h };
    },
    /* RF-08 (CU-06): recepción actualiza si la habitación puede asignarse. */
    actualizarDisponible(numero, disponible) {
      const hs = this.habitaciones(); const h = hs.find((x) => x.numero_habitacion === String(numero));
      if (!h) return { ok: false, error: 'no_existe' };
      h.disponible = !!disponible; escribir('habitaciones', hs);
      return { ok: true, habitacion: h };
    },
    /* ¿Tiene reservación Activa que cubra la fecha? (ocupada "hoy") */
    reservacionActivaEn(numero, fecha) {
      return this.reservaciones().find((r) => r.estado === 'Activa' && r.numero_habitacion === numero && r.fecha_entrada <= fecha && fecha < r.fecha_salida) || null;
    },

    /* ===================== DISPONIBILIDAD · RF-04, RF-05 ===================== */
    /* La disponibilidad por fechas NO se guarda: se calcula con HABITACION.disponible y las reservaciones Activas que se traslapen. */
    habitacionesLibres(entrada, salida, tipo) {
      const activas = this.reservaciones().filter((r) => r.estado === 'Activa');
      return this.habitaciones().filter((h) =>
        h.disponible && (!tipo || h.tipo_habitacion === tipo) &&
        !activas.some((r) => r.numero_habitacion === h.numero_habitacion && traslapan(entrada, salida, r.fecha_entrada, r.fecha_salida)));
    },
    /* Resumen por tipo con su precio por noche vigente (lo que muestra I-02). */
    disponibilidadPorTipo(entrada, salida) {
      return ['Sencilla', 'Doble'].map((tipo) => {
        const libres = this.habitacionesLibres(entrada, salida, tipo);
        const t = this.tarifaVigente(tipo);
        return { tipo_habitacion: tipo, libres: libres.length, precio_noche: t ? t.precio_noche : null };
      });
    },
    /* Validación de fechas compartida por huésped y recepción. */
    validarFechas(entrada, salida) {
      if (!entrada || !salida) return 'campos';
      if (entrada < FECHA_DEMO) return 'pasado';
      if (salida <= entrada) return 'orden';
      return null;
    },

    /* ===================== RESERVACIONES · RF-01, RF-02, RF-06, RF-12 ===================== */
    _codigoUnico() {
      const letras = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sin O/0/I/1 para evitar confusiones al dictarlo
      const usados = this.reservaciones().map((r) => r.numero_confirmacion);
      let c;
      do { c = Array.from({ length: 5 }, () => letras[Math.floor(Math.random() * letras.length)]).join(''); } while (usados.indexOf(c) !== -1);
      return c; // RF-06
    },
    _huesped(nombre, telefono) {
      const us = this.usuarios();
      let u = us.find((x) => x.rol === 'Huesped' && x.contacto === telefono && x.nombre.toLowerCase() === nombre.toLowerCase());
      if (!u) {
        u = { id_usuario: siguienteId(us, 'id_usuario'), nombre: nombre, contacto: telefono, correo: null, password: null, rol: 'Huesped' };
        us.push(u); escribir('usuarios', us);
      }
      return u;
    },
    /* RF-01 (recepción) / RF-12 (en línea). RNF-01 valida campos; RNF-04/RN-03 re-verifica el traslape JUSTO antes de guardar.
       datos: { nombre, telefono, entrada, salida, tipo, notas, canal } */
    crearReservacion(datos) {
      const nombre = String(datos.nombre || '').trim();
      const telefono = String(datos.telefono || '').trim();
      const notas = String(datos.notas || '').trim();
      const canal = datos.canal === 'Recepción' ? 'Recepción' : 'En línea';

      if (nombre.length < 3 || !/^\d{10}$/.test(telefono) || !datos.tipo || !datos.entrada || !datos.salida) return { ok: false, error: 'campos' }; // RNF-01
      const errF = this.validarFechas(datos.entrada, datos.salida);
      if (errF) return { ok: false, error: errF };

      let idRecep = null;
      if (canal === 'Recepción') {
        const s = leerSesion();
        if (!s || s.rol !== 'Recepcionista') return { ok: false, error: 'sin_sesion' };
        idRecep = s.id_usuario; // RNF-03 / RN-02
      }

      const libres = this.habitacionesLibres(datos.entrada, datos.salida, datos.tipo); // RNF-04: se calcula en este instante
      if (!libres.length) return { ok: false, error: 'ocupada' };
      const hab = libres[0];

      const tarifa = this.tarifaVigente(hab.tipo_habitacion);
      const politica = this.politicaVigente();
      if (!tarifa || !politica) return { ok: false, error: 'sin_tarifa' };

      const huesped = this._huesped(nombre, telefono);
      const rs = this.reservaciones();
      const res = {
        id_reservacion: siguienteId(rs, 'id_reservacion'),
        numero_confirmacion: this._codigoUnico(),         // RF-06
        fecha_entrada: datos.entrada, fecha_salida: datos.salida,
        estado: 'Activa', notas: notas,                   // RF-02
        fecha_registro: ahora(),                          // RNF-02
        canal: canal, id_huesped: huesped.id_usuario,
        id_recepcionista: idRecep,                        // RNF-03 (null si es en línea)
        numero_habitacion: hab.numero_habitacion,
        id_tarifa: tarifa.id_tarifa, id_politica: politica.id_politica
      };
      rs.push(res); escribir('reservaciones', rs);
      return { ok: true, reservacion: res, total: this.totalReservacion(res) };
    },
    /* RF-02: añadir notas a una reservación existente. */
    actualizarNotas(idReservacion, notas) {
      const rs = this.reservaciones(); const r = rs.find((x) => x.id_reservacion === idReservacion);
      if (!r) return { ok: false, error: 'no_existe' };
      r.notas = String(notas || '').trim(); escribir('reservaciones', rs);
      return { ok: true, reservacion: r };
    },
    /* RF-13: localizar por código de confirmación (sin importar mayúsculas). */
    buscarPorConfirmacion(codigo) {
      const c = String(codigo || '').trim().toUpperCase();
      return this.reservaciones().find((r) => r.numero_confirmacion === c) || null;
    },
    /* RF-03: búsqueda del recepcionista por nombre del huésped o por código. */
    buscarReservaciones(texto, estado) {
      const t = String(texto || '').trim().toLowerCase();
      return this.reservaciones().filter((r) => {
        const h = this.usuarioPorId(r.id_huesped);
        const coincide = !t || r.numero_confirmacion.toLowerCase().indexOf(t) !== -1 || (h && h.nombre.toLowerCase().indexOf(t) !== -1);
        return coincide && (!estado || r.estado === estado);
      }).sort((a, b) => b.fecha_registro.localeCompare(a.fecha_registro));
    },
    /* RF-09 (recepción) / RF-13 (huésped): cambia el estado a Cancelada; no borra. La habitación queda libre
       porque la reservación deja de estar Activa (CU-06, A1). */
    cancelarReservacion(codigo) {
      const rs = this.reservaciones(); const c = String(codigo || '').trim().toUpperCase();
      const r = rs.find((x) => x.numero_confirmacion === c);
      if (!r) return { ok: false, error: 'no_existe' };
      if (r.estado !== 'Activa') return { ok: false, error: 'no_activa' };
      r.estado = 'Cancelada';
      const s = leerSesion();
      if (s && s.rol === 'Recepcionista') r.id_recepcionista = s.id_usuario; // RN-02: quién modifica
      escribir('reservaciones', rs);
      return { ok: true, reservacion: r };
    },
    politicaDe(reservacion) { return this.politicas().find((p) => p.id_politica === reservacion.id_politica) || null; },

    /* ===================== TOTAL, SALDO Y PAGOS · RF-10, RF-11, RF-16 ===================== */
    /* Total y saldo se CALCULAN, no se guardan (modelo E2). */
    totalReservacion(r) {
      const t = this.tarifas().find((x) => x.id_tarifa === r.id_tarifa);
      return noches(r.fecha_entrada, r.fecha_salida) * (t ? t.precio_noche : 0);
    },
    pagosDe(idReservacion) {
      return this.pagos().filter((p) => p.id_reservacion === idReservacion).sort((a, b) => a.fecha_hora.localeCompare(b.fecha_hora));
    },
    resumenFinanciero(r) {
      const total = this.totalReservacion(r);
      const pagado = this.pagosDe(r.id_reservacion).reduce((s, p) => s + p.monto, 0);
      return { total, pagado, saldo: Math.max(total - pagado, 0) };
    },
    /* RF-10 (terminal, recepcionista) y RF-16 (pasarela, huésped). RN-04: asociado a una reservación existente.
       RNF-07: no existe función para editar ni eliminar un pago. RNF-08: fecha, hora y recepcionista automáticos.
       datos: { codigo, monto, tipo_pago, canal_pago } */
    registrarPago(datos) {
      const r = this.buscarPorConfirmacion(datos.codigo);
      if (!r) return { ok: false, error: 'no_existe' };
      if (r.estado !== 'Activa') return { ok: false, error: 'no_activa' };
      const monto = Math.round(Number(datos.monto) * 100) / 100;
      if (!(monto > 0)) return { ok: false, error: 'monto' };
      if (['Anticipo', 'Liquidación'].indexOf(datos.tipo_pago) === -1) return { ok: false, error: 'tipo' };
      const fin = this.resumenFinanciero(r);
      if (monto > fin.saldo + 0.001) return { ok: false, error: 'excede' };
      if (datos.tipo_pago === 'Liquidación' && Math.abs(monto - fin.saldo) > 0.001) return { ok: false, error: 'liquidacion' };

      let idRecep = null;
      if (datos.canal_pago === 'Terminal recepción') {
        const s = leerSesion();
        if (!s || s.rol !== 'Recepcionista') return { ok: false, error: 'sin_sesion' };
        idRecep = s.id_usuario;
      } else if (datos.canal_pago !== 'Pasarela en línea') return { ok: false, error: 'canal' };

      const ps = this.pagos();
      const pago = { id_pago: siguienteId(ps, 'id_pago'), monto: monto, fecha_hora: ahora(), tipo_pago: datos.tipo_pago,
                     canal_pago: datos.canal_pago, id_reservacion: r.id_reservacion, id_recepcionista: idRecep };
      ps.push(pago); escribir('pagos', ps);
      return { ok: true, pago: pago, resumen: this.resumenFinanciero(r) };
    },

    /* ===================== REPORTES · RF-15 (se calculan al consultarlos, no se guardan) ===================== */
    reporte(desde, hasta) {
      const pagos = this.pagos().filter((p) => { const f = p.fecha_hora.slice(0, 10); return f >= desde && f <= hasta; });
      const suma = (arr) => arr.reduce((s, p) => s + p.monto, 0);
      const resEnPeriodo = this.reservaciones().filter((r) => r.fecha_entrada >= desde && r.fecha_entrada <= hasta);
      const hs = this.habitaciones();
      const ocupadasHoy = (tipo) => hs.filter((h) => (!tipo || h.tipo_habitacion === tipo) && this.reservacionActivaEn(h.numero_habitacion, FECHA_DEMO)).length;
      const porTipo = ['Sencilla', 'Doble'].map((t) => ({ tipo_habitacion: t, ocupadas: ocupadasHoy(t), total: hs.filter((h) => h.tipo_habitacion === t).length }));
      const semanas = [];
      for (let i = 0; i < 4; i++) {
        const ini = sumarDias(desde, i * 7); const fin = i === 3 ? hasta : sumarDias(desde, i * 7 + 6);
        semanas.push({ etiqueta: 'Semana ' + (i + 1), monto: suma(pagos.filter((p) => { const f = p.fecha_hora.slice(0, 10); return f >= ini && f <= fin; })) });
      }
      return {
        ingresos: suma(pagos),
        ocupacionHoy: hs.length ? Math.round(ocupadasHoy() * 100 / hs.length) : 0,
        reservacionesActivas: resEnPeriodo.filter((r) => r.estado === 'Activa').length,
        reservacionesCanceladas: resEnPeriodo.filter((r) => r.estado === 'Cancelada').length,
        porTipoPago: { Anticipo: suma(pagos.filter((p) => p.tipo_pago === 'Anticipo')), 'Liquidación': suma(pagos.filter((p) => p.tipo_pago === 'Liquidación')) },
        porCanal: { 'Pasarela en línea': suma(pagos.filter((p) => p.canal_pago === 'Pasarela en línea')), 'Terminal recepción': suma(pagos.filter((p) => p.canal_pago === 'Terminal recepción')) },
        semanas, ocupacionPorTipo: porTipo
      };
    }
  };

  asegurarDatos();
  global.DB = DB;
})(window);
