# Remitente y servidor de envío

La selección de Correo externo se recuerda por identidad de sesión. Volver al acceso general conserva el buzón que se estaba utilizando, en lugar de seleccionar otra vez el primer buzón empresarial asignado. Los selectores explícitos prevalecen y se valida la propiedad de la cuenta recordada; una cuenta desvinculada o reasignada no se sustituye silenciosamente por otra. Las comprobaciones del buzón externo usan la misma selección. La recepción pública mantiene su proceso independiente.

El editor de correo externo vincula su formulario de un solo uso al propietario y dirección del buzón. Al enviar se recarga esa misma cuenta guardada, con su servidor, usuario y contraseña SMTP; no se usa una cuenta empresarial o pública como alternativa. Las respuestas desde Recepción asignada siguen seleccionando explícitamente el correo público, salvo que el usuario elija otro remitente permitido. El otro editor conserva la cuenta externa activa como selección inicial y permite cambiarla en De.

Debajo del editor de una cuenta personal aparece el aviso de que la empresa podría conservar copia del correo enviado y se recomienda usar una cuenta empresarial o la recepción pública para clientes. El aviso se muestra u oculta al cambiar De. No se envía el aviso al destinatario ni se añade copia a otro buzón; las copias locales existentes permanecen ligadas al propietario de la cuenta.

La prueba SenderSelectionTest usa cuentas sintéticas y un transporte SMTP simulado: comprueba servidor, credenciales y From, selección persistida, propiedad, bloqueo de formularios obsoletos y ausencia de sustitución a correo corporativo. No implica una prueba de autenticación real de proveedores personales.
