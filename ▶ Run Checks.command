#!/bin/bash
#
# Run Checks — double-click this file in Finder.
#
# Runs every automated check and leaves the results on screen. Starts the preview server
# and a headless Chrome if they aren't already running, and stops only what it started.
#
# Add nothing, install nothing: the browser tests use Node's built-in WebSocket.

cd "$(dirname "$0")" || {
  echo "Could not find the project folder. Has this file been moved?"
  read -r -p "Press Return to close. "
  exit 1
}

# Finder-launched scripts get a minimal PATH, so Homebrew and Node need locating.
export PATH="/opt/homebrew/bin:/usr/local/bin:$HOME/.local/bin:$PATH"

printf '\033[1m  SABIN Gallery Row — verification\033[0m\n'
printf '  %s\n' "$(pwd)"

if ! command -v php >/dev/null 2>&1; then
  printf '\n\033[31m  PHP is not installed.\033[0m  Run:  brew install php composer\n\n'
  read -r -p "  Press Return to close. "
  exit 1
fi

# Browser suites are optional — without Node the PHP checks still run and report.
ARGS=""
if ! command -v node >/dev/null 2>&1; then
  printf '\n\033[33m  Node not found — running the PHP checks only.\033[0m\n'
  ARGS="--quick"
fi

bin/verify $ARGS
STATUS=$?

printf '\n'
if [ $STATUS -eq 0 ]; then
  printf '\033[32m  Everything passed.\033[0m\n'
else
  printf '\033[31m  Something failed — the detail is above.\033[0m\n'
fi

printf '\n  Also worth reading:\n'
printf '    README.md                       measured numbers and open questions\n'
printf '    docs/craft-field-definitions.md what Foster receives\n\n'

# Keep the window open so results are readable after a double-click.
read -r -p "  Press Return to close. "
