#!/bin/bash
#
# Preview Gallery — double-click this file in Finder.
#
# Opens the gallery in your browser and leaves a Terminal window running the local
# server. Close that window, or press Ctrl-C in it, to stop.
#
# The .command extension is what makes it double-clickable on macOS. Finder launches it
# from your home folder rather than this one, so the first thing it does is move to its
# own directory — everything is quoted because this path contains spaces.

cd "$(dirname "$0")" || {
  echo "Could not find the project folder. Has this file been moved?"
  read -r -p "Press Return to close. "
  exit 1
}

PROJECT="$(pwd)"

printf '\033[1m  SABIN Gallery Row — local preview\033[0m\n'
printf '  %s\n\n' "$PROJECT"

# --- prerequisites ----------------------------------------------------------
# Finder-launched scripts get a minimal PATH, so Homebrew's location is added
# explicitly rather than assumed.
export PATH="/opt/homebrew/bin:/usr/local/bin:$HOME/.local/bin:$PATH"

if ! command -v php >/dev/null 2>&1; then
  printf '\033[31m  PHP is not installed.\033[0m\n\n'
  echo "  Open Terminal and run:"
  echo "      brew install php composer"
  echo
  read -r -p "  Press Return to close. "
  exit 1
fi

# --- find a free port -------------------------------------------------------
# 8080 is the documented default, but something else may already have it — falling
# back beats failing with "address already in use".
PORT=""
for candidate in 8080 8081 8082 8083 8090; do
  if ! lsof -nP -iTCP:"$candidate" -sTCP:LISTEN >/dev/null 2>&1; then
    PORT="$candidate"
    break
  fi
done

if [ -z "$PORT" ]; then
  printf '\033[31m  Ports 8080-8090 are all in use.\033[0m\n'
  echo "  A preview may already be running — check your other Terminal windows."
  echo
  read -r -p "  Press Return to close. "
  exit 1
fi

[ "$PORT" != "8080" ] && printf '  (8080 was busy, using %s)\n\n' "$PORT"

URL="http://localhost:${PORT}"

# --- open the browser once the server actually answers ----------------------
# Backgrounded so it can wait while the server starts up in the foreground. The first
# run builds the stylesheet and prepares media, so this can take a few seconds.
(
  for _ in $(seq 1 90); do
    if curl -sf -m 1 "$URL/" >/dev/null 2>&1; then
      open "$URL/"
      exit 0
    fi
    sleep 0.5
  done
  printf '\n\033[33m  The server did not answer in 45s. Open %s manually.\033[0m\n' "$URL"
) &

# printf, not a heredoc: `cat` prints escape sequences literally, so the bold markers
# showed up as raw \033[1m in the window.
bold() { printf '\033[1m%s\033[0m\n' "$1"; }

printf '  Opening %s in your browser...\n\n' "$URL"

bold '  Things to try — paste onto the end of the address'
cat <<'OPTIONS'
    ?layout=masonry              staggered rows instead of a strict grid
    ?gap=16                      wider gutter between tiles
    ?card=3in                    bigger tiles, fewer columns
    ?theme=dark                  dark theme
    ?depth=3                     shallower canvas, quicker to reach the end
    ?speed=fast&feel=overshoot   momentum: faster, with a rubber-band settle
    ?inertia=off                 momentum off, plain native scrolling
OPTIONS
printf '\n  Combine them with &   e.g.  %s/?layout=masonry&gap=12\n\n' "$URL"

bold '  Worth doing'
cat <<'TRY'
    Scroll hard, then immediately scroll back the other way — the momentum
    reverses at once rather than finishing its glide first.

    Compare ?inertia=off to feel the difference.
TRY
printf '\n'

bold '  To stop'
printf '    Press Ctrl-C, or just close this window.\n\n'

printf '\033[2m  ---- server log ----------------------------------------------\033[0m\n'

# exec so Ctrl-C and closing the window both stop the server cleanly.
exec bin/preview --port "$PORT"
