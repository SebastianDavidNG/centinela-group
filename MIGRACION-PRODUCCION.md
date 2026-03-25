# Migración a producción — Centinela Group

Guía para llevar el sitio WordPress (Docker local) a **producción** en **https://centinelagroup.com** sin errores.

---

## 1. Lo que necesito de ti / del hosting

Para poder ayudarte paso a paso o generar scripts concretos, necesito:

| Requisito | Descripción |
|-----------|-------------|
| **Acceso al servidor** | SSH (usuario, host, puerto) o panel (cPanel, Plesk, etc.) y SFTP/FTP. |
| **Base de datos en producción** | Host de MySQL, nombre de BD, usuario y contraseña (crear una BD vacía para importar). |
| **PHP** | Versión **8.2** (mínimo 8.1). Módulos: `mysqli`, `curl`, `mbstring`, `xml`, `zip`, `gd`/`imagick`, `openssl`. |
| **Dominio y SSL** | Dominio `centinelagroup.com` apuntando al servidor; certificado SSL (HTTPS). |
| **Ruta del sitio** | Ruta donde se instala WordPress (ej. `public_html` o `centinelagroup.com`). |

Cuando tengas esto, podemos:
- Adaptar el `wp-config.php` de producción.
- Decidir si subes archivos por SFTP, Git o panel.
- Hacer el reemplazo de URLs en la base de datos (local → producción).

---

## 1.1 Migración en cPanel (tu servidor)

Configuración de producción que vas a usar:

| Dato | Valor |
|------|--------|
| **Panel** | cPanel |
| **Base de datos** | `centinel_CentinelaGroup2026` |
| **Usuario BD** | `centinel_C3nt1n3l4Gr0up` |
| **Host MySQL** | `localhost` (habitual en cPanel) |
| **PHP** | 8.3 ✓ (compatible) |

La **contraseña** de la BD la tienes tú; al crear el `wp-config.php` en el servidor, úsala en `DB_PASSWORD`.

Pasos concretos para tu caso:
1. Exportar y reemplazar URLs (sección 3.1 y 3.2).
2. En cPanel → **Archivos** (o Administrador de archivos), subir todo el sitio a la carpeta del dominio (ej. `public_html`).
3. En cPanel → **phpMyAdmin**, seleccionar la BD `centinel_CentinelaGroup2026` e **Importar** el archivo `centinela-group-production.sql`.
4. En el servidor, crear `wp-config.php` con la configuración de la sección **4.1** (abajo), pegando tu contraseña de BD donde indica.
5. Comprobar permalinks e integraciones (secciones 3.6 y 3.7).

---

## 2. Resumen de tu entorno local

- **WordPress** en Docker (PHP 8.2, Apache).
- **MySQL 8.0**, charset `utf8mb4`.
- **URL local:** `http://localhost:8081`.
- **URL producción:** `https://centinelagroup.com` (recomendado con HTTPS).
- **Theme:** `centinela-group-theme` (Elementor, WooCommerce, ACF, Syscom, Wompi, etc.).
- **Config actual:** `WP_HOME` y `WP_SITEURL` definidos en `wp-config.php` para local.

---

## 3. Pasos de migración (orden recomendado)

### 3.1 Preparar el export de la base de datos (local)

En tu máquina, con los contenedores levantados:

**Opción rápida (script incluido):**

```bash
chmod +x scripts/export-db-for-production.sh
./scripts/export-db-for-production.sh https://centinelagroup.com
```

Se generan `centinela-group-export.sql` (copia cruda) y `centinela-group-production.sql` (con URLs ya reemplazadas).

**Opción manual:**

```bash
docker compose exec db mysqldump -u wordpress -pwordpress wordpress --single-transaction --routines --triggers > centinela-group-export.sql
```

Guarda los `.sql` en un lugar seguro. **No** los subas a repositorios públicos (contienen datos y posiblemente claves).

### 3.2 Reemplazar URLs en el SQL (antes de importar en producción)

En el SQL aparecen `http://localhost:8081` y posiblemente rutas locales. Hay que cambiarlas por la URL de producción.

**Opción A — Sed (terminal):**

```bash
# Crear copia
cp centinela-group-export.sql centinela-group-production.sql

# Reemplazar (usa la URL final con la que quieras que quede el sitio)
sed -i '' 's|http://localhost:8081|https://centinelagroup.com|g' centinela-group-production.sql
```

En Linux (sin `-i ''`):

```bash
sed -i 's|http://localhost:8081|https://centinelagroup.com|g' centinela-group-production.sql
```

**Opción B — Plugin en WordPress (después de subir archivos e importar):**

1. Subir archivos e importar el SQL **sin** reemplazar (o con reemplazo mínimo).
2. En producción, instalar **Better Search Replace** (o **WP-CLI**: `wp search-replace 'http://localhost:8081' 'https://centinelagroup.com' --all-tables`).
3. Ejecutar el reemplazo en todas las tablas y revisar.

**Recomendación:** hacer el reemplazo en el SQL antes de importar (Opción A) para que `siteurl` y `home` en `wp_options` ya queden correctos.

### 3.3 Subir archivos al servidor

Sube todo el contenido del proyecto **excepto**:

- `docker-compose.yml`, `Dockerfile`, `.env` (no se usan en hosting tradicional).
- La carpeta `db_data` o volúmenes de Docker (si existieran en el repo).
- El archivo `.sql` de export (no debe estar en el servidor público).
- Cualquier archivo sensible (`.env`, backups).

**Incluir:**

- Raíz de WordPress: `wp-admin`, `wp-includes`, `wp-content`, `wp-config.php` (el de producción, ver sección 4), etc.
- Theme: `wp-content/themes/centinela-group-theme/`.
- Plugins: `wp-content/plugins/`.
- **Subida de medios:** `wp-content/uploads/` (imágenes y archivos que hayas usado en local).

Puedes subir por SFTP, rsync o, si el hosting lo permite, clonar por Git y luego subir solo `wp-content` y el `wp-config.php` de producción.

#### Crear un ZIP para subir por cPanel

Desde la raíz del proyecto (donde está `wp-admin`, `wp-content`, etc.) puedes crear un ZIP excluyendo lo que no debe ir al servidor:

```bash
# Desde la raíz del proyecto (centinela-group)
zip -r centinela-group-sitio.zip . \
  -x "*.DS_Store" \
  -x "docker-compose.yml" \
  -x "Dockerfile" \
  -x ".env" \
  -x "*.sql" \
  -x "db_data/*" \
  -x ".git/*" \
  -x "wp-config.php"
```

**Importante:** No incluyas `wp-config.php` en el ZIP (es el de local). Crea el `wp-config.php` de producción en el servidor con la sección 4.1 después de subir y descomprimir.

Si el ZIP resulta demasiado grande, puedes crear uno solo con `wp-content` (themes, plugins, uploads) y subir el resto de WordPress por separado o usar el instalador de WordPress del hosting y luego reemplazar solo `wp-content`.

### 3.4 Configurar la base de datos en producción

1. En el panel del hosting, crea una base de datos MySQL (ej. `centinelagroup_wp`) y un usuario con todos los privilegios sobre esa BD.
2. Importa el SQL ya reemplazado (`centinela-group-production.sql`) vía phpMyAdmin o:

   ```bash
   mysql -h HOST -u USUARIO -p NOMBRE_BD < centinela-group-production.sql
   ```

3. Verifica que el prefijo de tablas sea el mismo que en local (`wp_` por defecto).

### 3.5 Usar `wp-config.php` de producción

En el servidor, el `wp-config.php` debe tener:

- Credenciales de la **base de datos de producción** (no las de Docker).
- **WP_HOME** y **WP_SITEURL** con la URL de producción (o no definirlos y dejarlos en la BD; si ya reemplazaste el SQL, la BD ya tendrá la URL correcta).
- **WP_DEBUG** en `false`.
- Claves y sales distintas a las de desarrollo (genera nuevas en [api.wordpress.org/secret-key/1.1/salt/](https://api.wordpress.org/secret-key/1.1/salt/)).

En la raíz del proyecto hay un ejemplo: **`wp-config.production.php.example`**. Cópialo a `wp-config.php` en el servidor y rellena los valores.

### 3.6 Comprobar permalinks y HTTPS

1. Entra al escritorio de WordPress en `https://centinelagroup.com/wp-admin`.
2. **Ajustes → Enlaces permanentes**: no hace falta cambiar nada; pulsa **Guardar** para refrescar las reglas.
3. Revisa que el sitio cargue por **HTTPS** y que no haya recursos mezclados (http en páginas https).

### 3.7 Revisar integraciones

- **Wompi (pagos):** configuración en producción (URLs de retorno, claves de producción).
- **Syscom (API):** si tienen restricción por dominio, añade `centinelagroup.com`. Las opciones `centinela_syscom_client_id` y `centinela_syscom_client_secret` se migran con la BD; verifica que sigan siendo válidas.
- **WhatsApp / flotante:** revisa que el número y enlaces sigan siendo los deseados.
- **Elementor:** si hay estilos o URLs guardadas, suelen actualizarse con el reemplazo de URLs; si algo se ve raro, regenera CSS en **Elementor → Herramientas**.

### 3.8 Colores globales de Elementor (Blue Color, Green Color, etc.)

Si en producción las áreas que usan las variables de color de Elementor (Blue Color, Green Color, etc.) se ven sin color o con valores por defecto:

1. **El tema ya inyecta un fallback:** el theme Centinela imprime en `wp_head` las variables del tema (`--centinela-color-*` y `--e-global-color-primary/secondary/text/accent`). Si el Kit de Elementor está en la BD, también imprime todos los colores del Kit. Sube la versión actual del theme para que este fallback esté activo en producción.

2. **Replicar el Kit desde local (recomendado, Elementor gratuito):**
   - **Importante:** No uses la **Biblioteca de plantillas** (Kit Library) ni `elementor-app`; ahí solo se importan plantillas, no se exporta. La exportación está en la página **Herramientas** de WordPress.
   - **En local:** Ve directo a **Herramientas** de Elementor con esta URL (ajusta el dominio si hace falta):
     ```
     http://localhost:8081/wp-admin/admin.php?page=elementor-tools#tab-import-export-kit
     ```
     O desde el escritorio: menú lateral **Elementor** → **Herramientas** (no abras "Plantillas" ni la app). En la página de Herramientas, haz clic en la pestaña **Plantillas de sitio web** / **Website Templates**. Deberías ver el bloque **Exportar este sitio** con el botón **Export** / **Exportar**. Ahí descarga el `.zip` (puedes marcar solo **Site Settings** si solo quieres colores y tipografías).
   - **En producción:** Entra a `https://centinelagroup.com/wp-admin/admin.php?page=elementor-tools#tab-import-export-kit`, pestaña **Plantillas de sitio web** → **Import** / **Subir archivo .zip** y sube ese `.zip`.
   - Luego en **Elementor → Herramientas** → pestaña **Regenerar CSS** para refrescar estilos.

3. **Si no tienes el Kit exportado:** en producción, **Elementor → Configuración del sitio → Colores globales** y vuelve a crear los colores con los mismos valores que en local (por ejemplo Blue: `#021C37`, Green: `#229379`; ver `wp-content/themes/centinela-group-theme/assets/scss/_variables.scss`).

### 3.9 Salud del sitio (Herramientas > Salud del sitio)

WordPress muestra advertencias en **Herramientas → Salud del sitio**. Cómo resolver las más habituales en producción (cPanel):

---

#### 1. Caché de página no detectada y tiempo de respuesta lento

**Problema:** No hay plugin de caché de página activo y el servidor responde en ~1,4 s (recomendado &lt; 600 ms). Las cabeceras de caché (`cache-control`, `expires`, `etag`, etc.) no aparecen.

**Qué hacer:**

- **Instalar un plugin de caché de página** en producción. Opciones habituales en cPanel:
  - **LiteSpeed Cache** (si tu hosting usa servidor LiteSpeed; muchos cPanel lo tienen): instálalo desde **Plugins → Añadir nuevo** (buscar “LiteSpeed Cache”). Activa “Caché de página” y deja que genere las cabeceras. Suele mejorar mucho el tiempo de respuesta en visitas repetidas.
  - **Cache Enabler** (ligero, gratuito): instala “Cache Enabler”, actívalo y en **Ajustes → Cache Enabler** activa “Caché de página”. Añade cabeceras como `cache-control` y `expires`.
  - **WP Super Cache** o **W3 Total Cache** (gratuitos): más opciones; configúralos para “Page Cache” / “Caché de página”.
- Tras activar la caché, **Herramientas → Salud del sitio** debería detectar cabeceras de caché en la página principal (puede tardar un minuto o requerir una visita a la portada).
- El **tiempo de respuesta** en la primera petición (sin caché) depende del hosting. Si con caché activa la portada ya responde en &lt; 600 ms en visitas normales, el aviso de “tiempo lento” puede seguir apareciendo para la prueba que hace WordPress (primera petición). Si todo el sitio se siente lento, valora con tu proveedor: PHP 8.x, plan con más recursos o CDN.

---

#### 2. No tienes un tema predeterminado disponible

**Problema:** WordPress recomienda tener instalado un tema “por defecto” (Twenty Twenty-Four, Twenty Twenty-Five, etc.) por si el tema activo falla.

**Qué hacer:**

1. En producción: **Apariencia → Temas**.
2. **Añadir nuevo** → busca **“Twenty Twenty-Five”** (o **“Twenty Twenty-Four”**).
3. **Instalar** y, si quieres, **Activar** solo para comprobar; luego vuelve a activar **Centinela Group Theme**. O deja Twenty Twenty-Five **instalado pero no activo**: así WordPress ya tiene un tema de respaldo y el aviso desaparece.

---

#### 3. Deberías utilizar una caché de objetos persistente

**Problema:** La **caché de objetos** no es lo mismo que la caché de página. WordPress recomienda Redis, Memcached o LSMCD para cachear consultas a la base de datos (opciones, transientes, etc.). LiteSpeed Cache tiene **caché de página** (pestaña Cache) pero la caché de objetos se configura aparte.

**Qué hacer:**

**Opción A – Usar LiteSpeed Cache (si ya lo tienes instalado):**

1. En el escritorio: **LiteSpeed Cache** → pestaña **Object** (u **Objeto**; a veces está bajo **Settings** o **Cache** según versión).
2. En **Object Cache** (o **Método de caché de objetos**): activa **Enable Object Cache** (Activar caché de objetos) y elige el backend que ofrezca tu hosting:
   - **Redis** – si en cPanel tienes Redis activado (host suele ser `localhost`, puerto `6379`).
   - **Memcached** o **LSMCD** – si el hosting ofrece Memcached o LSMCD (LiteSpeed); puerto típico `11211`.
3. Rellena **Host** y **Port** (o socket si te lo dan). Guarda. Si la conexión es correcta, Salud del sitio dejará de mostrar el aviso.

**Opción B – Plugin Redis Object Cache (si no usas la caché de objetos de LiteSpeed):**

- Pregunta a tu hosting (cPanel): ¿tienen **Redis** o **Memcached** para tu cuenta?
- Si tienen **Redis:** actívalo en cPanel, luego instala el plugin **Redis Object Cache**, actívalo y conéctalo (por ejemplo `localhost:6379`).
- Si tienen **Memcached/LSMCD:** el hosting indicará cómo activarlo; a veces se usa un drop-in de WordPress para Memcached.

**Si el hosting no ofrece Redis ni Memcached ni LSMCD:** el aviso de “caché de objetos persistente” seguirá. No es crítico: el sitio funciona bien; solo mejora rendimiento. Puedes ignorarlo o valorar un plan que incluya caché de objetos.

---

#### 4. Un evento programado ha fallado (action_scheduler_run_queue)

**Problema:** Salud del sitio indica que el evento programado `action_scheduler_run_queue` no se ha ejecutado. Ese evento lo usa **Action Scheduler** (WooCommerce y otros plugins) para tareas en segundo plano. Suele deberse a que **WP-Cron** no se está disparando en el servidor (pocas visitas, cron desactivado o el servidor no llama a `wp-cron.php`).

**Qué hacer:**

1. **Configurar un cron real en cPanel** para que el servidor llame a WordPress cada cierto tiempo (recomendado cada 5–15 minutos):
   - En cPanel: **Cron Jobs** (o **Tareas cron**).
   - **Añadir nueva tarea cron**.
   - **Frecuencia:** por ejemplo cada 15 minutos: `*/15 * * * *` (o cada 5: `*/5 * * * *`).
   - **Comando:** usa uno de estos (sustituye la URL si es distinta):
     ```bash
     curl -s -o /dev/null https://centinelagroup.com/wp-cron.php?doing_wp_cron
     ```
     o:
     ```bash
     wget -q -O - https://centinelagroup.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
     ```
   - Guarda la tarea. Así el servidor ejecutará la cola de WordPress (y Action Scheduler) con regularidad.

2. **(Opcional)** Para evitar que el cron dependa de las visitas y que se ejecute dos veces (visita + cron), en el `wp-config.php` de producción puedes añadir **antes** de `require_once ABSPATH . 'wp-settings.php';`:
   ```php
   define( 'DISABLE_WP_CRON', true );
   ```
   Solo hazlo **después** de tener el cron de cPanel configurado; si no, no se ejecutará ningún evento programado.

3. **Comprobar:** tras unos minutos, **Herramientas → Salud del sitio** (o **Eventos programados** en Herramientas) debería dejar de marcar el fallo. Si usas WooCommerce, **WooCommerce → Estado → Programador de acciones** permite ver si la cola se ejecuta.

Si el aviso continúa, revisa en cPanel que la tarea cron esté activa y que la URL del sitio en el comando sea la correcta (con https).

---

Resumen rápido:

| Aviso                         | Acción principal                                              |
|------------------------------|---------------------------------------------------------------|
| Caché de página / respuesta  | Instalar y activar plugin de caché (LiteSpeed, Cache Enabler, etc.). |
| Tema predeterminado          | Instalar Twenty Twenty-Five (o similar) y dejarlo inactivo.   |
| Caché de objetos persistente | LiteSpeed Cache → pestaña **Object** → Enable Object Cache (Redis/Memcached/LSMCD). Si el hosting no lo ofrece, se puede ignorar. |
| Evento programado fallido (action_scheduler_run_queue) | cPanel → **Cron Jobs** → añadir tarea cada 15 min que llame a `https://tudominio.com/wp-cron.php?doing_wp_cron`. |

---

#### LiteSpeed Cache: activar la caché de página

En LiteSpeed Cache **no hay una opción que se llame solo "Page Cache"**. La caché de página se activa con el interruptor principal:

1. En el escritorio de WordPress, menú lateral **LiteSpeed Cache** (o **LSCache**).
2. Abre la pestaña **Cache** (no "General" ni "Page Optimization").
3. En la parte superior verás **Enable Cache** (Activar caché). Ponlo en **ON**.
4. Guarda los cambios. Eso ya activa la caché de página; el plugin enviará cabeceras como `cache-control`, `x-litespeed-cache`, etc.

**Si "Enable Cache" sale desactivado o con aviso "LSCache is disabled":** el servidor puede ser Apache en lugar de LiteSpeed. En ese caso:
- Revisa en cPanel qué servidor web usa tu hosting (LiteSpeed suele indicarse).
- Si es Apache, el plugin puede usar **QUIC.cloud** (en **LiteSpeed Cache → General** hay opciones para conectar con QUIC.cloud y usar su caché), o puedes usar otro plugin como **Cache Enabler** para caché de página en Apache.

---

#### Caché de página no detectada por timeout de “solicitud de bucle” (cURL 28)

Si Salud del sitio muestra **“No se puede detectar la presencia de la caché de página”** y el error es **cURL error 28: Operation timed out** (solicitud de bucle / loopback), el servidor tarda más de 5 segundos en responderse a sí mismo al hacer la prueba.

- **El tema Centinela** incluye un filtro que sube el timeout de las peticiones de bucle a **15 segundos**. Sube la versión actual del theme a producción y vuelve a ejecutar la prueba en **Herramientas → Salud del sitio**.
- Si **sigue fallando** tras 15 s, el hosting puede estar bloqueando o limitando las peticiones de bucle. Contacta con el proveedor y comenta que la **prueba de loopback** de WordPress (y la detección de caché de página) hace timeout; que comprueben firewall, restricciones a `localhost` o al propio dominio. La caché de página puede estar funcionando bien para los visitantes aunque Salud del sitio no la detecte.

---

### 3.10 Imágenes en placeholder tras importar el Kit de Elementor

Si después de importar la configuración (o el Kit) de Elementor las imágenes del Hero Slider, secciones del home y páginas internas se ven como **placeholder** en lugar de las imágenes que tenías en local, suele ser porque:

- El contenido importado o la base de datos siguen usando **URLs de local** (`http://localhost:8081/...`) para las imágenes, o
- Los archivos de **medios** no están en el servidor de producción (carpeta `wp-content/uploads`).

**Qué hacer (en este orden):**

1. **Comprobar que los medios están en producción**  
   En el servidor, la carpeta `wp-content/uploads` debe tener las mismas subcarpetas y archivos que en local (por ejemplo `uploads/2026/03/`, etc.). Si faltan, sube desde tu máquina la carpeta **`wp-content/uploads`** de local a producción (por FTP/cPanel), manteniendo la misma estructura.

2. **Reemplazar de nuevo las URLs en la base de datos**  
   Aunque ya hiciste el reemplazo al migrar, **importar el Kit de Elementor puede haber vuelto a meter URLs de local** en el contenido y en los meta de Elementor. Hay que reemplazar otra vez en **producción**:

   - **Opción A – Plugin Better Search Replace:**  
     Instálalo en producción (**Plugins → Añadir nuevo** → buscar "Better Search Replace"). En **Herramientas → Better Search Replace** pon:
     - **Search:** `http://localhost:8081`
     - **Replace:** `https://centinelagroup.com`
     - Marca **all tables** y que trate datos serializados. Haz primero **Dry run** y luego **Run search/replace**.
   - **Opción B – WP-CLI** (si tienes acceso por SSH en el servidor):  
     `wp search-replace 'http://localhost:8081' 'https://centinelagroup.com' --all-tables --report-changed-only`

   Así se actualizan las URLs dentro del contenido de las páginas y de los datos de Elementor (`_elementor_data`, etc.).

3. **Limpiar caché y revisar**  
   - En **LiteSpeed Cache** (si lo usas): **LiteSpeed Cache → Toolbox → Purge All** (o Purge All LiteSpeed Cache).  
   - Abre la portada y las páginas afectadas en una ventana de incógnito o con caché desactivada. Las imágenes deberían cargar ya con la URL de producción.

4. **Si alguna imagen sigue rota**  
   Entra en **Medios** en WordPress y comprueba que ese archivo exista. Si el archivo está en `uploads` pero no aparece en la biblioteca, puedes re-importarlo. Para bloques concretos (Hero Slider, etc.), edita la página con Elementor y vuelve a elegir la imagen desde la biblioteca de medios de producción.

### 3.11 Correo del cotizador (remitente, asunto y adjuntos)

El tema ya está configurado para que:

- **Remitente:** Los correos del cotizador (y del formulario web de cotización) salgan como **Centinela Group** &lt;noreply@centinelagroup.com&gt; en lugar de WordPress &lt;wordpress@...&gt;.
- **Asunto:** El asunto del correo al cliente sea **Cotización Web de Centinela Group** (o **Cotización Web de Centinela Group - [título]** si la cotización tiene título).

**Si en producción el remitente sigue siendo "WordPress" o wordpress@...:**  
Muchos hostings ignoran el encabezado `From` y usan por defecto una cuenta del servidor. Para que salga **noreply@centinelagroup.com** necesitas una de estas dos cosas:

1. Que **noreply@centinelagroup.com** exista como correo en tu hosting (cPanel → Correo electrónico) y que WordPress envíe por el servidor de correo del mismo dominio, o  
2. Usar un plugin de envío por SMTP (por ejemplo **WP Mail SMTP**) configurado con la cuenta noreply@centinelagroup.com (usuario y contraseña de esa cuenta).

**Si el PDF o el Excel no llegan como adjunto:**

- **Excel/CSV:** Se genera con el tema (sin librerías externas). Si no llega, revisa en el hosting que no se bloqueen adjuntos (tamaño o tipo de archivo) y que la carpeta temporal de PHP sea escribible.
- **PDF:** El tema incluye **Dompdf** vía Composer. Para que el adjunto sea PDF (y no el fallback HTML):
  1. En la carpeta del tema (`wp-content/themes/centinela-group-theme`) ejecuta: `composer install --no-dev`.
  2. Sube a producción el tema **incluyendo la carpeta `vendor/`** (o ejecuta `composer install --no-dev` en el tema en el servidor si tienes SSH).
  Si Dompdf no está disponible, el tema adjunta un HTML como fallback para que el cliente reciba la cotización.

Sube la versión actual del theme (con los cambios de remitente y asunto) a producción para que los correos usen **Centinela Group** y **Cotización Web de Centinela Group**.

---

#### Entregabilidad: Gmail no recibe, Outlook/Hotmail marca “remitente no verificado”

Si el correo de la cotización **llega a Hotmail pero no a Gmail**, o en Outlook/Hotmail aparece el aviso **“No se puede comprobar que este correo electrónico proviene del remitente”** ([documentación de Microsoft sobre phishing y remitentes](https://support.microsoft.com/en-us/office/phishing-and-suspicious-behavior-in-outlook-0d882ea5-eedc-4bed-aebc-079ffa1105a3)), la causa suele ser la misma: **falta de autenticación del dominio**.

- **Gmail** es muy estricto: si el servidor que envía no está autorizado para el dominio (SPF/DKIM), puede rechazar el mensaje o mandarlo a spam.
- **Outlook/Hotmail** muestra el aviso cuando el “From” (p. ej. noreply@centinelagroup.com) no coincide con un remitente verificado por SPF/DKIM.

**Qué hacer (recomendado):**

1. **Enviar por SMTP autenticado**  
   Usa un plugin como **WP Mail SMTP** o **FluentSMTP** y configura el envío con una de estas opciones:
   - **Cuenta de correo del propio dominio** (noreply@centinelagroup.com en cPanel), usando el servidor SMTP del hosting (ej. mail.centinelagroup.com, puerto 465, SSL). Así el servidor que envía es el de centinelagroup.com.
   - **Servicio externo** (Gmail/Google Workspace, SendGrid, Mailgun, etc.) usando su SMTP y, si es posible, con el “From” noreply@centinelagroup.com y el dominio verificado en ese servicio.

2. **Configurar SPF y DKIM para centinelagroup.com**  
   En el panel DNS del dominio (o en cPanel → Zone Editor / Registro DNS) añade o ajusta:
   - **SPF:** Un registro TXT que indique qué servidores pueden enviar correo por tu dominio (ej. el de tu hosting o el de SendGrid/Mailgun si usas uno de ellos). El hosting suele dar la línea SPF recomendada.
   - **DKIM:** Firma que demuestra que el mensaje viene de tu dominio. En cPanel suele estar en “Autenticación de correo” o “Email Authentication”; en SendGrid/Mailgun lo generas en su panel y añades el TXT que te den.

Con SPF y DKIM bien configurados y envío por SMTP autenticado (desde proyectos@ o desde un servicio que use tu dominio), Gmail y Outlook dejarán de marcar el correo como no verificado y mejorará la llegada a bandeja de entrada.

**Resumen:** El aviso de Outlook no es un fallo del tema; es que el servidor que envía (PHP mail o el SMTP del hosting sin autenticación) no está autorizado para el remitente del dominio. Solución: SMTP configurado con la cuenta del dominio (p. ej. noreply@centinelagroup.com) o con un servicio que firme el dominio + SPF/DKIM en el DNS.

---

#### PDF que no llega como adjunto

Si el **PDF sigue sin llegar** en el correo al cliente:

1. **Instalar Dompdf en el tema**  
   El tema tiene `composer.json` con `dompdf/dompdf`. En la carpeta del tema ejecuta `composer install --no-dev` y sube la carpeta `vendor/` a producción (o ejecuta `composer install --no-dev` en el servidor). El tema carga automáticamente el autoload desde `tema/vendor/autoload.php` al generar el PDF. Sin esto, se enviará el fallback HTML en lugar del PDF.

2. **Probar primero con Excel/CSV**  
   Elige “Excel” en el cotizador y envía de nuevo. Si el CSV sí llega, el problema es solo la generación del PDF; si tampoco llega, el hosting podría estar bloqueando adjuntos (tamaño o tipo). En ese caso, revisa límites de `wp_mail` o usa un plugin SMTP que envíe por un servicio que permita adjuntos.

3. **Revisar carpeta temporal de PHP**  
   Los adjuntos se crean en la carpeta temporal del sistema. Si PHP no puede escribir ahí o la ruta es incorrecta en el servidor, el archivo no se genera. Revisa en el hosting la configuración de `upload_tmp_dir` / `sys_get_temp_dir()`.

---

## 4. Ejemplo de `wp-config.php` para producción

Ver archivo **`wp-config.production.php.example`** en la raíz del proyecto. No sobrescribas tu `wp-config.php` local; úsalo solo como referencia para crear el `wp-config.php` **en el servidor**.

### 4.1 `wp-config.php` listo para cPanel (Centinela Group)

Copia el siguiente contenido en un archivo **`wp-config.php`** en la raíz del sitio **en el servidor** (no en tu máquina local). Donde dice `'TU_CONTRASEÑA_BD'` sustituye por la contraseña real de la base de datos que creaste en cPanel.

En cPanel el host de MySQL suele ser `localhost`. Si en la página de MySQL/cPanel te indican otro host (por ejemplo `localhost` o algo como `centinelagroup.com`), cambia `DB_HOST` por ese valor.

```php
<?php
define( 'DB_NAME', 'centinel_CentinelaGroup2026' );
define( 'DB_USER', 'centinel_C3nt1n3l4Gr0up' );
define( 'DB_PASSWORD', 'TU_CONTRASEÑA_BD' );  // ← La contraseña que configuraste para esta BD en cPanel
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

define( 'AUTH_KEY',         'genera-claves-en https://api.wordpress.org/secret-key/1.1/salt/' );
define( 'SECURE_AUTH_KEY',  'genera-claves-en https://api.wordpress.org/secret-key/1.1/salt/' );
define( 'LOGGED_IN_KEY',    'genera-claves-en https://api.wordpress.org/secret-key/1.1/salt/' );
define( 'NONCE_KEY',        'genera-claves-en https://api.wordpress.org/secret-key/1.1/salt/' );
define( 'AUTH_SALT',        'genera-claves-en https://api.wordpress.org/secret-key/1.1/salt/' );
define( 'SECURE_AUTH_SALT', 'genera-claves-en https://api.wordpress.org/secret-key/1.1/salt/' );
define( 'LOGGED_IN_SALT',   'genera-claves-en https://api.wordpress.org/secret-key/1.1/salt/' );
define( 'NONCE_SALT',       'genera-claves-en https://api.wordpress.org/secret-key/1.1/salt/' );

$table_prefix = 'wp_';

define( 'WP_DEBUG', false );
@ini_set( 'display_errors', 0 );

define( 'WP_HOME', 'https://centinelagroup.com' );
define( 'WP_SITEURL', 'https://centinelagroup.com' );

if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ) {
	$_SERVER['HTTPS'] = 'on';
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
```

**Importante:** Antes de subir, genera claves y sales nuevas en https://api.wordpress.org/secret-key/1.1/salt/ y reemplaza las líneas de `AUTH_KEY`, `SECURE_AUTH_KEY`, etc., con los valores que te devuelva esa página. Así las sesiones y cookies de producción serán distintas a las de desarrollo.

---

## 5. Checklist rápido

- [ ] Exportar BD local.
- [ ] Reemplazar `http://localhost:8081` → `https://centinelagroup.com` en el SQL.
- [ ] Subir archivos (WordPress + theme + plugins + uploads).
- [ ] Crear BD en producción e importar el SQL.
- [ ] Poner en el servidor un `wp-config.php` de producción (datos de BD, URL, sales, `WP_DEBUG` false).
- [ ] Guardar permalinks en el escritorio.
- [ ] Probar inicio, tienda, checkout, formularios y enlaces.
- [ ] Revisar Wompi, Syscom y WhatsApp en producción.

Cuando tengas **acceso al hosting** (SSH/SFTP y datos de BD), podemos concretar comandos o un script de despliegue adaptado a tu proveedor.
