<?php
session_start();
// Verificación de sesión y permisos

$id = intval($_GET['id']);
$numero_poliza = 'VL-' . date('Ymd') . '-' . rand(100, 999);
$monto = 100000; // Monto base o calcular según reglas

$conexion->query("UPDATE seguros_vida SET 
                 estado = 'Aprobado', 
                 numero_poliza = '$numero_poliza', 
                 monto_asegurado = $monto, 
                 fecha_aprobacion = NOW() 
                 WHERE id = $id");

header("Location: gestion_seguros_vida.php?success=aprobado");
exit();