# Mail dispatch and fixed account actions

A fixed right-hand account bar provides profile settings, lock/setup PIN and CSRF-protected logout. On narrow screens it becomes a bottom bar; it is hidden inside compose dialogs. Lock uses the existing device PIN when configured; otherwise it opens PIN setup. Logout revokes this remembered device.

The internal composer now uses the same routing service as external composition. Local users/addresses route to SQLite. External destinations require the user's can_send_external permission and an authorized selected primary, assigned or personal SMTP account. The former screen hard-coded SMTP off. Exact active internal addresses are recognized even after a company-domain change.

To and CC accept comma/semicolon lists (up to 50 recipients). All routes, account availability and permissions are validated before delivery; alias duplicates are removed. Local recipients receive separate protected copies with To/CC metadata. SMTP recipients share one send with To/CC envelope/headers; locally routed recipients are excluded from that envelope. SMTP failure can coexist with successful internal delivery, and results report each recipient individually. A durable sender-scoped request key prevents automatic replay, including interrupted attempts.

Internal messages can be forwarded through the modal with their body prefilled. Send/forward history is scoped to the sender and includes per-recipient status; SMTP accepted is not final delivery confirmation. This history is a delivery log, not a provider inbox or reusable mailing-list feature.

Validation: PHP regression suites, simulated SMTP multi-recipient and CC MIME checks, and browser testing from a fictitious employee account forwarding to two local recipients plus a simulated external recipient. No real user mail was sent during this change.
