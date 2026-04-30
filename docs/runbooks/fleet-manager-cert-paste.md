---
title: Fleet Manager — Cert Paste & Rotation
description: Operator workflow for installing the Fleet Manager mTLS client cert on a CRM droplet and rotating it later.
updated: 2026-04-30
tags: [fleet-manager, mtls, ops]
category: runbook
---

# Fleet Manager — Cert Paste & Rotation

This runbook covers installing the Fleet Manager mTLS client cert on a CRM droplet for the first time, and rotating it later. It applies to v2.0.0+ of the Fleet Manager agent contract — the cutover from bearer auth to mTLS landed at CRM session 248.

The contract is governed by [`docs/fleet-manager-agent-contract.md`](../fleet-manager-agent-contract.md). FM owns the keypair (private key never leaves FM); the CRM holds only the public cert.

## Where the cert lives

- **Bind-mount source on the host:** `/opt/nonprofitcrm/nginx-certs/fm-client.crt`
- **In-container path nginx reads:** `/etc/nginx/certs/fm-client.crt` (mapped read-only by `docker-compose.prod.yml`)
- **Trusted by:** the production nginx config at `docker/nginx/prod.conf`, via `ssl_client_certificate /etc/nginx/certs/fm-client.crt;`

The directory is operator-managed — it is not part of any Docker image artifact, and it is not provisioned by `docker compose up`. The operator MUST populate it before the first nginx start, or nginx fails to load the config.

## First-time install

Performed once per droplet, at install time, before the FM operator schedules the install for polling.

1. **Get the cert from FM.** Copy the public cert blob from FM's admin UI for this client (FM's `Client` row → "View cert" action). It is a PEM block beginning `-----BEGIN CERTIFICATE-----` and ending `-----END CERTIFICATE-----`. The FM-side private key never leaves FM.
2. **Create the bind-mount directory and paste the cert:**
   ```bash
   sudo mkdir -p /opt/nonprofitcrm/nginx-certs
   # paste the PEM block from FM into /opt/nonprofitcrm/nginx-certs/fm-client.crt
   sudo chmod 644 /opt/nonprofitcrm/nginx-certs/fm-client.crt
   ```
   The cert file must be world-readable so the nginx container's UID can read it through the bind-mount. The cert is a public artifact; world-read does not leak anything sensitive.
3. **Bring up nginx:**
   ```bash
   sudo docker compose -f /opt/nonprofitcrm/docker-compose.prod.yml up -d
   ```
   On a fresh install, this is part of the standard startup. On an existing install, restart nginx so it picks up the new cert: `sudo docker compose -f /opt/nonprofitcrm/docker-compose.prod.yml restart nginx`.
4. **Verify from FM.** The FM-side smoke-test command (`php artisan fleet:smoke-test https://this-droplet/api/health` or equivalent) should hit the endpoint and receive a 200 with the v2.0.0 JSON envelope. If FM gets `400 Bad Request` ("SSL certificate error"), the cert pasted does not match the keypair FM holds — re-copy from FM's admin UI and try again. If FM gets `403 Forbidden`, the cert is correctly trusted but the request did not present it (FM-side config issue — `HealthClient` is not passing the cert/key to Guzzle).

## Rotation

Performed when FM has generated a new keypair for this client (e.g., periodic rotation, key compromise suspected, or FM-side cert hygiene).

1. Generate the new keypair on FM (FM's `Client` row → "Rotate keypair" action). FM displays the new public cert.
2. Paste the new cert over the existing file:
   ```bash
   # paste the new PEM block into /opt/nonprofitcrm/nginx-certs/fm-client.crt (overwriting)
   sudo chmod 644 /opt/nonprofitcrm/nginx-certs/fm-client.crt
   ```
3. Restart nginx to pick up the new trust:
   ```bash
   sudo docker compose -f /opt/nonprofitcrm/docker-compose.prod.yml restart nginx
   ```
4. Verify from FM. The next poll should return 200. There is a brief polling gap during the restart — typically a few seconds.

The old cert stops working immediately at restart. There is no overlap window — FM should hold the new keypair before the operator pastes, and the operator should paste only after FM has switched.

## Verification commands

These confirm that mTLS is enforced and cert trust is correct. Run them from the droplet host (or a workstation with curl).

- **Cert presented and trusted (success):**
  ```bash
  curl --cert path/to/fm-client.crt --key path/to/fm-client.key https://<droplet>/api/health
  ```
  Expects 200 with the v2.0.0 JSON envelope.
- **No cert presented:**
  ```bash
  curl https://<droplet>/api/health
  ```
  Expects HTTP `403 Forbidden` from nginx (HTML body, not JSON). The TLS handshake completes — `ssl_verify_client optional` does not reject the connection just because a cert is missing — and then nginx's per-location strict gate returns 403.
- **Wrong cert presented:**
  Use any other self-signed cert/key pair. Expects HTTP `400 Bad Request` from nginx, body "The SSL certificate error".
- **Public route still serves without a cert (sanity check):**
  ```bash
  curl -I https://<droplet>/
  ```
  Expects 200 (or whatever the home page emits) — mTLS must be `/api/health`-scoped, not site-wide.

## Troubleshooting

- **nginx fails to start with `BIO_new_file()` or `cannot load certificate` error.** The bind-mount target is empty or the cert file is unreadable. Confirm `/opt/nonprofitcrm/nginx-certs/fm-client.crt` exists, is non-empty, and has mode `644`.
- **All polls from FM return 403.** FM is reaching nginx but not presenting a cert. Check FM-side `HealthClient` config — Guzzle's `cert` and `ssl_key` request options must be set to the FM-held cert + private key for this client.
- **All polls from FM return 400 ("SSL certificate error").** FM is presenting a cert, but the cert does not match the one trusted at `/opt/nonprofitcrm/nginx-certs/fm-client.crt`. Re-paste from FM's admin UI ("View cert" → copy → paste-and-restart).
- **All polls fail before any HTTP response (TLS handshake error).** Server-side TLS is broken — the droplet's Let's Encrypt cert may have expired, or nginx may not be running. Check the droplet's TLS state independently of mTLS.
- **Polls intermittently fail.** Could be rate-limit (60 RPM cap at the application layer — FM should back off) or transient network. Not a cert issue if some polls succeed.
