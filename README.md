# Hotel Costa Azul

**Propósito.** Aplicación web para administrar las reservaciones, habitaciones, pagos, tarifas y políticas de cancelación del hotel Costa Azul, con acceso diferenciado para huésped, recepcionista, camarista y administrador.

**Tipo de aplicación.** Web (se usa desde el navegador; las pantallas de recepción y camarista se diseñaron también para celular).

**Equipo 10, grupo XA.** Bonilla Sevilla Alejandra Kaori y Contreras Rivera Leslie. Proyecto de Ingeniería de Software.

## Stack y versiones

| Elemento | Tecnología y versión |
|---|---|
| Frontend | HTML5, CSS3 y JavaScript (sin versión instalada); Chart.js (CDN) para la gráfica de reportes |
| Backend | PHP 8.2.12, sin frameworks ni gestor de dependencias |
| Acceso a datos | PDO con el controlador `pdo_mysql` y consultas preparadas |
| SGBD | MariaDB 10.4.32 (incluida en XAMPP; compatible con MySQL) |
| Servidor y entorno local | XAMPP 8.2.12 (panel de control 3.3.0): Apache 2.4.58 + MariaDB; phpMyAdmin 5.2.1 |
| Editor | Visual Studio Code 1.145.0 |
| Control de versiones | Git 2.54.0 y GitHub |

## Funcionalidad por rol

| Rol | Qué puede hacer |
|---|---|
| Huésped | Crear cuenta e iniciar sesión; consultar disponibilidad por fechas y reservar; ver todas sus reservaciones con su código de confirmación, estado, total, pagado y saldo (**Mis reservaciones**); pagar en línea reservaciones `Activa` o `En curso` (anticipo o liquidación, sin exceder el saldo); cancelar sus reservaciones `Activa` con la política vigente al reservar, escribiendo el código o eligiéndola de una lista, y confirmando en una ventana emergente. |
| Recepcionista | Ver todas las reservaciones (en línea y de recepción); buscar disponibilidad por fechas y tipo; crear reservaciones con notas o solicitudes especiales; registrar la entrada y la salida del huésped; cancelar reservaciones o marcarlas como no-show; actualizar limpieza y disponibilidad de habitaciones; registrar pagos y consultar saldo e historial. |
| Camarista | Cambiar el estado de limpieza de las habitaciones (Limpia, Sucia, Mantenimiento). |
| Administrador | Consultar reportes de ingresos y ocupación (este mes, mes anterior o rango de fechas); gestionar tarifas y políticas de cancelación con historial; gestionar usuarios. |

Reglas del sistema:

- **Sin traslapes (RF-12, RN-03).** Una habitación no se reserva dos veces en fechas que se cruzan; el día de salida queda libre para la siguiente entrada. La reserva y la validación van en una transacción que bloquea las habitaciones del tipo.
- **Validación de captura.** La entrada no puede ser anterior a hoy, la salida debe ser posterior a la entrada, se admite una anticipación máxima de 365 días y una estancia máxima de 90 noches. El teléfono debe tener 10 dígitos y los nombres se validan por formato (se aceptan partículas como "de" y "del", guiones y apóstrofos).
- **Contraseñas.** Mínimo 8 caracteres y máximo 72, sin espacios (se valida en el navegador y en el servidor).
- **Estados de la reservación.** `Activa` → `En curso` (al registrar la entrada, solo el día de la entrada y con la habitación Limpia) → `Finalizada` (al registrar la salida, solo con saldo en $0.00). Una reservación `Activa` puede pasar a `Cancelada`. Los estados están restringidos también en la BD con un `CHECK`.
- **Cancelación.** Solo se puede cancelar una reservación `Activa` cuya entrada aún no haya llegado. Se aplica la política que la reservación tenía al reservar: cancelación gratuita hasta 72 horas antes de la entrada; después se cobra la primera noche (si la política no cobra primera noche, fuera de plazo la cancelación se bloquea y la pantalla muestra el motivo). Si la entrada ya pasó y el huésped no llegó, recepción la marca como no-show y se cobra la primera noche sin importar la política. La liquidación se calcula en el servidor, dentro de una transacción, con lo que haya pagado el huésped en ese momento:
  - **Lo retenido** (hasta el valor de la primera noche) no genera una fila nueva: es lo que queda neto en la tabla `pago` tras la devolución.
  - **Lo pagado de más** se registra como una fila negativa de tipo `Devolución`.
  - **Lo que la penalización exceda de lo pagado** no se inserta como pago: queda anotado en las notas de la reservación como "pendiente de cobro".
  - Al terminar, la pantalla muestra un aviso de una sola vez (guardado en la sesión, no en la URL) con la penalización y la devolución aplicadas.
- **Pagos.** Ningún pago puede superar el saldo pendiente. Una liquidación cubre todo el saldo; un anticipo, solo una parte. Los pagos no se editan ni se eliminan. Al liquidar, la pantalla muestra "Reservación liquidada" y saldo $0.00.
- **Trazabilidad (RNF-03).** Cada reservación guarda el canal de origen (`En línea` o `Recepción`) y, si la creó recepción, quién (`id_recepcionista`). Al cancelar o modificar se guarda quién y cuándo (`modificado_por`, `fecha_modificacion`). Las reservas en línea no llevan recepcionista porque no interviene ninguna.
- **Tarifas y políticas.** Una nueva tarifa o política deja la anterior como histórica; las reservaciones conservan la que tenían al reservar (`id_tarifa`, `id_politica`).
- **Habitaciones.** Una habitación con una reservación activa o en curso hoy no puede ponerse en Mantenimiento, y una habitación en Mantenimiento no queda disponible.

## Instalación y ejecución (Windows con XAMPP)

No hay dependencias que instalar: no se usan frameworks ni Composer.

1. Inicia **Apache** y **MySQL** desde el panel de control de XAMPP.
2. Clona el repositorio dentro de `htdocs`:
   ```bash
   cd C:\xampp\htdocs
   git clone https://github.com/Less-122/Hotel-Costa-Azul.git
   ```
   Queda en `C:\xampp\htdocs\Hotel-Costa-Azul`. Esta es la carpeta que Apache sirve: si guardas otra copia del proyecto en otro lugar (por ejemplo, el zip descomprimido), los cambios que hagas ahí no se reflejan en el navegador hasta que los copies a `htdocs`.
3. Crea la BD: abre <http://localhost/phpmyadmin/>, entra a la pestaña **Importar** (sin seleccionar ninguna base de datos) y carga `database/esquemaBD.sql`. El script elimina la base `hotel_costa_azul` si existe, la vuelve a crear con cotejamiento `utf8mb4_general_ci`, crea las tablas con sus PK, FK, únicos y `CHECK`, y carga los datos ficticios. **Borra los datos que tuviera esa base.** Si es la primera vez y phpMyAdmin marca error porque la base no existe, créala vacía con el nombre `hotel_costa_azul` y vuelve a importar.
4. Configuración (opcional): `config/conexion.php` usa `127.0.0.1`, puerto `3306`, BD `hotel_costa_azul`, usuario `root` y contraseña vacía (valores de XAMPP). Si tu MySQL usa otros, puedes definir las variables de entorno `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` y `DB_PASS`, o editar los valores por defecto en ese archivo. El proyecto no incluye un archivo `.env`.
5. Abre <http://localhost/Hotel-Costa-Azul/Principal/index.php>.

Para restablecer los datos de prueba: vuelve a importar `database/esquemaBD.sql` desde phpMyAdmin.

### Accesos

- Huéspedes: inician sesión en `Huesped/Login_regis/login.php` (la cuenta se crea en `regis.php`).
- Personal (administrador, recepcionista y camarista): `personal_login/login_personal.php`. El sistema envía a cada persona a su panel según su rol.

| Rol | Pantallas |
|---|---|
| Huésped | `Huesped/Reserva/Reser.php`, `Huesped/MisReservas/misreservas.php`, `Huesped/Pagar/pagar.php`, `Huesped/Cancelar/Cance.php` |
| Recepcionista | `Hotel_Recep/Recepcion/recep.php`, `Hotel_Recep/Pagos/pagos.php`, `Hotel_Recep/Habitaciones/hab.php` |
| Camarista | `Camarista/Cama.php` |
| Administrador | `Administrador/Tarifas/tari.php`, `Administrador/Politicas/poli.php`, `Administrador/Usuarios/usuarios.php`, `Administrador/Reportes/repo.php` |

### Usuarios de demostración

| Rol | Correo | Contraseña |
|---|---|---|
| Admin | admin@costaazul.test | 123 |
| Recepcionista | recep1@costaazul.test | 123 |
| Camarista | camarista@costaazul.test | 123 |
| Huésped | huesped.a@correo.test | 123 |
| Admin (alterno) | admin@gmail.com | admin123 |
| Recepcionista (alterno) | recepcionista1@gmail.com | recep123 |
| Camarista (alterno) | camarista@gmail.com | camar123 |

Los datos son ficticios; no incluyen datos personales reales. Las tarifas vigentes son Sencilla $850.00 y Doble $1,400.00 por noche, y la política vigente es cancelación gratuita hasta 72 horas antes (después se cobra la primera noche).

## Cómo comprobar la conexión con la BD

**Reservar y pagar.** Inicia sesión como `huesped.a@correo.test`, abre **Reservar**, elige fechas futuras y un tipo de habitación y confirma. La aplicación consulta la tarifa y la habitación disponibles en la BD y registra la reservación: se puede ver la fila nueva en la tabla `reservacion` desde phpMyAdmin. Después, en **Pagar**, registra un anticipo con el código obtenido (para la tarjeta usa un número ficticio válido, por ejemplo `4242 4242 4242 4242`, un vencimiento futuro y cualquier CVV de 3 dígitos) y revisa la tabla `pago`. Al liquidar el saldo, la pantalla muestra "Pago procesado correctamente" y "Reservación liquidada".

**Cancelar.** Abre **Mis reservaciones** y pulsa **Cancelar** en una reservación `Activa` (o entra a **Cancelar reservación** y elige una de la lista, o escribe el código). La URL debe llevar el código, por ejemplo `Cance.php?codigo=BU8TP`, y la pantalla muestra el detalle, la política aplicable y la devolución que se va a registrar. Pulsa **Cancelar reservación**: se abre la ventana de confirmación; **No, volver** la cierra y **Sí, cancelar** envía la cancelación. Después, la reservación aparece como `Cancelada` en `reservacion` y, si el huésped había pagado, se ve la fila `Devolución` (monto negativo) en `pago`.

## Estructura del proyecto

```
Hotel-Costa-Azul/
├── Administrador/        Politicas/, Reportes/, Tarifas/, Usuarios/ (.php y .css)
├── Camarista/            Cama.php, Cama.css
├── Hotel_Recep/          Habitaciones/, Pagos/, Recepcion/ (módulo de recepción)
├── Huesped/              Cancelar/, Login_regis/, MisReservas/, Pagar/, Reserva/
├── personal_login/       Inicio de sesión del personal
├── Principal/            Pantalla pública del hotel
├── config/
│   ├── conexion.php      Conexión PDO, zona horaria (America/Mexico_City) y medición del tiempo de respuesta
│   ├── sesion.php        Sesión, roles (verificarAcceso), tokens CSRF y bloqueo por intentos fallidos de inicio de sesión
│   ├── reservaciones.php Lógica compartida: fechas, costo, política de cancelación, cancelación y no-show, avisos flash y listado de reservaciones del huésped
│   └── logout.php        Cierre de sesión
├── database/
│   └── esquemaBD.sql     Tablas, llaves, restricciones CHECK y datos ficticios
└── README.md
```

## Seguridad

- Consultas preparadas con PDO contra inyección SQL, y salida escapada con `htmlspecialchars` contra XSS.
- Contraseñas guardadas con `password_hash` y verificadas con `password_verify`. El hash se genera en el servidor al registrar la cuenta; a la BD nunca llega el texto original.
- Acceso por rol centralizado en `config/sesion.php` (`verificarAcceso`): cada rol solo entra a sus pantallas, y quien escribe la URL de otro rol es redirigido a su propia página. La cuenta creada en *Crear cuenta* siempre es de rol Huésped. Las pantallas autenticadas se envían con `Cache-Control: no-store`, para que el botón "Atrás" no muestre un panel tras cerrar sesión.
- La sesión se vuelve a validar contra la BD cada pocos segundos: si el usuario se elimina o se desactiva, pierde el acceso sin esperar a cerrar sesión.
- Límite de intentos de inicio de sesión: después de 5 intentos fallidos con el mismo correo (por portal e IP) se bloquea el acceso durante 15 minutos.
- Tokens CSRF en los formularios y en las peticiones AJAX de las pantallas autenticadas, incluida la confirmación de cancelación.
- Un huésped solo puede ver, pagar y cancelar sus propias reservaciones (se filtra por el usuario de la sesión, también dentro de la transacción de cancelación).
- La cancelación se recalcula en el servidor con los datos de la BD en el momento de confirmar (estado, fechas, política y pagos); lo que muestre la pantalla no determina el monto.
- Los datos de la tarjeta se validan en el servidor (algoritmo de Luhn, vencimiento y CVV) y no se almacenan.
- Cierre de sesión en `config/logout.php`, que destruye la sesión y la cookie.
- Si falla la conexión con la BD, el usuario ve un mensaje genérico y el detalle técnico se escribe en el log del servidor.

## Correcciones recientes

- **Cancelación del huésped (`Cance.php`).** La ventana de confirmación podía verse abierta sin tener una reservación cargada, y sus botones no hacían nada (el envío iba sin código). Ahora la ventana solo se genera cuando hay una reservación cancelable, el formulario lleva el código en su `action`, el CSS oculta el modal salvo que tenga la clase `is-open` y el enlace a `Cance.css` incluye la fecha de modificación del archivo para evitar que el navegador use una versión vieja.
- **Mis reservaciones.** Nueva pantalla del huésped con el código de confirmación y accesos directos a pagar y cancelar.
- **Libro de pagos al cancelar.** Solo se inserta la `Devolución`; la penalización retenida ya no se guarda como una fila aparte, y la parte sin cubrir se anota en las notas de la reservación.
- **Pago en línea.** Acepta reservaciones `Activa` y `En curso`, igual que recepción.
- **Si algo no se ve como debe** tras copiar archivos nuevos: confirma que están en `C:\xampp\htdocs\Hotel-Costa-Azul` y recarga con Ctrl+F5.

## Limitaciones conocidas y mejoras posibles

- La pasarela de pago en línea es **simulada**: valida el formato de la tarjeta, pero no se conecta a un procesador real.
- No se envía notificación por correo de la reservación ni de la cancelación.
- RNF-06 (respuesta de 3 segundos): el servidor mide cada petición (cabeceras `X-Response-Time` y `X-SLA-3s-Status`) y registra en el log las que pasan de 3 segundos; las pantallas de pagos, reportes y cancelación también lo registran en la consola del navegador. Falta un informe formal de mediciones.
- La penalización que excede lo pagado no se cobra en el momento: queda como "pendiente de cobro" en las notas de la reservación, sin una fila en `pago`, por lo que no aparece en los reportes de ingresos ni en el saldo.
- Las filas antiguas de tipo `Penalización` con canal `Retención` (de versiones previas) siguen existiendo en la BD si se conservaron datos; los reportes las excluyen para no duplicar ingresos.
- El plazo y el cargo de la política de cancelación se leen del texto de su descripción (por ejemplo "72 horas", "3 días" o "primera noche"); si no se puede interpretar, se usa 72 horas por defecto. No hay columnas propias para estos datos.
- `esquemaBD.sql` comienza con `DROP DATABASE hotel_costa_azul;`, que falla si la base aún no existe; usar `DROP DATABASE IF EXISTS` evitaría el paso manual de crearla.
- La conexión usa por defecto el usuario `root` sin contraseña (solo para desarrollo). El bloqueo por intentos fallidos se guarda en archivos temporales del servidor, no en la BD.
- La disponibilidad de un día depende de las reservaciones activas o en curso por fecha; `disponible` en `habitacion` refleja la ocupación actual que marca recepción.
- Mejoras posibles: cargar la configuración desde un archivo `.env` fuera del repositorio, guardar el plazo de la política en columnas propias y agregar la notificación por correo.

## Responsables

| Caso de uso | Prioridad | Responsable |
|---|---|---|
| CU-01 Gestionar reservación | Alta | Kaori |
| CU-02 Registrar solicitudes especiales | Alta | Kaori |
| CU-03 Notificar reservación | Alta | Kaori |
| CU-04 Gestionar pagos | Alta | Leslie |
| CU-05 Consultar saldo e historial de pagos | Alta | Leslie |
| CU-06 Actualizar inventario de habitaciones | Alta | Leslie |
| CU-07 Actualizar estado de limpieza | Alta | Leslie |
| CU-08 Consultar estado de habitaciones | Media | Leslie con Kaori |
| CU-09 Gestionar tarifas y políticas de cancelación | Media | Kaori |
| CU-10 Consultar reportes de ingresos y ocupación | Media | Kaori con Leslie |
| Autenticación y permisos por rol (soporte, RNF-09) | Alta | Kaori |

## Acuerdo de integración

- Cada integrante desarrolla su caso de uso en una rama y lo integra a `main` por pull request; la otra integrante lo revisa antes de fusionar.
- Tablas y campos en minúsculas con guion bajo. La conexión se reutiliza siempre desde `config/conexion.php`.
- Cualquier cambio a la BD se acuerda entre ambas y se hace en `database/esquemaBD.sql`, nunca solo en phpMyAdmin.
- `git pull` al iniciar cada sesión y `git push` al terminar. Antes de fusionar, se recrea la BD con el script y se comprueba que la aplicación ejecuta en XAMPP.
- Los conflictos se resuelven en la rama local, sin push forzado.

## Referencias de evidencias

| Evidencia | Dónde |
|---|---|
| Diagrama de las seis tablas con PK y FK | Figura 1 del documento U3-E1 |
| Script de la BD y datos ficticios | `database/esquemaBD.sql` |
| Conexión PDO | `config/conexion.php` |
| Operación de la aplicación sobre la BD | Reservación desde `Huesped/Reserva/Reser.php` |