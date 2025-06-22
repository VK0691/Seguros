<?php
error_reporting(0);
ini_set('display_errors', 0);
session_start();

if (!isset($_SESSION['session_regenerada'])) {
    session_regenerate_id(true);
    $_SESSION['session_regenerada'] = true;
}

if (!isset($_SESSION['usuario']) || !isset($_SESSION['rol'])) {
    header("Location: ../login.php");
    exit();
}

include '../conexion.php';

$resultado = $conn->query("SELECT * FROM usuarios");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Usuarios</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="/Administrador/estiloadmin.css">

    <style>
        body {
            background: linear-gradient(135deg, #ffffff 50%,  #002147 50%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #000;
        }

        h2 {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #002147;
        }

        .botones-acciones a {
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin-left: 10px;
            transition: all 0.3s ease;
        }

        .btn-solicitudes {
            background-color: #FFD700;
            color: #000;
        }

        .btn-solicitudes:hover {
            background-color: #e6c200;
        }

        .btn-regresar {
            background-color: #002147;
            color: #fff;
            border: 5px;
        }
        

        .btn-regresar:hover {
            background-color: #00112a;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
            background-color: white;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
            animation: fadeIn 0.5s ease-in-out;
        }

        th, td {
            padding: 12px;
            text-align: center;
        }

        th {
            background-color: #002147;
            color:rgb(255, 255, 255);
        }

        td {
            background-color:rgb(255, 255, 255);
        }

        td button {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 7px 12px;
            border-radius: 10px;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 10px;
        }
        
        td button:hover {
            background-color: #c82333;
        }

        /* ==========================
           Estilos para Botón Editar
           ========================== */
        .btn-editar {
            background-color:rgb(255, 217, 46) !important;
            color: black !important;
            border: none !important;
            padding: 7px 20px !important;
            border-radius: 10px !important;
            cursor: pointer !important;
            transition: background-color 0.3s !important;
            margin-top: 10px !important;
            text-decoration: none !important;
            display: inline-block !important;
        }
        .btn-editar:hover {
            background-color: #0056b3 !important;
        }

        /* Fin estilos botón editar */

        #modal-eliminar {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.3);
            z-index: 9999;
            animation: fadeInModal 0.4s ease-in-out;
        }

        #modal-eliminar button {
            margin: 10px;
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
        }

        #modal-eliminar #confirmar-eliminar {
            background-color: #dc3545;
            color: white;
        }

        #modal-eliminar button:hover {
            opacity: 0.9;
        }

        .mensaje-exito {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #4CAF50;
            color: white;
            padding: 15px 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.3);
            z-index: 10000;
            font-size: 18px;
            animation: slideIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeInModal {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }

        @keyframes slideIn {
            from { opacity: 0; top: 0; }
            to { opacity: 1; top: 20px; }
        }
    </style>
</head>
<body>
    <?php if (isset($_GET['mensaje']) && $_GET['mensaje'] === 'eliminado'): ?>
        <div id="mensaje-exito" class="mensaje-exito">
            ✅ Usuario eliminado correctamente.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['mensaje']) && $_GET['mensaje'] === 'actualizado'): ?>
        <div id="mensaje-exito" class="mensaje-exito">
            ✅ Usuario actualizado correctamente.
        </div>
    <?php endif; ?>

    <div class="container px-4">
        <h2>
            Lista de Usuarios
            <div class="botones-acciones">
                <a href="../Agente/listaforms.php" class="btn-solicitudes">Ver Solicitudes</a>
                <a href="../Administrador/adminpanel.php" class="btn-regresar">Regresar</a>
            </div>
        </h2>

        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
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
                        <td><?= htmlspecialchars($fila['usuario']) ?></td>
                        <td><?= htmlspecialchars($fila['telefono']) ?></td>
                        <td><?= htmlspecialchars($fila['direccion']) ?></td>
                        <td><?= htmlspecialchars($fila['correo']) ?></td>
                        <td><?= htmlspecialchars($fila['rol']) ?></td>
                        <td><?= $fila['estado'] ? 'Activo' : 'Inactivo' ?></td>
                        <td>
                            <a href="editar_usuario.php?id=<?= $fila['id'] ?>" class="btn-editar">Editar</a>
                            <button onclick="mostrarModal(<?= $fila['id'] ?>)">Eliminar</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div id="modal-eliminar">
        <p>¿Estás seguro de que quieres eliminar este usuario?</p>
        <button id="confirmar-eliminar">Sí, eliminar</button>
        <button onclick="cerrarModal()">Cancelar</button>
    </div>

    <script>
        window.onload = function() {
            const mensaje = document.getElementById('mensaje-exito');
            if (mensaje) {
                setTimeout(() => {
                    mensaje.style.opacity = '0';
                    setTimeout(() => mensaje.style.display = 'none', 500);
                }, 3000);
            }
        }

        let idUsuarioEliminar = null;

        function mostrarModal(id) {
            idUsuarioEliminar = id;
            document.getElementById('modal-eliminar').style.display = 'block';
        }

        function cerrarModal() {
            document.getElementById('modal-eliminar').style.display = 'none';
            idUsuarioEliminar = null;
        }

        document.addEventListener('DOMContentLoaded', function () {
            const btnConfirmar = document.getElementById('confirmar-eliminar');
            if (btnConfirmar) {
                btnConfirmar.addEventListener('click', function () {
                    if (idUsuarioEliminar !== null) {
                        window.location.href = "eliminar_usuario.php?id=" + idUsuarioEliminar;
                    }
                });
            }
        });
    </script>
</body>
</html>
