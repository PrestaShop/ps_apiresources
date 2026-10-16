#!/usr/bin/env bash
# sync-rules.sh — exposes the path-scoped rules of .ai/rules/*.md to every AI tool.
#
# The rule file is the source (frontmatter `description` + `paths` as a YAML list + a short body):
#   .claude/rules/{name}.md                      → symlink to the source (Claude Code reads `paths`)
#   .cursor/rules/{name}.mdc                     ← generated copy (`globs`, `alwaysApply: false`)
#   .windsurf/rules/{name}.md                    ← generated copy (`trigger: glob`, `globs`)
#   .github/instructions/{name}.instructions.md  ← generated copy (`applyTo`)
# Same pattern as the PrestaShop core (.ai/STRUCTURE.md → "Path-scoped rules"). Run it after any change
# to .ai/rules/ and during the AI context maintenance.
#
# Usage: bash .ai/bin/sync-rules.sh

set -euo pipefail
export LC_ALL=C

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
RULES_DIR="$REPO_ROOT/.ai/rules"
WRITTEN=0
UNCHANGED=0
LINKS=0

write_rule_copy() {
    local target="$1" frontmatter="$2" rule_name="$3" body="$4"
    local tmp="$target.tmp"
    mkdir -p "$(dirname "$target")"
    [[ -L "$target" ]] && rm -f "$target"
    {
        echo "---"
        echo "$frontmatter"
        echo "---"
        echo "<!-- Generated from .ai/rules/$rule_name.md by .ai/bin/sync-rules.sh: edit the source, not this copy -->"
        echo "$body"
    } > "$tmp"
    if [[ -f "$target" ]] && cmp -s "$tmp" "$target"; then
        rm -f "$tmp"
        UNCHANGED=$((UNCHANGED + 1))
    else
        mv -f "$tmp" "$target"
        WRITTEN=$((WRITTEN + 1))
    fi
}

[[ -d "$RULES_DIR" ]] || { echo "No .ai/rules directory"; exit 0; }

while IFS= read -r rule_md; do
    rule_name="$(basename "$rule_md" .md)"
    description=$(awk '/^---$/ {c++; next} c == 1 && /^description:/ {sub(/^description:[[:space:]]*/, ""); print; exit}' "$rule_md")
    globs=$(awk '
        /^---$/ {c++; next}
        c == 1 && /^paths:/ {p = 1; next}
        c == 1 && p && /^[[:space:]]*-[[:space:]]*/ {sub(/^[[:space:]]*-[[:space:]]*/, ""); gsub(/"/, ""); printf "%s%s", (n++ ? "," : ""), $0; next}
        c == 1 && p {p = 0}
    ' "$rule_md")
    body=$(awk '/^---$/ {c++; next} c >= 2 {print}' "$rule_md")
    if [[ -z "$globs" ]]; then
        echo "⚠ rule $rule_name: no \`paths:\` list in the frontmatter, skipped" >&2
        continue
    fi

    link="$REPO_ROOT/.claude/rules/$rule_name.md"
    mkdir -p "$(dirname "$link")"
    if [[ ! -L "$link" ]]; then
        rm -f "$link"
        ln -s "../../.ai/rules/$rule_name.md" "$link"
        LINKS=$((LINKS + 1))
    fi

    write_rule_copy "$REPO_ROOT/.cursor/rules/$rule_name.mdc" \
        "description: $description"$'\n'"globs: $globs"$'\n'"alwaysApply: false" "$rule_name" "$body"
    write_rule_copy "$REPO_ROOT/.windsurf/rules/$rule_name.md" \
        "trigger: glob"$'\n'"globs: $globs"$'\n'"description: $description" "$rule_name" "$body"
    write_rule_copy "$REPO_ROOT/.github/instructions/$rule_name.instructions.md" \
        "applyTo: \"$globs\"" "$rule_name" "$body"
done < <(find "$RULES_DIR" -maxdepth 1 -name "*.md" | sort)

echo "rules: $LINKS Claude symlink(s) created, $WRITTEN tool copies written, $UNCHANGED unchanged"
