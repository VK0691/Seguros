<?php

error_reporting(0);
ini_set('display_errors', 0);
session_start();

include '../conexion.php';

$resultado = $conn->query("SELECT * FROM usuarios");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Lista de Usuarios</title>
      <link rel="stylesheet" href="estiloadmin.css" />

    <style>
        .mensaje-exito {
            background-color: #4CAF50;
            color: white;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            text-align: center;
            width: 50%;
            margin: 10px auto;
        }
    </style>
    <script>
        window.onload = function() {
            const mensaje = document.getElementById('mensaje-exito');
            if (mensaje) {
                setTimeout(() => {
                    mensaje.style.display = 'none';
                }, 3000);
            }
        }
    </script>
</head>

<body>
    <?php if (isset($_GET['mensaje']) && $_GET['mensaje'] === 'eliminado'): ?>
    <div id="mensaje-exito" class="mensaje-exito">
        ✅ Usuario eliminado correctamente.
    </div>
<?php endif; ?>

    <h2>Lista de Usuarios</h2>

    <?php if (isset($_GET['mensaje']) && $_GET['mensaje'] === 'actualizado'): ?>
        <div id="mensaje-exito" class="mensaje-exito">
            ✅ Usuario actualizado correctamente.
        </div>
    <?php endif; ?>

    <table border="1" width="100%">
        <thead>
            <tr>
                <th>Usuario</th>
                <th>Teléfono</th>
                <th>Dirección</th>
                <th>Correo</th>
                <th>Rol</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($fila = $resultado->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($fila['usuario']); ?></td>
                    <td><?php echo htmlspecialchars($fila['telefono']); ?></td>
                    <td><?php echo htmlspecialchars($fila['direccion']); ?></td>
                    <td><?php echo htmlspecialchars($fila['correo']); ?></td>
                    <td><?php echo htmlspecialchars($fila['rol']); ?></td>
                    <td><?php echo $fila['estado'] ? 'Activo' : 'Inactivo'; ?></td>
                    <td>
                        <a href="editar_usuario.php?id=<?php echo $fila['id']; ?>">Editar</a>
                        <button onclick="mostrarModal(<?php echo $fila['id']; ?>)">Eliminar</button>

                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <div id="modal-eliminar" style="display:none; position:fixed; top:30%; left:50%; transform:translate(-50%, -50%); background:#fff; border:1px solid #ccc; padding:20px; box-shadow:0 0 10px rgba(0,0,0,0.3); z-index:9999;">
    <p>¿Estás seguro de que quieres eliminar este usuario?</p>
    <button id="confirmar-eliminar">Sí, eliminar</button>
    <button onclick="cerrarModal()">Cancelar</button>
</div>
<script>
    let idUsuarioEliminar = null;

    function mostrarModal(id) {
        idUsuarioEliminar = id;
        document.getElementById('modal-eliminar').style.display = 'block';
    }

    function cerrarModal() {
        document.getElementById('modal-eliminar').style.display = 'none';
        idUsuarioEliminar = null;
    }

    document.getElementById('confirmar-eliminar').addEventListener('click', function () {
        if (idUsuarioEliminar !== null) {
            window.location.href = "eliminar_usuario.php?id=" + idUsuarioEliminar;
        }
    });
</script>
<style>
    .mensaje-exito {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background-color: #4CAF50; /* verde */
        color: white;
        padding: 15px 30px;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0,0,0,0.3);
        z-index: 10000;
        font-size: 18px;
        text-align: center;
    }
</style>

<script>
    setTimeout(function () {
        const mensaje = document.getElementById('mensaje-exito');
        if (mensaje) {
            mensaje.style.display = 'none';
        }
    }, 3000);
</script>

</body>

</html>
<!-- Al principio o al final de la página lista_usuarios.php -->
<a href="../Administrador/adminpanel.php" style="display:inline-block; margin:10px 0; padding:8px 15px; background-color:#007BFF; color:#fff; text-decoration:none; border-radius:4px;">Regresar</a>
