#!/usr/bin/env bash
# Puts the screenshots of one pull request on the pr-screenshots branch, through the git API.
set -euo pipefail
repo="$1"
pr="$2"
shots="$3"
work="$(mktemp -d)"

entries="[]"
for file in "$shots"/*.png; do
  name="$(basename "$file")"
  base64 -i "$file" > "$work/blob.b64"
  jq -n --rawfile content "$work/blob.b64" '{content: ($content | gsub("\\s"; "")), encoding: "base64"}' > "$work/blob.json"
  sha="$(gh api "repos/$repo/git/blobs" --input "$work/blob.json" --jq .sha)"
  entries="$(jq --arg path "pr/$pr/$name" --arg sha "$sha" '. + [{path: $path, mode: "100644", type: "blob", sha: $sha}]' <<< "$entries")"
done

if ! parent="$(gh api "repos/$repo/git/ref/heads/pr-screenshots" --jq .object.sha 2>/dev/null)"; then
  parent=""
fi
if [ -n "$parent" ]; then
  base_tree="$(gh api "repos/$repo/git/commits/$parent" --jq .tree.sha)"
  jq -n --arg base "$base_tree" --argjson tree "$entries" '{base_tree: $base, tree: $tree}' > "$work/tree.json"
else
  jq -n --argjson tree "$entries" '{tree: $tree}' > "$work/tree.json"
fi
tree="$(gh api "repos/$repo/git/trees" --input "$work/tree.json" --jq .sha)"

if [ -n "$parent" ]; then
  jq -n --arg tree "$tree" --arg parent "$parent" --arg message "📸 screenshots for #$pr" '{message: $message, tree: $tree, parents: [$parent]}' > "$work/commit.json"
else
  jq -n --arg tree "$tree" --arg message "📸 screenshots for #$pr" '{message: $message, tree: $tree, parents: []}' > "$work/commit.json"
fi
commit="$(gh api "repos/$repo/git/commits" --input "$work/commit.json" --jq .sha)"

if [ -n "$parent" ]; then
  gh api -X PATCH "repos/$repo/git/refs/heads/pr-screenshots" -f sha="$commit" --jq .object.sha
else
  gh api "repos/$repo/git/refs" -f ref=refs/heads/pr-screenshots -f sha="$commit" --jq .object.sha
fi

rm -rf "$work"
for file in "$shots"/*.png; do
  echo "https://raw.githubusercontent.com/$repo/pr-screenshots/pr/$pr/$(basename "$file")"
done
