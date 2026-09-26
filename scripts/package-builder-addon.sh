#!/usr/bin/env bash
#
# Build the Builder addon's Vite bundle and stash it as a single zip in
# Modules/Builder/Resources/dist/build.zip — replacing any previous zip.
#
# After this, manually zip Modules/Builder/ for distribution.
#
# Prerequisites: bash, npm + node, zip.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$PROJECT_ROOT"

MODULE_DIR="Modules/Builder"
DIST_DIR="$MODULE_DIR/Resources/dist"
DIST_ZIP="$DIST_DIR/build.zip"

echo "[1/2] Production build (npm run build:prod)"
npm run build:prod

if [[ ! -d public/build ]]; then
    echo "[error] public/build/ doesn't exist after build — abort."
    exit 1
fi

echo "[2/2] Zipping public/build → $DIST_ZIP"
rm -rf "$DIST_DIR"
mkdir -p "$DIST_DIR"
# Zip from inside public/build so the archive root is manifest.json /
# assets/, not "build/manifest.json". Excludes Vite's internal cache.
(cd public/build && zip -rq "../../$DIST_ZIP" . -x '.vite/*')

echo
echo "Done."
echo "  Output: $DIST_ZIP"
echo "  Size:   $(du -h "$DIST_ZIP" | awk '{print $1}')"
echo "Ready to zip Modules/Builder/ for distribution."
