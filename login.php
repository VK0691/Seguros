<?php
session_start();
include 'conexion.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $correo = trim($_POST['correo']);
    $contrasena = trim($_POST['contrasena']);

    // Validación límite máximo 15 caracteres
    if (strlen($contrasena) > 15) {
        $error = "La contraseña no puede tener más de 15 caracteres.";
    } else {
        if (!$conn) {
            die("Error de conexión a la base de datos: " . mysqli_connect_error());
        }

        $sql = "SELECT * FROM usuarios WHERE correo = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("Error al preparar la consulta: " . $conn->error . "\nSQL: $sql");
        }

        $stmt->bind_param("s", $correo);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (password_verify($contrasena, $row['contrasena'])) {
                if ($row['estado'] != 1) {
                    $error = "Usuario inactivo. Contacta al administrador.";
                } else {
                    $_SESSION['usuario'] = $row['correo'];
                    $_SESSION['rol'] = $row['rol'];

                    if ($row['rol'] == 'Administrador') {
                        header("Location: ../Administrador/adminpanel.php");
                    } elseif ($row['rol'] == 'Cliente') {
                        header("Location: ../Cliente/panel_cliente.php");
                    } elseif ($row['rol'] == 'Agente') {
                        header("Location: ../Agente/panel_agente.php");
                    } else {
                        header("Location: login.php?error=rol_no_valido");
                    }
                    exit();
                }
            } else {
                $error = "Contraseña incorrecta.";
            }
        } else {
            $error = "Usuario no encontrado.";
        }

        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Login - Seguro de Salud</title>
  <link rel="stylesheet" href="estiloslogin.css">
</head>
<body>
<div class="container">
  <div class="left">
    <img src="img/usuario.jpg" alt="Icono Usuario">
  </div>
  <div class="right">
    <div class="form-box">
      <h2>Seguro de Salud</h2>

      <form method="POST" action="login.php">
        <input type="text" name="correo" placeholder="Correo o usuario" required>
        <input id="contrasena" type="password" name="contrasena" maxlength="15" placeholder="Contraseña" required>
        <small id="mensajeContrasena" style="color: red; display: none;">Permitido máximo 15 caracteres.</small>

        <?php if ($error != '') {
            echo '<p style="color:red; margin-top:10px;">' . htmlspecialchars($error) . '</p>';
        } ?>

        <button type="submit">Iniciar Sesión</button>
      </form>

    </div>
  </div>
</div>

<script>
  const inputContrasenaLogin = document.getElementById('contrasena');
  const mensajeLogin = document.getElementById('mensajeContrasena');

  inputContrasenaLogin.addEventListener('input', () => {
    if (inputContrasenaLogin.value.length >= 15) {
      mensajeLogin.style.display = 'inline';
    } else {
      mensajeLogin.style.display = 'none';
    }
  });

  // Evitar pegar texto mayor a 15 caracteres
  inputContrasenaLogin.addEventListener('paste', (e) => {
    e.preventDefault();
    const pasteData = (e.clipboardData || window.clipboardData).getData('text');
    const currentValue = inputContrasenaLogin.value;
    const allowedPaste = pasteData.slice(0, 15 - currentValue.length);
    inputContrasenaLogin.value = currentValue + allowedPaste;
    // Mostrar mensaje si se pega más de lo permitido
    if (inputContrasenaLogin.value.length >= 15) {
      mensajeLogin.style.display = 'inline';
    } else {
      mensajeLogin.style.display = 'none';
    }
  });
</script>


</body>
</html>
