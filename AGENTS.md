# TNL Mail development workflow

This repository serves the user's private test installation at https://cache.lat/tnlmaildev/ from branch develop.

After user-authorized application changes intended for this test site:
1. Run relevant PHP tests and syntax checks.
2. Run `php scripts/build-manifest.php VERSION` and commit the manifest with the source. See docs/DEPLOYMENT.md.
3. Publish to develop and verify the exact remote commit SHA.
4. If the deployment MCP is installed and its scoped deployment credential is available, use github_preview followed by github_deploy for that exact commit, then verify deploy_status, system_status and the browser login page. The user requested this publish-then-update workflow to avoid manual ZIP uploads.
5. If the MCP is not activated or authentication is unavailable, report the precise remaining step. Never claim the site is updated based only on a GitHub push or local tests.

The existing diagnostic credential is read-only. Never store credentials in Git or expand its scope. Use the separate deployment credential. A rollback restores SQLite and discards subsequent writes: obtain user authorization unless those writes were explicitly designated disposable. Updater core and infrastructure are outside normal deployment; see docs/DEPLOYMENT.md.
