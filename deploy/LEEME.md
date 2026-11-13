# Despliegue en Hostinger (kyrios.leongrup.com)

La app vive en `~/domains/kyrios.leongrup.com/app` (clonada con git) y `public_html` es un enlace a `app/public`,
así el dominio solo ve la carpeta pública de Laravel.

Requisitos en hPanel: PHP **8.2 o superior** para el dominio, certificado SSL activo y la base de datos creada.
Los estilos (`public/build`) ya van dentro del repositorio: el servidor no necesita Node.

## Primera instalación (por SSH)

```bash
cd ~/domains/kyrios.leongrup.com
mv public_html public_html_original            # respaldo de la página por defecto
git clone https://github.com/DekoCB/Kyrios.git app
cd app

cp deploy/env.produccion.example .env
nano .env                                      # completar DB_PASSWORD, ADMIN_EMAIL, ADMIN_PASSWORD (y MASTER_PASSWORD si se usa)

# Hostinger desactiva proc_open y exec: composer sin scripts y el enlace de storage con ln
composer install --no-dev --optimize-autoloader --no-scripts
php artisan package:discover
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force                    # roles, catálogo base y usuario administrador
(cd public && ln -s ../storage/app/public storage)
php artisan config:cache && php artisan route:cache && php artisan view:cache

ln -s ~/domains/kyrios.leongrup.com/app/public ~/domains/kyrios.leongrup.com/public_html
```

## Actualizar después de cada cambio

En la PC: `git push`. En el servidor:

```bash
cd ~/domains/kyrios.leongrup.com/app && bash deploy/hostinger.sh
```

## Tareas programadas (hPanel > Avanzado > Cron Jobs, cada minuto)

```
* * * * * cd /home/u188616411/domains/kyrios.leongrup.com/app && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Sirve para los avisos de stock mínimo, de fechas del cronograma y de herramientas por devolver.

## Qué no viaja con git

- El archivo `.env` (contraseñas): se crea solo en el servidor.
- Los datos (productos, clientes, ventas): la base de Hostinger arranca vacía, solo con roles, catálogo base y el administrador.
- Las fotos de productos (`storage/app/public`): hay que subirlas aparte (FTP o hPanel) si se quieren conservar.
