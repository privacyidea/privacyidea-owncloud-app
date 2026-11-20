#!/bin/sh
# Print the section of the changelog for one version, without its heading.
# Usage: changelog-section.sh <version> [changelog file]
# The heading of the section must start with the version, for example "## 3.2.0 02/2024" or "## 3.0, 2022-01-20".
# The subsection "### Note for the release" is for the maintainers. The script leaves it out.
# The release workflow uses this script. You can run it by hand to see the release notes before you set the tag.
set -eu

version="${1:?Usage: changelog-section.sh <version> [changelog file]}"
file="${2:-Changelog.md}"

section=$(tr -d '\r' < "$file" | awk -v v="$version" '
  /^## / {
    if (found) exit
    token = $2
    sub(/,$/, "", token)
    # Compare as text. As numbers, "3.1" and "3.10" are equal.
    if ((token "") == (v "")) found = 1
    next
  }
  !found { next }
  /^### / { skip = ($0 ~ /^### Note for the release/) }
  !skip { print }
' | sed '/./,$!d')

if [ -z "$section" ]; then
  echo "No changelog section for version $version in $file." >&2
  echo "Rename the heading \"## Unreleased\" to \"## $version <month>/<year>\"." >&2
  exit 1
fi

printf '%s\n' "$section"
