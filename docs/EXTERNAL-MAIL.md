# External mail (0.3.0-dev)

Use **Correo externo** in the administrator or employee panel. Each login has its own mailbox configuration: `admin:ID` and `employee:ID` are separate identities, even when their numeric IDs match. This version does not share a central mailbox or provision mailboxes on the hosting provider.

Configure the email address (also the authentication username), IMAP hostname and SMTP hostname. Only direct, certificate-verified TLS is supported: IMAP 993 and SMTP 465. OAuth and STARTTLS/587 are not included. Public DNS names are required; literal IP addresses and resolutions to private/reserved addresses are rejected.

Save the mailbox password, then click **Probar conexión sin enviar correo**. The password field is never prefilled. Leaving it empty on subsequent saves retains the original password for the same email address. Credentials use AES-256-GCM, with owner-bound authentication and a random key stored at `storage/external-mail.key`. Back up that key alongside SQLite; restoring SQLite alone cannot recover credentials if the key is missing. The protected storage directory must never be served by HTTP. A server compromise with access to both key and database can decrypt credentials.

Inbox listing reads the latest 20 message headers per page from INBOX. Messages are fetched using UID, EXAMINE and BODY.PEEK, so reading does not mark them as read. Full-message reading is limited to 1 MB. MIME text/plain is preferred; HTML-only messages are reduced to escaped inert text. Attachments, remote images, folder management, replies, search and deletion are not included yet. The MIME reader is intentionally limited and may not display every real-world MIME message correctly.

Sending uses PHPMailer 7.1.1 (upstream commit 1bc1716a507a65e039d4ac9d9adebbbd0d346e15), pinned under app/ThirdParty/PHPMailer with its LGPL license text. No Composer execution is required on the hosting server. One recipient and a plain-text body are supported. A one-use form nonce and POST/redirect/GET prevent simple refresh resubmission. SMTP acceptance does not prove final delivery. Uncertain SMTP outcomes are not retried automatically.

**Enviados en TNL Mail** records local copies of mail sent by this app. It is not an IMAP Sent-folder view and the app does not APPEND a server-side sent copy. Each copy is scoped to the account owner.

The deployment-scoped MCP token can call external_mail_probe for the user-provided test account on cache.lat. It checks authentication only, does not save credentials and does not send messages. The diagnostic token cannot call it. Do not write real mailbox credentials to Git, logs, fixtures or the deployment manifest.
