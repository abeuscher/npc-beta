#!/usr/bin/env bash
# Generate a self-signed server TLS cert for local nginx (localhost).
# Run once per fresh checkout. The cert and key live under nginx-certs/
# and are gitignored. The placeholder client trust cert at
# nginx-certs/fm-client.crt is committed and untouched by this script.

set -euo pipefail

cd "$(dirname "$0")/.."

if [ -f nginx-certs/localhost.crt ] && [ -f nginx-certs/localhost.key ]; then
  echo "nginx-certs/localhost.{crt,key} already exist — leaving alone."
  exit 0
fi

mkdir -p nginx-certs

openssl req -x509 -newkey rsa:2048 -days 3650 -nodes \
  -keyout nginx-certs/localhost.key \
  -out   nginx-certs/localhost.crt \
  -subj '/CN=localhost'

chmod 600 nginx-certs/localhost.key
chmod 644 nginx-certs/localhost.crt

echo "Generated nginx-certs/localhost.{crt,key}."
