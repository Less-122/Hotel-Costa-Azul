<?php
require_once __DIR__ . '/sesion.php';

cerrarSesionCompleta();

header('Location: ' . urlBase() . 'Principal/index.php');
exit;