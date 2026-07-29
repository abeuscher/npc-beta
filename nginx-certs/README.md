# nginx-certs

Certificates for the local nginx container. Nothing in this folder is a secret.

| File | What it is | Committed? |
|---|---|---|
| `fm-client.crt` | **Placeholder, not a credential** — a self-signed dummy (`CN = placeholder-do-not-trust`). It exists only because nginx's `ssl_client_certificate` directive needs a file present to boot the mTLS server block that guards the Fleet Manager `/api/*` endpoints. Locally those endpoints simply reject all callers, which is the intended dev behavior. | yes |
| `localhost.crt` / `localhost.key` | Self-signed server TLS pair for local HTTPS. Generated once per fresh checkout by `bin/dev-certs.sh`. | no (gitignored) |

**Production never uses this folder.** Live nodes bind-mount `/opt/nonprofitcrm/nginx-certs` from the host, where the *real* Fleet Manager client certificate is provisioned (and rotated via `bin/rotate-fm-cert.sh`). The repo copy of `fm-client.crt` never reaches a server.

Fresh checkout setup: run `bin/dev-certs.sh` once, then start the stack.
