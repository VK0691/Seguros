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
                        header("Location: ../Seguros/Administrador/adminpanel.php");
                    } elseif ($row['rol'] == 'Cliente') {
                        header("Location: ../Seguros/Cliente/clientedash.php");
                    } elseif ($row['rol'] == 'Agente') {
                        header("Location: ../Seguros/Agente/panel_agente.php");
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
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - Seguro de Salud</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="estiloslogin.css">
</head>
<body style="margin: 0; font-family: Arial, sans-serif;">

<div class="container-fluid vh-100 d-flex flex-column flex-lg-row p-0">
  <!-- Lado Izquierdo -->
<div class="text-center d-flex align-items-center justify-content-center col-lg-4 py-4" style="background-color: #062D49;">
  <img src="img/usuario.jpg" alt="Icono Usuario" class="img-fluid" style="max-height: 300px;">
</div>

  <!-- Lado Derecho -->
  <div class="col-lg-8 d-flex align-items-center justify-content-center p-4">
    <div class="form-box p-4 shadow-lg bg-white rounded-4" style="max-width: 600px; width: 100%;">
      <h2 class="text-center mb-4">Seguro de Salud</h2>

      <form method="POST" action="login.php" class="needs-validation" novalidate>
        <div class="mb-3">
          <input type="text" class="form-control" name="correo" placeholder="Correo o usuario" required>
        </div>
        <div class="mb-2">
          <input id="contrasena" type="password" name="contrasena" maxlength="15" class="form-control" placeholder="Contraseña" required>
        </div>
        <small id="mensajeContrasena" class="text-danger" style="display: none;">Permitido máximo 15 caracteres.</small>

        <?php if (!empty($error)) {
          echo '<p class="text-danger mt-3">' . htmlspecialchars($error) . '</p>';
        } ?>

        <div class="d-grid mt-3">
          <button type="submit" class="btn btn-outline-dark rounded-pill fw-bold">Iniciar Sesión</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


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
