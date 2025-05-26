<?php
session_start();
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $correo = trim($_POST['correo']);
    $contrasena = trim($_POST['contrasena']);

    $sql = "SELECT * FROM usuarios WHERE correo = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Error en la consulta: " . $conn->error);
    }

    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Verifica la contraseña con password_verify
        if (password_verify($contrasena, $row['contrasena'])) {

            if ($row['estado'] != 1) {
                echo "<script>
                    alert('Usuario inactivo. Contacta al administrador.');
                    window.location='index.html';
                </script>";
                exit();
            }

            $_SESSION['usuario'] = $row['correo'];
            $_SESSION['rol'] = $row['rol'];

            // Redirige según rol
            if ($row['rol'] == 'Administrador') {
                header("Location: adminpanel.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();

        } else {
            echo "<script>
                alert('Contraseña incorrecta');
                window.location='index.html';
            </script>";
        }
    } else {
        echo "<script>
            alert('Usuario no encontrado');
            window.location='index.html';
        </script>";
    }

    $stmt->close();
    $conn->close();
}
?>
