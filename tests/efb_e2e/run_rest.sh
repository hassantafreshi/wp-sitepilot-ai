#!/bin/bash
# Drives tests/efb_e2e/rest_flow.php on a throwaway WordPress with Easy Form Builder (dev4) and its add-ons in vendor/.
#   WP="php wp-cli.phar --path=/path/to/site" bash tests/efb_e2e/run_rest.sh
# Each scenario's add-on switches and plan are set in their own process (EFB loads add-ons once per request), then
# restored. The AI provider is the inert mock mu-plugin (tests/efb_e2e/aisa-ai-mock-mu.php, copied to mu-plugins).
set -u
DIR="$(cd "$(dirname "$0")" && pwd)"
WP=${WP:?set WP to a wp-cli command with --path}
ctl() { $WP eval-file "$DIR/ctl.php" "$@" >/dev/null; }
run() { $WP eval-file "$DIR/rest_flow.php" "$1" 2>&1 | grep -E "^(PASS|FAIL)"; }
ctl setup
ctl plan 3; ctl addon AdnSMF 1; ctl addon AdnTSG 1; ctl addon AdnCLC 0; ctl addon AdnADR 0; ctl addon AdnMOL 0; ctl addon AdnSPF 0
run split
run address
ctl addon AdnTSG 0; run tsg; ctl addon AdnTSG 1
ctl plan 2; run free; ctl plan 3
run logic
ctl addon AdnMOL 1; run payments; ctl addon AdnMOL 0
run portal
run editor
run write
run undo
# EFB 4.3.2: drafts, a saved form changed through form.update, the owner's Save warnings (Numbers & Calculations off).
run saved
# Restore the add-ons this site had.
ctl addon AdnCLC 1; ctl addon AdnADR 1; ctl addon AdnSMF 0
