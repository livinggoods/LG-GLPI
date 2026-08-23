# AfyaDesk Release Flow

1. Run `powershell -File tools/afyadesk-local-health.ps1` and fix any failed checks.
2. Run `powershell -File tools/backup-afyadesk-local.ps1` before database or deployment work.
3. Run `powershell -File tools/check-afyadesk-stable-base.ps1`.
4. If the base reports a development version, rebase the AfyaDesk commits onto the latest stable GLPI tag before production deployment.
5. Verify the local UI with `node tools/visual-regression-afyadesk.js`.
6. Open a pull request from the feature branch.
7. Test login, Helpdesk, Service catalog, Users, Assets, notification setup, and WhatsApp test delivery.
8. Back up production.
9. Merge the PR.
10. Deploy and run `php bin/console database:enable_timezones` if GLPI reports timezone support is disabled. On the local Windows setup, run `powershell -File tools/seed-afyadesk-mysql-timezones.ps1` first if MySQL timezone tables are empty.
