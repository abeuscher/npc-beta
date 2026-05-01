---
title: Fleet Manager — Compromise Recovery
description: Operator workflow for recovering from a Fleet Manager compromise — break-glass cert swap across the fleet.
updated: 2026-05-01
tags: [fleet-manager, mtls, ops, recovery, security]
category: runbook
---

# Fleet Manager — Compromise Recovery

This runbook covers recovery from a Fleet Manager compromise: an operator-initiated cert swap across every CRM in the fleet, replacing trust in the compromised FM with trust in a pre-generated break-glass cert held offline. It applies to v2.1.0+ of the [Fleet Manager agent contract](../fleet-manager-agent-contract.md), which establishes the per-CRM trust-one-cert mTLS model.

This runbook is for a fleet-wide compromise event. For routine cert rotation (FM-driven, scheduled), see [fleet-manager-cert-paste.md](fleet-manager-cert-paste.md) instead.

## Threat model and scope

Each CRM install trusts exactly one FM-side cert. If FM's filesystem, database, or admin session is compromised, an attacker may be able to authenticate to every CRM in the fleet using the (compromised) FM-held keypair. The recovery model is **operator-driven cert swap**: the operator visits each CRM out-of-band (SSH), swaps the trusted cert to a pre-generated break-glass cert kept offline, then provisions a clean FM that holds the break-glass keypair.

The break-glass cert is never on FM's filesystem during normal operation — it is generated offline at install time and kept in cold storage (encrypted offline drive, hardware token, sealed paper — operator's choice). FM's compromise therefore does not expose the break-glass keypair, and trust can be re-established from a clean FM.

## Detection signals

The runbook fires when FM's trust posture is suspect. The triggering event determines urgency, not the procedure — once you decide to recover, the steps below are the same.

- **FM admin credentials confirmed compromised** — lost laptop with an active FM session, phishing succeeded, password leak. Treat any of these as compromise; an attacker with admin credentials can issue or rotate certs.
- **FM database or filesystem exfiltration confirmed or suspected** — backup logs show unauthorized access, hosting-provider security alert, IoCs on the FM host. The encrypted client keypairs live on FM's filesystem; exfiltration plus the master key (off-filesystem per FM's posture) compromises every CRM keypair.
- **Unauthorized SSH or console access to FM** — host audit log shows anomalous sessions. Even if no exfiltration is confirmed, treat as compromise.
- **Unexpected polling source identified** — FM's audit sink shows a poll dispatched against a CRM from a host that is not the legitimate FM. Implies FM keypair use by an attacker.

## Pre-installation — generate the break-glass cert

Performed once per CRM install at install time, before the install joins the fleet. **Do not skip this step** — without a break-glass cert in cold storage, recovery from FM compromise becomes significantly harder (manual cert generation under time pressure, while the fleet is potentially actively compromised).

1. **Generate the break-glass keypair offline.** On an air-gapped or otherwise trusted machine that is NOT FM:

   ```bash
   openssl req -x509 -newkey rsa:2048 -days 3650 -nodes \
     -keyout breakglass-fm-client.key \
     -out   breakglass-fm-client.crt \
     -subj  '/CN=fm-breakglass-<install-name>'
   ```

   Replace `<install-name>` with a unique identifier per install. The 10-year expiry matches FM's normal cert lifetime and avoids cold-storage cert expiry surprises.

2. **Store the keypair in cold storage.** Move both files (`.key` and `.crt`) to whichever cold-storage medium the operator prefers — encrypted offline drive (LUKS, VeraCrypt, FileVault image), USB drive in a sealed envelope, printed `gpg -ac`-armoured copy in a safe. The cold-storage discipline applies to the `.key` (private) only; the `.crt` (public) can also live alongside install-time docs.

3. **Verify the cold-storage copy is recoverable.** Test-restore the cold-storage copy to a clean machine, run `openssl x509 -in breakglass-fm-client.crt -noout -text` to confirm parse, then re-seal. A copy that cannot be recovered defeats the purpose.

4. **Note the cert fingerprint.** Record `openssl x509 -in breakglass-fm-client.crt -noout -fingerprint -sha256` somewhere accessible during recovery (operator notebook, password manager, install-time runbook). The fingerprint lets you verify the cert in cold storage matches what was deployed at install time, in case multiple break-glass certs are ever staged.

## At recovery time

Performed when one of the detection signals fires. Procedure assumes a fleet of one or more CRM installs, each with a pre-generated break-glass cert in cold storage per the install-time procedure above.

### 1. Triage and decision to recover

Recovery is fleet-wide and operationally heavy — every CRM needs an operator visit. Before initiating, confirm the compromise is real:

- For credential compromise: rotate the operator's FM admin credentials, terminate active sessions, audit FM admin action log for unauthorized actions. If no unauthorized actions are present, recovery may not be required.
- For exfiltration: confirm the exfiltration scope. If only public assets were taken, recovery is not required. If FM's keypair store was in scope, recovery is required.
- For unauthorized polling: confirm the polling source. Misconfigured legitimate-FM polling does not require recovery.

If recovery IS required, proceed with the steps below. The fleet remains operational during recovery (CRMs continue serving polls against the old trust until their cert is swapped); the goal is to swap every CRM's trust before the attacker can act on the compromised keypair.

### 2. Retrieve the break-glass cert (public)

Pull each install's `breakglass-fm-client.crt` (public cert) from its install-time storage location. Do not retrieve the private keys yet — those stay in cold storage until step 4.

### 3. Per-CRM cert swap

For each CRM install, in parallel if multiple operators are available:

1. **SSH to the CRM droplet.**

   ```bash
   ssh operator@<crm-droplet-host>
   ```

2. **Stage the break-glass public cert in a temp file.** Paste the PEM block into a tmp file:

   ```bash
   nano /tmp/breakglass-fm-client.crt
   # paste the PEM, save, exit
   ```

3. **Run the rotation script.**

   ```bash
   sudo /opt/nonprofitcrm/bin/rotate-fm-cert.sh /tmp/breakglass-fm-client.crt
   ```

   The script validates the cert with `openssl x509`, atomically replaces `/opt/nonprofitcrm/nginx-certs/fm-client.crt`, reloads nginx (graceful, no connection drop), and appends a rotation record to `/opt/nonprofitcrm/logs/fm-cert-rotations.log`. Verify the script's final fingerprint output matches the fingerprint recorded at install time.

4. **Clean up the temp file.**

   ```bash
   rm /tmp/breakglass-fm-client.crt
   ```

5. **Verify the swap.** From the droplet host or a workstation:

   ```bash
   curl https://<crm-droplet>/api/health
   ```

   Expects HTTP `403` (no client cert presented — strict mTLS gate is responding). If it returns `200`, nginx may not have reloaded; check `docker exec nonprofitcrm_nginx nginx -t` and the script's exit code.

After every CRM in the fleet is swapped, no CRM trusts the compromised FM keypair. The compromised keypair is now useless against the fleet.

### 4. Provision clean FM with break-glass keypair

This step is FM-side; the CRM-side procedure is complete after step 3. The high-level shape:

1. Provision a clean FM install per FM-side procedures. Bring up FM's database, admin auth, etc.
2. Retrieve break-glass private keys from cold storage, transfer to the clean FM install via a secure channel (operator-typed paste over SSH is fine; do not transmit through the compromised FM).
3. Configure clean FM to poll each CRM using its corresponding break-glass keypair.
4. Verify polls succeed (FM dashboard shows green status across the fleet).

See FM-side recovery documentation for the authoritative version of step 4.

### 5. Re-establish the cold-storage invariant

The break-glass certs that were just swapped into active use are no longer offline — their private keys are now on the clean FM. Restore the invariant:

1. For each install, repeat the [Pre-installation — generate the break-glass cert](#pre-installation--generate-the-break-glass-cert) procedure to produce a NEW break-glass keypair.
2. Store the new keypair in cold storage.
3. Schedule a routine rotation per [fleet-manager-cert-paste.md](fleet-manager-cert-paste.md) for each CRM, swapping the (now-active) break-glass cert for a fresh-FM-issued cert. This restores the "break-glass cert is offline" invariant.

Step 3 can be deferred — running indefinitely with the break-glass cert as the active cert is operationally fine, but it leaves you without an offline backup against the next compromise. Plan the rotation within a reasonable window post-recovery.

## Post-recovery

- **Audit log review.** Walk `/opt/nonprofitcrm/logs/fm-cert-rotations.log` on each CRM to confirm the rotation event was recorded at the expected timestamp with the expected fingerprint. Anomalous log entries (a rotation happened that you did not perform) indicate the recovery was already too late and the attacker had operator-level CRM access; treat as a separate incident.
- **Compromise post-mortem.** Determine how the compromise occurred and harden against recurrence. Out of scope for this runbook.
- **Fleet inventory check.** Confirm every CRM in your install inventory was visited. A CRM whose break-glass cert was not swapped is still trusting the compromised FM.

## Troubleshooting

- **Rotation script fails with "openssl x509 parse failed."** The pasted PEM is malformed. Re-paste from cold storage; ensure the paste includes the full `-----BEGIN CERTIFICATE-----` and `-----END CERTIFICATE-----` lines and no surrounding whitespace.
- **Rotation script succeeds but `nginx -s reload` fails.** The cert is in place but nginx may not have picked it up. Check `docker exec nonprofitcrm_nginx nginx -t` for config syntax; if it reports OK, retry the reload manually: `docker exec nonprofitcrm_nginx nginx -s reload`. As a fallback, restart the container: `docker compose -f /opt/nonprofitcrm/docker-compose.prod.yml restart nginx`.
- **Post-swap, FM polls return `400` ("SSL certificate error").** The cert pasted on the CRM does not match the keypair the clean FM is using. Verify the fingerprint on the CRM (`docker exec nonprofitcrm_nginx cat /etc/nginx/certs/fm-client.crt | openssl x509 -noout -fingerprint -sha256`) matches the public cert paired with the private key on FM.
- **Post-swap, FM polls return `403` (no client cert presented).** FM is reaching nginx but not presenting a cert. Check FM-side `HealthClient` config — Guzzle's `cert` and `ssl_key` request options must be set to the break-glass keypair.
- **A CRM was missed during the swap.** That CRM still trusts the compromised FM. Visit it immediately and run the swap procedure. Until the swap, treat the missed CRM as compromised.
