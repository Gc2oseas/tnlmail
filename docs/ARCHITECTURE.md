# Architecture (draft)

PHP 8.3+, SQLite via PDO, HTML/CSS/JS PWA.

Modules: Mail, Chat, Files, Shared, Push and optional MCP.

Do not expose storage to the web. Production installer must verify the document root, TLS, extensions, writable storage, session security and key management before accepting accounts.

No IMAP, SMTP, encryption or authentication functionality is implemented yet. Chat E2EE must use a vetted protocol; do not invent custom cryptography.

Meetings and Google Drive are out of scope.
