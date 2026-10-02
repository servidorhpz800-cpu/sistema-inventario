<?php

require_once __DIR__ . '/includes/session.php';
iniciarSesionPersistente();

require __DIR__ . '/config/database.php';


/*
|--------------------------------------------------------------------------
| SI YA HAY UNA SESIÓN ACTIVA, REDIRIGIR SEGÚN EL ROL
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['usuario_id'])) {

    if ($_SESSION['rol'] === 'admin') {
        header('Location: admin.php');

    } elseif ($_SESSION['rol'] === 'tienda') {
        header('Location: tienda.php');

    } else {
        header('Location: tecnico.php');
    }

    exit;
}


/*
|--------------------------------------------------------------------------
| MENSAJES
|--------------------------------------------------------------------------
*/

$error = $_GET['error'] ?? null;
$success = $_GET['success'] ?? null;


/*
|--------------------------------------------------------------------------
| LOGIN
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {

    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';


    if ($usuario !== '' && $password !== '') {

        $stmt = $pdo->prepare(
            'SELECT * 
             FROM usuarios 
             WHERE usuario = :usuario 
             AND activo = 1 
             LIMIT 1'
        );

        $stmt->execute([
            'usuario' => $usuario
        ]);

        $user = $stmt->fetch();


        /*
        |--------------------------------------------------------------------------
        | VERIFICAR CREDENCIALES
        |--------------------------------------------------------------------------
        */

        if ($user && password_verify($password, $user['password'])) {

            /*
            |--------------------------------------------------------------------------
            | REGENERAR ID DE SESIÓN
            |--------------------------------------------------------------------------
            */

            session_regenerate_id(true);


            /*
            |--------------------------------------------------------------------------
            | GUARDAR DATOS DEL USUARIO EN LA SESIÓN
            |--------------------------------------------------------------------------
            */

            $_SESSION['usuario_id'] = $user['id'];
            $_SESSION['usuario'] = $user['usuario'];
            $_SESSION['nombre'] = $user['nombre'];
            $_SESSION['rol'] = $user['rol'];
            $_SESSION['equipo'] = $user['equipo'];


            /*
            |--------------------------------------------------------------------------
            | REDIRECCIÓN SEGÚN ROL
            |--------------------------------------------------------------------------
            */

            if ($user['rol'] === 'admin') {

                $redirect = 'admin.php';

            } elseif ($user['rol'] === 'tienda') {

                $redirect = 'tienda.php';

            } else {

                $redirect = 'tecnico.php';
            }


            header('Location: ' . $redirect);
            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | CREDENCIALES INCORRECTAS
        |--------------------------------------------------------------------------
        */

        $error = 'Credenciales inválidas.';

    } else {

        $error = 'Debe llenar usuario y contraseña.';
    }
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Compuser | Inventario</title>

    <link
        rel="stylesheet"
        href="assets/style.css"
    >

</head>

<body>

    <div class="login-shell">

        <div class="login-card">

            <h2>COMPUSER.NET🛜</h2>

            <p></p>


            <?php if ($error): ?>

                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>

            <?php elseif ($success): ?>

                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>

            <?php endif; ?>


            <form method="POST">

                <label>

                    <span>Usuario</span>

                    <input
                        type="text"
                        name="usuario"
                        placeholder=""
                        required
                    >

                </label>


                <label>

                    <span>Contraseña</span>

                    <input
                        type="password"
                        name="password"
                        placeholder=""
                        required
                    >

                </label>


                <button
                    type="submit"
                    name="login"
                    class="btn btn-primary"
                >
                    Entrar al sistema
                </button>

            </form>


            <div class="form-note">

                <!--
                Usuarios demo:
                admin / tienda / santos / jorge / dario / kevin / joshua.

                Contraseñas:
                admin123,
                tienda123,
                santos123,
                jorge123,
                dario123,
                kevin123,
                joshua123.
                -->

            </div>

        </div>

    </div>

</body>

</html>
