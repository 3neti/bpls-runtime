#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
asset_dir="${repo_root}/docs/product/storyboards/assets/renewal-happy-path"
contact_sheet="${repo_root}/docs/product/storyboards/renewal-happy-path-contact-sheet.png"
render_dir="$(mktemp -d)"
trap 'rm -rf "${render_dir}"' EXIT

for target in "${asset_dir}"/*-target.svg; do
    magick -background white -density 120 "${target}" -resize 720x450 "${render_dir}/$(basename "${target%.svg}").png"
done

magick montage \
    "${asset_dir}/01-first-principles-onboarding-application-lobs-current.png" \
    "${asset_dir}/05-first-principles-routing-responsibilities-current.png" \
    "${asset_dir}/audit-final-health-work-surface.png" \
    "${asset_dir}/audit-final-evaluation-responsibilities.png" \
    "${asset_dir}/07-assessment-officer-working-paper-current.png" \
    "${asset_dir}/11-first-principles-assessment-treasury-treasurer-current.png" \
    "${asset_dir}/14-first-principles-payable-current.png" \
    -thumbnail 720x450 \
    -background '#eef2f5' \
    -geometry 720x450+18+18 \
    -tile 2x \
    "${contact_sheet}"

printf '%s\n' "${contact_sheet}"
