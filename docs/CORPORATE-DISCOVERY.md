# Corporate discovery (0.6.1-dev)

Order: explicit company server profile; a unique matching set of saved corporate admin IMAP/SMTP host/port metadata (never usernames/passwords); known direct provider; domain discovery; known-provider MX fallback. Different corporate admin server profiles are not guessed.

Domain discovery uses HTTPS only:
1. autoconfig.DOMAIN/mail/config-v1.1.xml
2. DOMAIN/.well-known/autoconfig/mail/config-v1.1.xml
3. autoconfig.thunderbird.net/v1.1/DOMAIN
4. DNS SRV _imaps._tcp plus _submissions._tcp or _submission._tcp

Only TLS IMAP 993 and TLS SMTP 465 / mandatory STARTTLS 587 are supported. No Exchange Autodiscover, HTTP plaintext discovery, arbitrary redirects, unsupported port probing or certificate bypass. The existing advanced settings remain available.

HTTPS discovery sends only domain/path, with no mailbox address or password. All returned A/AAAA addresses must be public; the HTTPS connection pins the validated address while verifying TLS against the original hostname. Redirects are disabled, size limited to 64 KiB, HTTPS timeout 2.5 seconds per request; DNS resolution timing depends on the host resolver. XML rejects DTD/entities, null-byte input, cross-domain provider entries and unsupported username placeholders. External entities are disabled. Mail connections independently validate their public target and certificates.

Positive domain templates are cached one hour; negative results five minutes, maximum 1000 domains. Username templates are expanded per employee, not stored as shared account logins. Explicit company settings override cache immediately. An automatic save goes directly to the inbox; IMAP authentication takes place there, and SMTP is verified on send/probe. Saving configuration itself does not establish successful authentication.

Authenticated MCP corporate_mail_settings discovers only the installation's configured company domain and returns sanitized server metadata. It never uses or returns a password or account username.

Tests cover parser, HTTPS request construction via injected transport, domain matching, DTD rejection, private/mixed DNS rejection, SRV fallbacks, per-user substitution/cache and admin overrides using synthetic data. A failed network lookup in the local Windows environment does not prove that a domain publishes no configuration. Deployment verification separately checks the actual hosting server.

## Generic domain templates (0.6.2-dev)
After published Autoconfig/ISPDB/SRV sources and recognized-provider MX lookup fail, try mail.DOMAIN, imap.DOMAIN + smtp.DOMAIN, and DOMAIN itself. Candidates require public DNS and valid TLS certificates; IMAP 993 must greet as IMAP, SMTP 465/587 must answer EHLO and advertise AUTH after TLS. Port 587 requires STARTTLS with a second EHLO. No username, password, AUTH, message envelope or DATA is sent during the probe. The template is recomputed per domain; another company's hosting server or credentials are never copied. Template probing has a six-second scheduling budget plus DNS resolver timing and in-flight reads. Tests use multiple synthetic corporate domains and simulated sockets/TLS outcomes, not live mailbox authentication.
