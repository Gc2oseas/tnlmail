# Security status
This is an early prototype, NOT production ready.

- Internal identities are metadata only; user login, permissions enforcement and mail routing are not yet implemented.
- SMTP/IMAP credentials are NOT collected or stored in this phase.
- Installer and webroot placement require security review. Storage should be outside the document root.
- Do not deploy with sensitive mail or real users.
- SMTP Reply-To identity must have a working external forwarder; never assume aliases are provisioned automatically.
- Authentication needs rate limiting, secure HTTPS enforcement, session hardening and integration tests.
- ZIP bootstrapper, backup, Web Push, MCP, chat and encryption are future work.
