

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
        href="frontend/assets/css/style.css"
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
