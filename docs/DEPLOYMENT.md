# MCP deployment from develop

The deployment feature is opt-in. The existing diagnostic MCP token cannot write files. An administrator enables deployments and creates a separate token at `public/deployment.php`, after confirming their password. Store tokens outside Git. HTTPS is required remotely.

The repository and branch are fixed in code: `Gc2oseas/tnlmail`, `develop`. This implementation supports a public repository using unauthenticated GitHub HTTPS requests; private repositories require a separate credentials design.

## Release workflow

1. Modify and test the application.
2. Run `php scripts/build-manifest.php VERSION` and commit the resulting `tnl-update.json` together with the source changes. Use a new version, for example `0.2.3-dev`.
3. Push to `develop` and read the full commit SHA back from GitHub.
4. Authenticate to `public/mcp.php` with the deployment token. Call `github_preview` with `{ "commit": "FULL_40_CHARACTER_SHA" }`.
5. Call `github_deploy` with the returned `ticket` and the same `commit`. The preview expires after 30 minutes and deployment rejects a changed develop head.
6. Verify `deploy_status`, `system_status`, and a real browser request to the login page. Deployment itself verifies file hashes and SQLite integrity; these are not browser or SMTP functional tests.

The server downloads the manifest and source files directly from that immutable GitHub commit and builds the ZIP internally. There is no manually uploaded ZIP for subsequent normal application releases. Hashes provide consistency, not an independent release signature. Whoever can push to develop and holds the deployment token can execute application code on the hosting account.

The fixed updater core (`app/Update/Engine.php`, `public/updates.php`), web server configuration, install script, documentation, tests and storage are outside normal deployment. Updating the updater core or changing infrastructure requires a separately prepared maintenance operation. Files removed from the repository are not automatically removed from the server by this version.

## Operations and recovery

`github_preview` downloads without modifying installed application files or SQLite. `github_deploy` checks the head again, enters maintenance, calls the backed-up updater, records commit metadata and leaves maintenance. Regular browser requests return 503 while the maintenance marker exists; MCP and the independent updater remain accessible. Already-running requests must finish before deployment; this is intended for the current test environment.

`deploy_status` reports version, commit, maintenance and last backup ID. It is also available to the read-only token.

To roll back, call `github_rollback` with `backup` and `confirm_data_loss: true`. This restores SQLite and discards writes after the snapshot. Obtain explicit authorization before discarding data unless the user has identified those writes as disposable test data. Token hashes and metadata revert with SQLite. The web updater at `public/updates.php` remains the fallback.

Only one deployment runs at a time. If the PHP worker is interrupted, the last journal may remain `applying`; restore that backup before retrying. Maintenance stays enabled when recovery is incomplete. After a successful fallback restoration via updates.php, remove `storage/deploy-maintenance` using the hosting file manager if it remains. Keep an external backup. Runtime limits, filesystem permission changes, disk failures and concurrent requests can still require manual recovery.

Required: PHP 8.3+, PDO SQLite, ZIP, OpenSSL, `allow_url_fopen`, outbound HTTPS to api.github.com and raw.githubusercontent.com, protected writable storage and writable application directories. GitHub rate limits and hosting request timeouts can interrupt a preview; it does not modify installed code.

## One-time activation from 0.2.1-dev

Install `03-TNL-Mail-MCP-GitHub-0.2.2-update.zip` through `public/updates.php`, using its SHA-256. The existing updater cannot bootstrap remote writes before that code is installed.

Then visit `public/deployment.php`, sign in as administrator and select **Habilitar y generar token**. This is a new credential, displayed once. The initial package does not enable deployments automatically or embed a token. Configure the MCP client to use the deployment token when updating this site; retain the read-only token for diagnostic access.
