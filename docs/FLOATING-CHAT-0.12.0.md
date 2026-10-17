# Chat flotante y conversaciones compactas

Las conversaciones presentan primero lo más reciente, con páginas de 10 mensajes y enlaces a páginas más antiguas. Los mensajes del mismo segundo se ordenan por su identificador de secuencia cuando está disponible. El lector externo busca hasta 30 mensajes relacionados del servidor, además de sus copias locales; la paginación no elimina mensajes.

El acceso al chat interno pasa del menú izquierdo a un botón flotante en la esquina inferior derecha. El panel contiene sus conversaciones, sus contadores, un cuadro de respuesta rápido y la creación de chats con usuarios internos. Se conservan los mensajes internos existentes y las páginas antiguas siguen siendo accesibles. El envío desde chat.php valida sesión, usuario activo, CSRF y formulario de un solo uso, y utiliza exclusivamente entrega interna. El cuerpo de texto no se interpreta como HTML.

El panel actualiza solo el historial o la lista, conservando el cuadro de redacción. El sondeo del padre coordina las peticiones con Web Locks; la sesión bloqueada también bloquea el chat.

El sonido es una secuencia sintetizada de dos notas senoidales con entrada y salida suave, generada mediante Web Audio. Activar sonido reproduce una muestra y habilita las alertas; Silenciar sonido las detiene. La preferencia se conserva en ese navegador, que debe recibir una interacción para permitir audio después de cambiar de página. No se reproducen alertas al cargar los contadores por primera vez. Los identificadores del último mensaje ayudan a detectar mensajes nuevos incluso si el chat abierto ya los marca como leídos. Las pestañas limitan las repeticiones sonoras mediante una marca local de tres segundos.
