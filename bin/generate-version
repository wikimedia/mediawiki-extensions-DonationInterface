#!/bin/bash
#
# This script can be run manually, to populate the .version-stamp file.
#
# If the version is not found at runtime, the string "unknown" is used instead.

dest="$(dirname $0)/../.version-stamp"
head_rev=$(git rev-parse --verify HEAD)

echo -n "$head_rev" > $dest
