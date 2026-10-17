# Directory, routing and provider discovery (0.6.0-dev)

Internal recipients accept an exact unique username without a domain, or an internal address. Authenticated employee/admin directory searches return at most 20 active matches (username, display name, job title, department); two characters are required, wildcard characters are literal, and no passwords or external-account configs are returned. Duplicate display names are allowed; usernames remain unique. UI suggestions show the job and department but submit the canonical username. Typed names are never silently guessed as a recipient.

Admin user creation/editing includes job title and department. Route supervision is /admin/?page=routes (latest 100 decisions, no subjects/bodies/passwords). Both employee internal composition and the connected external mailbox composer use the server-side router:
- Bare usernames resolve locally.
- Email addresses in the configured company domain resolve to an active user's internal address or uniquely assigned mailbox alias.
- Unknown, disabled or ambiguous company destinations are blocked without SMTP fallback.
- Other email domains use SMTP and require the existing external-send permission.
- Local delivery lands in Correo del equipo, not the provider's IMAP inbox. External composer keeps its existing local Sent copy. Admin-only identities cannot masquerade as employees; use an employee identity for local delivery.
- This is an application submission router. Messages sent by another SMTP client or arriving from the Internet need routing rules in the actual MTA/hosting control panel; this application cannot intercept those sockets.

Provider detection uses exact known Gmail/Googlemail, Outlook/Hotmail/Live/MSN, Zoho domains, or allowlisted provider MX suffixes for hosted company domains. No passwords are sent during discovery, no arbitrary autodiscover URL is fetched and no mail.domain hostname is guessed. TLS verification remains mandatory.
- Gmail/Workspace: imap.gmail.com / smtp.gmail.com. App password may be needed and available depending on account policy; OAuth is recommended but not integrated.
- Outlook/Microsoft 365: known hosts/587 are detected; auto-save explicitly reports that OAuth must be integrated, rather than trying a normal password.
- Zoho: personal vs domain organization and supported datacenter region profiles. Organization/free plan/account-specific servers may require advanced correction.
- Company: /admin/?page=settings stores an explicit shared server profile with no credentials. It takes precedence for the configured company domain.
- Unknown providers retain advanced manual setup. Previously saved account configuration is reused; no credential is borrowed from another user.

Official references checked during implementation:
https://support.google.com/mail/answer/7126229
https://support.microsoft.com/en-us/outlook/pop-imap-and-smtp-settings-for-outlook-com
https://www.zoho.com/mail/help/imap-access.html

Tests use synthetic SQLite users, protocol fakes and local HTTP fixtures. They do not prove actual Gmail/Zoho account authentication and do not send real email.
