#!/usr/bin/env bash
# Rotate the trusted Fleet Manager mTLS client cert on a CRM droplet.
# Validates the input PEM, writes it atomically to the nginx bind-mount,
# reloads nginx, and appends a rotation record to the host-side audit log.
# Used during break-glass FM compromise recovery and for routine rotation.
# See docs/runbooks/fm-compromise-recovery.md and docs/runbooks/fleet-manager-cert-paste.md.

set -euo pipefail

readonly CERT_DIR="/opt/nonprofitcrm/nginx-certs"
readonly CERT_PATH="${CERT_DIR}/fm-client.crt"
readonly TMP_PATH="${CERT_DIR}/.fm-client.crt.new"
readonly LOG_DIR="/opt/nonprofitcrm/logs"
readonly LOG_PATH="${LOG_DIR}/fm-cert-rotations.log"
readonly NGINX_CONTAINER="nonprofitcrm_nginx"

usage() {
  cat >&2 <<EOF
Usage: $0 <path-to-new-cert.pem>

Rotates the trusted Fleet Manager mTLS client cert. The input file must
contain a single PEM-encoded X.509 certificate (the public cert from FM,
beginning '-----BEGIN CERTIFICATE-----').

Requires write access to ${CERT_DIR} and permission to invoke docker exec
against the ${NGINX_CONTAINER} container (typically: run with sudo).
EOF
  exit 2
}

[ $# -eq 1 ] || usage
input="$1"
[ -f "$input" ] || { echo "error: input file not found: $input" >&2; exit 2; }

cleanup() { rm -f "$TMP_PATH"; }
trap cleanup EXIT

cp "$input" "$TMP_PATH"
chmod 644 "$TMP_PATH"

if ! openssl x509 -in "$TMP_PATH" -noout -text >/dev/null 2>&1; then
  echo "error: input is not a valid X.509 certificate (openssl x509 parse failed)" >&2
  exit 1
fi

fingerprint="$(openssl x509 -in "$TMP_PATH" -noout -fingerprint -sha256 | sed 's/^.*=//')"
subject="$(openssl x509 -in "$TMP_PATH" -noout -subject | sed 's/^subject= *//')"

mv "$TMP_PATH" "$CERT_PATH"
trap - EXIT

if ! docker exec "$NGINX_CONTAINER" nginx -s reload; then
  echo "error: nginx reload failed; cert is in place at $CERT_PATH but nginx may still be using the previous trust" >&2
  echo "       verify config with: docker exec $NGINX_CONTAINER nginx -t" >&2
  exit 1
fi

mkdir -p "$LOG_DIR"
ts="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
printf '%s  fingerprint=%s  subject=%s\n' "$ts" "$fingerprint" "$subject" >> "$LOG_PATH"

echo "Rotated FM client cert."
echo "  cert:        $CERT_PATH"
echo "  fingerprint: $fingerprint"
echo "  subject:     $subject"
echo "  audit:       $LOG_PATH"
