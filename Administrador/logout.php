<?php
session_start();
session_unset();    // Elimina todas las variables de sesión
session_destroy();  // Destruye la sesión
header("Location: ../login.php"); // O la ruta de tu página de login
exit();
?>
