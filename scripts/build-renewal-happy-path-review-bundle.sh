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
    "${asset_dir}/01-application-lodged-and-payable-current.png" \
    "${render_dir}/01-citizen-lodged-target.png" \
    "${asset_dir}/audit-final-health-work-surface.png" \
    "${render_dir}/04-responsibility-work-queue-target.png" \
    "${asset_dir}/audit-final-evaluation-responsibilities.png" \
    "${render_dir}/05-office-responsibility-target.png" \
    "${asset_dir}/07-assessment-officer-working-paper-current.png" \
    "${render_dir}/08-ready-for-assessment-target.png" \
    "${asset_dir}/audit-final-assessment-desktop.png" \
    "${asset_dir}/audit-final-assessment-mobile-390.png" \
    "${asset_dir}/audit-final-assessment-desktop.png" \
    "${render_dir}/11-treasurer-decision-target.png" \
    "${asset_dir}/audit-final-payable.png" \
    "${render_dir}/14-qr-issuance-target.png" \
    "${render_dir}/16-receipt-target.png" \
    -thumbnail 720x450 \
    -background '#eef2f5' \
    -geometry 720x450+18+18 \
    -tile 2x \
    "${contact_sheet}"

printf '%s\n' "${contact_sheet}"
