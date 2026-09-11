# Publication safety review

Date: 2026-09-11. Scope: the four outgoing commits from
`e28aa8b258527b2ec11295cbb8a8d8bebe11a9ae` through
`e9f3f8741ece56ca2ee2216bbc8c35124cfadd3b`, including intermediate blobs.

**Repair: private metadata removed from the first unpublished appearance.**
The initial HIGH privacy finding is repaired by rebuilding only the unpublished
history. Publication remains gated on the complete outgoing-history verification.

## Finding: private photo metadata in unpublished history

Commit `e51f111af75506e3ef379efce28cb30caccb2e69` introduces 40 JPEG/MPO files
with GPS coordinates, location-associated capture timestamps and Apple photo/
capture identifiers. The four original files under
`docs/checkpoints/approved-storefront-2026-09-11/media/` are:

- `2026/04/local-gallery-iphone-set-01-img_0833.jpeg`
- `2026/09/ak-visual-review/category-macbook.jpg`
- `2026/09/ak-visual-review/category-ipad.jpg`
- `2026/09/ak-visual-review/category-apple-watch.jpg`

Each family includes nine derived images that also retain the metadata. The
iPhone copy is newly tracked as part of the approved-content recovery snapshot,
even though its storefront assignment was unchanged. No actual coordinates,
timestamps or identifiers are reproduced in this report.

The four existing PNG banners and four supplied JPG banners, including their
derivatives, have no identified sensitive EXIF/GPS metadata in this check.

## Authorized metadata cleanup completed locally

The publication snapshot on `feature/homepage-carousel-polish` is sanitized in
each affected unpublished commit. The original Downloads and running LOCAL uploads
are unchanged. The manifest contains the updated SHA-256 values.

- Removed GPS IFD entries and their data, capture-date/time fields, primary XMP
  capture/region metadata, and Apple MakerNote RunTime, ImageCaptureRequestID and
  PhotoIdentifier fields. Removed redundant capture time/camera details from the
  four corresponding public JSON metadata records.
- Used same-length metadata edits, without JPEG recompression. Orientation,
  decoded pixels for every frame, ICC profiles, all remaining MakerNote values
  including HDRHeadroom/HDRGain, MPF blocks and complete secondary HDR frame bytes
  were compared before/after and are unchanged for all 40 files.
- Preserved the approved visual image content, including store labels and prices.

The immutable published ancestor is
`e28aa8b258527b2ec11295cbb8a8d8bebe11a9ae`. All 51 current public remote refs
were checked before rewriting. Only `e51f111`, `21e9876` and `e9f3f87` are rebuilt;
`6bb0060` remains unchanged. The checkpoint's 40 image blobs and two metadata JSON
files are replaced from their first appearance, and descendants use clean parents.
Documentation records the repair; application code remains byte-identical.

The protected `prelaunch-checkpoint-2026-09` tag remains
`d5c332e13a4b18f461064bd12bfe5388fc835f61`. No published commit, remote tag,
main ref or unrelated history is changed. Normal non-force push must remain a
fast-forward from the immutable ancestor. Old local reflog entries are not pushed.

## Text/configuration check

Reviewed 135 affected paths and 32 unique textual blobs across the four outgoing
commits. No actual API/provider credential (including TEST credentials), SSH private
key, password, token, private environment configuration, server/database backup,
QA/customer personal data or private operational artifact was identified.
Public store contact details and clearly synthetic test fixtures are not private
customer data. This narrow publication check is not the pending TEST launch audit.
