# Cuentas personales por dominio y contexto de envío

Una cuenta es personal cuando su dominio difiere del dominio empresarial configurado. No depende de que esté guardada como cuenta principal, asociada o asignada. La advertencia del editor sigue ese criterio. Los buzones asignados conservan su gestión y permisos de acceso.

El POST del editor externo obtiene el propietario y la dirección del contexto de formulario guardado en sesión antes de resolver el buzón. Verifica la identidad, la propiedad o asignación, el plazo de 30 minutos y las credenciales de la misma cuenta. Un cambio de carpeta o de buzón en otra pestaña no altera el SMTP de ese formulario. La comprobación CSRF y el consumo del formulario siguen siendo obligatorios.

La herramienta de diagnóstico sender_account_status está limitada a cuentas del dominio praxma.org. Indica configuración, clasificación y permisos sin revelar contraseñas ni contenidos. Con probe=true comprueba autenticación IMAP y SMTP con credenciales guardadas sin enviar mensajes. El último error del formulario se conserva por identidad para distinguir problemas de selección y de transporte.
