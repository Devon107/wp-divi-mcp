# WP Divi MCP: guía de conexión

Esta guía es para el propietario del sitio. Explica cómo instalar el plugin, crear una conexión y añadirla a Claude para que pueda gestionar el sitio y construir páginas con Divi 5.

## Qué necesitas

- Un WordPress con HTTPS (el endpoint rechaza HTTP plano, porque la URL de conexión lleva un secreto).
- WordPress 6.2 o superior y PHP 7.4 o superior.
- El tema Divi 5 activo, si quieres que Claude construya páginas con Divi. Sin Divi el plugin funciona igual para contenido, medios, menús y ajustes; solo faltan las herramientas de Divi.
- El archivo `wp-divi-mcp.zip`. Descárgalo de la página de releases del repositorio. Para una versión sin publicar, ve a **Actions → Build zip → Run workflow**, elige la rama y, cuando termine, descarga el artefacto de esa ejecución. GitHub lo entrega dentro de otro zip: descomprímelo una vez y usa el `wp-divi-mcp.zip` de dentro.

## 1. Instalar el plugin

1. En el escritorio de WordPress: **Plugins → Añadir nuevo → Subir plugin**, elige `wp-divi-mcp.zip` y pulsa **Instalar ahora**.
2. Pulsa **Activar plugin**.
3. Si tienes instalado el plugin antiguo **Divi 5 MCP Companion**, desactívalo. WP Divi MCP lo sustituye y la pantalla de ajustes te avisa mientras siga activo.

## 2. Crear una conexión

1. Ve a **Ajustes → Divi MCP**.
2. En **New connection** rellena:
   - **Label**: un nombre que te ayude a reconocerla, por ejemplo "Claude Desktop" o "Claude web".
   - **Acts as user**: el usuario de WordPress con cuyos permisos actuará Claude. Todo lo que Claude haga queda a nombre de ese usuario y limitado a lo que ese usuario puede hacer.
   - **Mode**: **Read only** (solo lectura: Claude puede mirar, pero no cambiar nada) o **Full access** (lectura y escritura).
   - **Expires**: nunca, a 30 días o a 90 días.
3. Pulsa **Create connection**.
4. Aparece la **URL de conexión**. **Se muestra una sola vez.** Cópiala ahora (botón **Copy URL**). Si la pierdes, revoca esa conexión y crea otra.

La URL tiene esta forma (el valor de `key` es el secreto):

```
https://tu-sitio.com/wp-json/wp-divi-mcp/v1/mcp?key=...
```

Si tu sitio no usa enlaces permanentes bonitos, la URL será del tipo `https://tu-sitio.com/index.php?rest_route=/wp-divi-mcp/v1/mcp&key=...`. Es normal y funciona igual.

Trata esta URL como una contraseña: cualquiera que la tenga puede actuar como el usuario elegido.

## 3. Añadir la conexión a Claude

### claude.ai (web)

1. Abre **Settings → Connectors**.
2. Pulsa **Add custom connector**.
3. Pon un nombre (por ejemplo "Mi sitio WordPress") y pega la URL de conexión completa.
4. Guarda. En una conversación nueva, activa el conector desde el menú de herramientas.

Según tu plan, añadir conectores personalizados puede requerir que un administrador de la organización lo permita.

### Claude Desktop

Los conectores que añades en claude.ai se sincronizan con Claude Desktop, así que normalmente no tienes que hacer nada más: añádelo en **Settings → Connectors** de la web o de la propia aplicación.

Si prefieres configurarlo a mano, usa un puente como `mcp-remote` en `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "mi-sitio": {
      "command": "npx",
      "args": ["-y", "mcp-remote", "https://tu-sitio.com/wp-json/wp-divi-mcp/v1/mcp?key=..."]
    }
  }
}
```

### Claude Code

Al crear la conexión, la pantalla muestra el comando listo para copiar (botón **Copy command**):

```bash
claude mcp add --transport http mi-etiqueta "https://tu-sitio.com/wp-json/wp-divi-mcp/v1/mcp?key=..."
```

Ejecútalo en tu terminal. Después, `claude mcp list` debe mostrar la conexión como disponible.

## 4. Gestionar las conexiones

Todo se hace en **Ajustes → Divi MCP**:

- **Connections**: la lista de conexiones con su usuario, modo, caducidad y último uso. El botón **Revoke** corta una conexión al instante: la URL deja de funcionar y no se puede reactivar (se crea otra nueva).
- **Endpoint**: la casilla **Disable endpoint** apaga el servidor entero. Mientras esté marcada, todas las conexiones reciben un error 503. Es el interruptor de emergencia; al desmarcarla todo vuelve a funcionar con las mismas URLs.
- **Activity**: el registro de actividad con cada llamada de Claude. Las columnas son Time (fecha y hora), Connection (la etiqueta de la conexión), Tool (la herramienta), Target (sobre qué actuó), OK (si salió bien) y ms (duración). No hay columna de usuario: para saber con qué usuario actuó, mira la conexión en la lista. Se conserva 30 días. Revísalo después de una sesión de trabajo para ver qué se hizo.

## 5. Qué puede y qué no puede hacer Claude

**Puede**:

- Leer y buscar entradas, páginas, otros tipos de contenido, taxonomías, términos, comentarios, menús, usuarios (listado) y ajustes básicos.
- Crear y editar entradas y páginas, términos y medios (subida de imágenes), actualizar menús y moderar comentarios.
- Con Divi: construir páginas a partir de un árbol de diseño, crear páginas nuevas como borrador, exportar e inspeccionar páginas, aplicar un diseño a una página existente, ajustar el CSS de página, fusionar colores y variables globales sin sobrescribir los existentes, y crear y asignar plantillas del Theme Builder.

**No puede**:

- Instalar, activar, actualizar ni borrar plugins o temas.
- Crear, editar ni borrar usuarios.
- Borrar entradas o páginas de forma definitiva: solo van a la papelera y se pueden restaurar (no hay herramienta para borrar medios ni términos). Hay dos excepciones que debes conocer:
  - **Elementos de menú**: al editar un menú, `update_menu_items` borra de verdad los elementos que Claude quite. Esa edición **no se puede deshacer** desde WordPress. El resultado de la herramienta incluye la definición de cada elemento quitado (título, URL, tipo, objeto enlazado, padre y posición), así que Claude puede volver a crearlos si se lo pides; si no, habría que hacerlo a mano. Antes de dejar que Claude reorganice un menú importante, apunta cómo está o pídele que te lo describa primero.
  - **Plantillas del Theme Builder**: `divi_delete_theme_builder_template` manda la plantilla a la papelera de Divi y la desvincula del sitio. La plantilla en sí no se restaura con `divi_restore_backup` (el contenido de sus diseños de cabecera, cuerpo y pie sí queda guardado en las copias de seguridad). La plantilla por defecto del sitio no se puede borrar.
- Cambiar ajustes que no estén en la lista permitida (por ejemplo, la estructura de enlaces permanentes es solo de lectura).
- Tocar otras partes del Theme Builder que no sean cabecera y pie: solo permite establecer el diseño de la cabecera y el pie de una plantilla (más crear, listar y borrar plantillas).

**Seguridad añadida en Divi**:

- Antes de cambiar una página Divi se guarda una copia (copia de seguridad) que se puede restaurar. Lo mismo ocurre cada vez que `update_post` cambia el contenido de una entrada o página, sea de Divi o no.
- Para modificar una página que ya está publicada, programada o privada, Claude tiene que pedir confirmación explícita (`confirm_published`), también para cambiar el contenido de una entrada o página publicada con `update_post`. Los diseños nuevos van siempre a un borrador.
- Para cambiar la plantilla por defecto del sitio (la cabecera y el pie de todo el sitio) también hace falta una confirmación expresa (`confirm_default_template`).
- Para cambiar, restaurar o borrar cualquier otra plantilla del Theme Builder que esté activa y asignada a alguna parte del sitio (`use_on`), hace falta `confirm_live_template`. Si falta, Claude recibe un error que dice en qué páginas está en uso la plantilla.

Claude debería preguntarte antes de usar cualquiera de estas confirmaciones. Si no quieres que las use, dilo al empezar la conversación.

## 6. Notas de seguridad

- **La URL lleva el secreto en la propia dirección.** Eso significa que el token puede acabar en los registros de acceso del servidor (y de cualquier proxy o CDN intermedio). Quien pueda leer esos registros podría reutilizarlo. Por eso conviene dar caducidad a las conexiones y revocarlas al terminar.
- **Revoca la conexión cuando termines** el trabajo, sobre todo si compartiste la URL o la pegaste en una herramienta que no controlas.
- **Usa modo solo lectura para auditorías**, revisiones de contenido o cuando solo quieras que Claude analice el sitio.
- **Prefiere un usuario editor a un administrador** cuando no necesites que Claude toque los ajustes globales ni las opciones del tema. Con un usuario editor, estas herramientas ni siquiera aparecen, porque requieren permisos de administrador:
  - ajustes del sitio: `get_options`, `update_options`;
  - menús: `list_menus`, `get_menu`, `update_menu_items`;
  - globales y Theme Builder de Divi: `divi_get_globals`, `divi_merge_globals` y las cuatro herramientas de plantillas del Theme Builder.

  Con un editor siguen disponibles el contenido, los medios, los términos, los comentarios y las herramientas de páginas de Divi (`divi_create_page`, `divi_apply_layout`, `divi_export_page` y similares). Dos matices: `divi_list_backups` solo muestra copias de las páginas que ese usuario puede editar (las copias de plantillas del Theme Builder requieren permisos de administrador), y un diseño que añada o cambie colores o variables globales se rechaza para un editor salvo que se aplique sin sincronizar los globales.
- **HTTPS es obligatorio.** Sin HTTPS el endpoint rechaza las peticiones, y la pantalla de ajustes te lo indica.
- **Detrás de un proxy o un CDN**, el límite de intentos fallidos (10 por minuto y por IP) se aplica a la IP que ve WordPress. Si todas las peticiones llegan con la misma IP del proxy, un atacante que falle muchas veces puede bloquear también a los clientes legítimos durante un minuto. Si te pasa, configura el proxy para que WordPress vea la IP real del visitante.
- **HTTPS detrás de un proxy o CDN que termina TLS** (Cloudflare, un balanceador, algunos hostings): WordPress puede ver la petición como HTTP (`is_ssl()` devuelve falso) y el endpoint responde 403 aunque el visitante use HTTPS. Arréglalo en la configuración del proxy (que envíe `X-Forwarded-Proto` y el hosting lo respete) o en `wp-config.php` con `if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'] ) { $_SERVER['HTTPS'] = 'on'; }`. La constante `WP_DIVI_MCP_ALLOW_HTTP` desactiva la comprobación: úsala solo en instalaciones de confianza (por ejemplo, desarrollo local).
- **Plugins que bloquean la REST API a anónimos** (los que usan el filtro `rest_authentication_errors`, como muchos plugins de seguridad) pueden bloquear también el endpoint, porque Claude no inicia sesión con cookie sino con el token. Añade `/wp-json/wp-divi-mcp/v1/mcp` a su lista de rutas permitidas.
- **No pegues la URL en chats, tickets ni repositorios.** Si crees que se ha filtrado, revócala y crea otra.

## 7. Primeras peticiones para probar

Empieza siempre por una petición pequeña y en modo lectura:

- "Usa `site_info` y dime qué versión de WordPress y de Divi tiene el sitio."
- "Lista las páginas publicadas y dime cuáles no tienen imagen destacada."

Cuando todo funcione, pide algo más ambicioso:

- "Lee `divi://guide` y crea un borrador de landing para una clínica dental en Valencia, con hero, tres tratamientos, opiniones y un llamado a la acción. Usa los colores globales del sitio."
- "Exporta la página de inicio, dime cómo está construida y propón una variante para una campaña de verano. Créala como borrador nuevo, sin tocar la original."
- "Revisa los menús del sitio y dime qué enlaces apuntan a páginas en borrador o en la papelera."
- "Crea una plantilla de cabecera en el Theme Builder con logotipo y menú, pero no la asignes todavía; muéstramela primero."

Después de cada sesión, abre **Ajustes → Divi MCP → Activity** para ver lo que se hizo, y borra las páginas de prueba que ya no necesites.

## Desinstalar

Al borrar el plugin desde **Plugins** (no basta con desactivarlo), WordPress ejecuta `uninstall.php`, en cada sitio si es una red multisitio. Se borran todas las conexiones (opción `wpdm_connections`), los ajustes `wpdm_disabled` y `wpdm_db_version`, los transitorios `wpdm_*`, la tabla del registro de actividad (`{prefijo}wpdm_log`) y la tarea programada `wpdm_prune_log`.

Las **copias de seguridad** (entradas de tipo `wpdm_backup`) se conservan, para poder restaurarlas si vuelves a instalar el plugin. Si también quieres borrarlas, añade esta línea a `wp-config.php` antes de borrar el plugin:

```php
define( 'WPDM_UNINSTALL_DELETE_BACKUPS', true );
```

## Problemas frecuentes

| Síntoma | Causa probable |
| --- | --- |
| Claude dice que no puede conectar | La URL está incompleta (falta `key=...`), la conexión está revocada o caducada, o el sitio no usa HTTPS. |
| Error 503 | Está marcada la casilla **Disable endpoint**. |
| Error 429 | Demasiados intentos fallidos desde la misma IP; espera un minuto. |
| No aparecen las herramientas de Divi | El tema Divi 5 no está activo (solo quedan `divi_build_layout`, `divi_validate_layout` y `divi_find_icon`), o el usuario de la conexión no tiene permisos de administrador (faltan las de globales y Theme Builder). |
| No puede modificar una página | La conexión está en modo **Read only**, o la página está publicada y falta la confirmación. |
