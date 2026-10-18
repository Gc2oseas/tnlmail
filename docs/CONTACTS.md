# Libreta y búsqueda global

Disponible para usuarios autenticados en contacts.php. Nombre, apellido, empresa, cargo y correo son obligatorios; teléfono opcional. Los contactos públicos se comparten con el equipo. En contactos privados solo propietario y lectores autorizados reciben correo y teléfono. La administración tampoco omite esos permisos. El propietario recibe solicitudes y puede conceder o revocar lectura.

Importación CSV UTF-8: hasta 500 filas y 1 MB, columnas Nombre, Apellido, Empresa, Cargo, Correo, Telefono y Privado. Las últimas dos son opcionales. La importación es atómica y asigna los contactos al usuario que importa. Exportación solo para administradores: públicos y privados autorizados, hasta 10000 contactos; campos de fórmulas se neutralizan.

La búsqueda del encabezado relaciona nombres con correos autorizados y busca texto, asunto y participantes en chat propio, recepción permitida, enviados propios y bandejas de entrada IMAP conectadas. No busca carpetas IMAP archivadas. Máximo 40 resultados por sección local, 10 por buzón remoto; refinar la búsqueda cuando sea necesario. Las consultas remotas son de solo lectura y no marcan mensajes como leídos.
