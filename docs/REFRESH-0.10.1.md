# Actualización 0.10.1-dev

El correo interno, la recepción compartida y los avisos actualizan sus listas cada 10 segundos mientras la aplicación está abierta. Los buzones externos se consultan por IMAP cada 60 segundos; SMTP sigue enviando en el momento de enviar. Las solicitudes de fondo se ejecutan en cola, separadas por 150–299 ms, y se coordinan entre pestañas mediante Web Locks cuando está disponible. No se reemplazan editores ni diálogos abiertos. Actualizar ahora adelanta la comprobación del navegador, respetando el límite del servidor.

Se reconocen respuestas de asignación con saludo y «Para usuario», excluyendo citas HTML y firmas. La recepción reevalúa hasta 50 mensajes pendientes por ciclo, sin reiniciar el cursor ni volver a enviar respuestas automáticas. La comprobación MCP reception_audit compara los últimos UID del buzón con la importación y el reconocimiento de destinatario sin devolver contenido del correo.

Sin una pestaña abierta se necesita ejecutar ReceptionWorker.php mediante el cron del alojamiento, cada minuto. Esta actualización no configura ese cron.

La revisión comprueba además hasta 20 UID anteriores al cursor, a partir del primer correo ya importado en esa misma UIDVALIDITY. Recupera huecos de importación sin tocar mensajes anteriores a la activación, sin duplicar registros y sin enviar nuevas respuestas automáticas durante la recuperación.
