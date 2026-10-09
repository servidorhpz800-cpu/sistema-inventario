# Compuser - Inventario de equipos de internet

Sistema básico en PHP/MySQL para gestionar inventario de equipos de internet con roles separados:

- Administrador
- Tienda
- Técnicos

## Requisitos

- XAMPP con Apache + MySQL activos
- PHP 8.x
- Navegador web
- Extensión PHP cURL y Fileinfo habilitadas

## Instalación rápida

1. Copia esta carpeta en `C:\xampp\htdocs\compuser`
2. Inicia Apache y MySQL en XAMPP
3. En phpMyAdmin, importa `database/schema.sql` o ejecuta el contenido de `todo_sql`. Ambos crean la base de datos, todas las tablas y los usuarios demo.
4. Abre `http://localhost/compuser/index.php`
5. Usa los usuarios demo:
   - admin / admin123
   - tienda / tienda123
   - santos / santos123
   - jorge / jorge123
   - dario / dario123
   - kevin / kevin123
   - joshua / joshua123

La aplicación usa una sola conexión PDO compartida por todos sus módulos. La extensión `pdo_mysql` debe estar habilitada. La estructura SQL se importa una vez; no se crean ni alteran tablas al abrir cada página.

Las sesiones de acceso usan una cookie persistente de hasta 10 años y no se cierran por inactividad normal. En equipos compartidos, usa siempre **Cerrar sesión** al terminar.

## Base de datos central

Para configurar TiDB Cloud, copia `config/database.local.example.php` como `config/database.local.php` y edita ahí el host, usuario, contraseña y certificado CA. Ese archivo está excluido por `.gitignore` y no debe subirse a GitHub. También puedes definir `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` y `DB_SSL_CA` en el entorno de Apache; las variables de entorno tienen prioridad. TiDB Cloud usa normalmente el puerto `4000`.

### Despliegue en Render

Configura en el servicio web de Render las variables `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` y `DB_PASS` con los datos de tu servidor MySQL externo. Usa el puerto que indique ese proveedor (`4000` es el valor predeterminado del proyecto para TiDB Cloud; otros servidores MySQL suelen usar `3306`). `DB_NAME` debe ser la base donde se instalará el esquema, normalmente `compuser_inventario`.

Render ejecuta la aplicación, pero el esquema no se importa automáticamente. Con un cliente MySQL conectado a esa misma base y servidor, importa `database/schema.sql` una sola vez con una cuenta que tenga permisos para crear tablas e insertar los usuarios iniciales. Después verifica que exista `usuarios` (por ejemplo, con `SHOW TABLES;`). No ejecutes el esquema repetidamente sobre una instalación existente.

El error `Table 'compuser_inventario.usuarios' doesn't exist` significa que la aplicación sí alcanzó una base llamada `compuser_inventario`, pero esa base no tiene la tabla que necesita el inicio de sesión. Confirma que importaste el esquema en el mismo servidor indicado por `DB_HOST` y la misma base indicada por `DB_NAME`; importar el archivo en MySQL local de XAMPP no crea las tablas en el servidor remoto.

La conexión requiere TLS y valida el certificado del servidor. `DB_SSL_CA` puede apuntar al certificado CA PEM de TiDB Cloud; si no se define, se usa el archivo indicado por `openssl.cafile` o `curl.cainfo` en `php.ini`. Si ninguno apunta a un certificado válido, configura `DB_SSL_CA` en `database.local.php`.

### Solución de problemas de conexión

- `Access denied` o error `1045`: revisa `DB_USER` y `DB_PASS`, y confirma que la IP del servidor esté permitida en las reglas de acceso de TiDB Cloud.
- Error de TLS o certificado: verifica que `DB_SSL_CA` apunte a un archivo PEM existente y válido. En XAMPP suele estar disponible `C:\xampp\apache\bin\curl-ca-bundle.crt`.
- Tiempo de espera o conexión rechazada: confirma el hostname del clúster, el puerto `4000` y que la red permita conexiones salientes a TiDB Cloud.
- Error de controlador: habilita `pdo_mysql` en el PHP que usa Apache y reinícialo.

Si las credenciales se subieron previamente a GitHub, moverlas ahora no las elimina del historial. Cambia la contraseña de TiDB Cloud y limpia el historial del repositorio antes de volver a publicarlo.

Importa `database/schema.sql` una sola vez en el servidor central con una cuenta administradora. Después crea un usuario exclusivo para la aplicación y dale permisos solo sobre esta base; no uses `root` ni expongas el puerto MySQL a Internet. Permite conexiones únicamente desde las IP de los servidores web mediante el firewall del servidor y las reglas de MySQL.

Ejemplo de permisos (reemplaza la IP y la contraseña; ejecútalo como administrador de MySQL):

```sql
CREATE USER 'compuser_app'@'192.168.1.20' IDENTIFIED BY 'cambia-esta-clave';
GRANT SELECT, INSERT, UPDATE, DELETE ON compuser_inventario.* TO 'compuser_app'@'192.168.1.20';
```

Si ya tienes datos, haz primero un respaldo. No vuelvas a importar el esquema sobre una instalación antigua esperando que modifique tablas existentes. Si todavía faltan columnas de domicilio o reportes, aplica primero `database/migracion_domicilio_reportes.sql`; después aplica una sola vez `database/migracion_equipo_reportes.sql` para que Tienda y Admin muestren el módem seleccionado en cada reporte. Aplica también una sola vez `database/migracion_clientes.sql` para crear el directorio de clientes y asociar las órdenes existentes por teléfono. Ejecuta `database/migracion_indices.sql` una sola vez para añadir índices a esa base. En instalaciones existentes, aplica además `database/migracion_movimientos_evidencias.sql` para guardar el cliente por movimiento/reporte y habilitar varias imágenes por reporte. Aplica `database/migracion_ciudad.sql` para agregar la ciudad a clientes, órdenes y reportes; debe ejecutarse en la misma base de datos que usa la aplicación y antes de desplegar código que consulte `r.ciudad`. Si aparece `Unknown column 'r.ciudad'`, la migración todavía no se ha aplicado a esa base. No ejecutes estas migraciones en una base recién creada con `schema.sql` si sus tablas ya incluyen esos cambios.

Las fotos de reportes e instalaciones se guardan como archivos; la base central conserva sus nombres. La aplicación no borra evidencias por antigüedad y las sirve mediante `evidencia.php`. Para que sobrevivan reinicios y nuevos despliegues, configura `COMPUSER_REPORTES_DIR` con una ruta absoluta en un disco persistente montado en el servidor. En Render, monta un Persistent Disk (por ejemplo en `/var/data`) y agrega la variable `COMPUSER_REPORTES_DIR=/var/data/compuser/reportes`; concede permisos de escritura a Apache (`www-data`). Antes de desplegar con el disco nuevo, haz un respaldo y copia las evidencias existentes desde `/var/www/html/uploads/reportes/` al directorio persistente:

```sh
mkdir -p /var/data/compuser/reportes
cp -a /var/www/html/uploads/reportes/. /var/data/compuser/reportes/
chown -R www-data:www-data /var/data/compuser/reportes
```

Si ejecutas más de un servidor web, la ruta debe apuntar a almacenamiento compartido entre todos ellos. Sin disco persistente, el directorio predeterminado bajo `uploads/reportes` puede perder sus archivos al reemplazarse el contenedor o el servidor. No se guardan imágenes binarias en MySQL para evitar inflar y ralentizar la base. La aplicación no impone un máximo de tamaño o cantidad de imágenes; esos límites, si los hay, dependen de PHP (`upload_max_filesize`, `post_max_size`, `max_file_uploads`) y del servidor web. Al enviar un reporte técnico con ubicación GPS, la ciudad y el enlace del mapa se actualizan en el registro del cliente y en la orden relacionada para reutilizarlos en futuras visitas.

## Funcionalidades

- Administración de inventario
- Agregar, editar, eliminar equipos
- Importar módems al stock desde CSV y exportar inventario, reportes, instalaciones y clientes desde Tienda
- Estados: nuevo, medio uso, recogido, dañados, no sirven
- Despacho de equipos a técnicos
- Seguimiento de órdenes
- Directorio de clientes con domicilios, búsqueda y reutilización al crear órdenes
- Reportes técnicos
- Resumen de frecuencia de reportes por cliente, con filtro por cliente y técnico
- Detección del fabricante al leer el serial con lector de códigos de barras USB
- Roles separados por pantalla

## Archivos principales

- `index.php`, `admin.php`, `tienda.php`, `tecnico.php` - puntos de entrada públicos; se conservan sus URLs
- `backend/controllers/` - lógica de acceso, inventario y operaciones de cada pantalla
- `frontend/views/` - plantillas PHP de las pantallas
- `frontend/assets/css/` y `frontend/assets/js/` - estilos y scripts del navegador
- `includes/` - sesión y funciones compartidas
- `config/` - configuración de base de datos
- `uploads/reportes/` - evidencias adjuntas a reportes
- `evidencia.php` - entrega evidencias autenticadas desde el almacenamiento configurado
- `config/database.php` - conexión central configurable por variables de entorno
- `database/schema.sql` - esquema completo para instalaciones nuevas
- `database/migracion_domicilio_reportes.sql` - actualización de instalaciones anteriores
- `database/migracion_equipo_reportes.sql` - enlaza cada reporte con el equipo utilizado
- `database/migracion_movimientos_evidencias.sql` - agrega cliente por movimiento y evidencias múltiples
- `database/migracion_ciudad.sql` - agrega pueblo o ciudad a clientes, órdenes y reportes
- `database/migracion_indices.sql` - índices para bases existentes

## Importante

Mantén respaldos regulares de MySQL y de `uploads/reportes`. Las operaciones de esquema son manuales y deben ejecutarse antes de desplegar una versión que requiera nuevas columnas o tablas.

En **Tienda > Operación**, las exportaciones CSV incluyen inventario, reportes, instalaciones y clientes. La exportación de reportes e instalaciones respeta la vista diaria o el archivo seleccionado. Al elegir un cliente existente al crear una orden, se llenan sus datos y se reutiliza la ubicación guardada; si no tiene enlace en su ficha, se recuperan las coordenadas GPS de su reporte más reciente. Para importar módems, descarga la plantilla CSV, completa el serial de cada equipo y cárgala desde la misma sección. Se aceptan archivos separados por coma o punto y coma, de hasta 5 MB y 5,000 equipos; los seriales ya registrados se omiten y los equipos nuevos quedan disponibles en Tienda.

En **Admin > Reportes** y **Tienda > Operación**, el resumen muestra la cantidad histórica de reportes por cliente y el conteo por técnico; los filtros también permiten revisar el detalle visible. Al registrar un equipo, conecta un lector USB tipo teclado y escanea el serial en su campo. Se reconocen los prefijos `48575443` (Huawei), `TLPK` (TP-Link), `HWTC` (Telmex), `ALCL` (Nokia) y `V23`, `VSOL` o `GPON` (V-SOL). La detección completa automáticamente el fabricante y el tipo GPON cuando está disponible; los campos se pueden corregir manualmente.

En **Admin > Reportes** y **Tienda > Operación**, el resumen muestra la cantidad histórica de reportes por cliente y los técnicos que los enviaron; los filtros también permiten revisar el detalle visible en la pantalla. Al registrar un equipo, conecta un lector USB tipo teclado y escanea el serial en su campo. Se reconocen los prefijos `48575443` (Huawei), `TLPK` (TP-Link), `HWTC` (Telmex), `ALCL` (Nokia) y `V23`, `VSOL` o `GPON` (V-SOL). La detección completa automáticamente el fabricante y el tipo GPON cuando está disponible; puedes corregir esos campos manualmente.
