#!/usr/bin/env bash
set -euo pipefail
# Configuration: set your webroot and SSH key path
WEBROOT="/var/www/exit473"   # <-- change this to your actual webroot on the server
SSH_KEY="$HOME/.ssh/exit473_deploy"  # <-- path to your deploy private key

export GIT_SSH_COMMAND="ssh -i \"$SSH_KEY\" -o StrictHostKeyChecking=no"

cd "$WEBROOT"

# Fetch and reset to remote main branch to ensure exact copy of repo
git fetch origin main
git reset --hard origin/main

echo "Update complete: $(date -u +%Y-%m-%dT%H:%M:%SZ)"
