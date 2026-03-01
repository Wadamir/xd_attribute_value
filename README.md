# XD Attribute Value

## Local Mail Catcher

The `mailpit` service is configured in `docker_files/docker-compose.yml`.
- Mail web UI: `http://localhost:8025`
- SMTP server for the app: `mailpit:1025`

In ocStore Admin (`System → Settings → Server → Mail`), use:
- `Mail Engine`: `smtp`
- `SMTP Hostname`: `mailpit`
- `SMTP Port`: `1025`
- `SMTP Username` / `SMTP Password`: leave empty
- `SMTP Timeout`: `5`
