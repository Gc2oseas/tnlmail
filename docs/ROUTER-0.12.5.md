# Direcciones empresariales sin usuario interno

El router busca usuarios activos por dirección interna y buzones asignados. Una sola coincidencia se entrega internamente; varias coincidencias se detienen como ambiguas. Si una dirección de correo válida no tiene asignación activa, se envía por el SMTP de la cuenta remitente seleccionada, aunque su dominio coincida con el empresarial. Los nombres de usuario sin @ siguen requiriendo una coincidencia interna.

La salida SMTP conserva sus permisos, la cuenta seleccionada en el formulario, su configuración y el control de reintentos. No utiliza el correo público como alternativa. En una misma lista, los usuarios asignados reciben copia interna y los demás destinatarios y CC salen por el SMTP elegido. No se garantiza la existencia ni la entrega del destinatario remoto: eso lo determina el servidor de correo.
