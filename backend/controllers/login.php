<?php

require_once dirname(__DIR__, 2) . '/includes/session.php';
iniciarSesionPersistente();

require dirname(__DIR__, 2) . '/config/database.php';


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