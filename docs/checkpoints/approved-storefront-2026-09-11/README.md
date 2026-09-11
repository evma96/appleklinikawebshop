# Approved LOCAL storefront checkpoint

Martin approved this storefront on 2026-09-11 before the final small polish pass.
The containing commit on `feature/homepage-carousel-polish` preserves the approved
theme code, homepage, eight-slide carousel, category photos, Contact map and mobile
directions chooser. This snapshot does not run automatically or change the site.

Publication review found private GPS/capture metadata in four photo families
(40 files). These files are sanitized losslessly from their first appearance in
the rewritten unpublished history, with the manifest updated. Pixels, orientation,
ICC and HDR data are preserved. See `../../publication-safety-review.md`.

`content.json` records the public homepage/Contact options, featured product
selection, resolved unchanged iPhone category image, and the 12 media-library
entries used by those sections. `media/` retains the original and generated
image pixels with private capture metadata removed, relative to the WordPress uploads directory. `files.json` records
SHA-256 hashes and byte counts for every copy. The tracked theme already contains
the default service photo. No credentials, customer records or orders are exported.

To recover this approved content on the same LOCAL database, first preserve any
newer state. Restore the containing commit's theme code and copy the needed files
from `media/` to the corresponding uploads paths after checking their hashes.
Restore only the four named options in `content.json` through the WordPress option
API. Media IDs are LOCAL database identifiers: check them against the recorded
title/file/metadata before restoring references. If a recorded attachment is
missing, recreate it from its recorded file and metadata and remap its ID in the
options; do not overwrite an unrelated attachment. The iPhone row intentionally
keeps its existing catalogue fallback rather than introducing a new selection.

This is a frontend content recovery snapshot, not a full WordPress database backup.
Existing products, order/integration data, accounts and plugin configuration are
unchanged. No TEST access, merge, push or deployment is part of this checkpoint.
