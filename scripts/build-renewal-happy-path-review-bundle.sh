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
    "${asset_dir}/product-01-citizen-my-permit-applications.png" \
    "${asset_dir}/product-02-citizen-scenario-detail.png" \
    "${asset_dir}/product-03-bplo-lifecycle-summary.png" \
    "${asset_dir}/product-04-bplo-treasury-timeline.png" \
    "${asset_dir}/product-05-assessment-officer-working-paper.png" \
    "${asset_dir}/product-06-concerned-office-health.png" \
    "${asset_dir}/product-07-treasury-lens.png" \
    "${asset_dir}/product-08-municipal-treasurer-lens.png" \
    "${asset_dir}/product-09-payable-payment-schedule.png" \
    "${asset_dir}/product-10-mobile-citizen-detail.png" \
    "${asset_dir}/product-11-mobile-bplo-summary.png" \
    "${asset_dir}/product-12-mobile-municipal-treasurer.png" \
    -thumbnail 720x450 \
    -background '#eef2f5' \
    -geometry 720x450+18+18 \
    -tile 2x \
    "${contact_sheet}"

printf '%s\n' "${contact_sheet}"
