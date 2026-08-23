# AfyaDesk Production Hardening

This project adds AfyaDesk user-access and WhatsApp notification tooling on top of stable GLPI.

## Console Commands

- `php bin/console afyadesk:seed-service-areas --allow-superuser`
- `php bin/console afyadesk:seed-access-levels --allow-superuser`
- `php bin/console afyadesk:seed-whatsapp-templates --allow-superuser`
- `php bin/console afyadesk:import-users users.csv --allow-superuser`
- `php bin/console afyadesk:export-user-access-audit --allow-superuser`

## Browser Pages

- `/front/afyadesk.user_import.php`
- `/front/afyadesk.whatsapp_status.php`
- `/front/notificationwhatsappsetting.form.php`

## WhatsApp Environment Variables

The UI/database settings still work, but production can override secrets with:

- `AFYADESK_WHATSAPP_API_URL`
- `AFYADESK_WHATSAPP_PHONE_NUMBER_ID`
- `AFYADESK_WHATSAPP_ACCESS_TOKEN`
- `AFYADESK_WHATSAPP_TEMPLATE_NAME`
- `AFYADESK_WHATSAPP_TEMPLATE_LANGUAGE`

## Local Deployment Helper

Run `powershell -ExecutionPolicy Bypass -File tools\deploy-afyadesk-local.ps1` to backup, seed, clear cache, and health check.
