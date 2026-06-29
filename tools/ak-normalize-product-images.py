#!/usr/bin/env python3
"""Normalize Apple Klinika product images for local demo media workflows.

The script is intentionally local-only: it reads image files from disk, writes
normalized PNG assets, and never talks to WordPress or external services.
"""

from __future__ import annotations

import argparse
import json
import math
from dataclasses import dataclass
from pathlib import Path
from typing import Iterable

from PIL import Image, ImageChops, ImageDraw, ImageFont


@dataclass(frozen=True)
class Profile:
    name: str
    canvas_width: int
    canvas_height: int
    target_height_pct: float
    min_width_pct: float
    max_width_pct: float


PROFILES: dict[str, Profile] = {
    "phone-portrait": Profile("phone-portrait", 1000, 1450, 0.84, 0.58, 0.70),
    "laptop-landscape": Profile("laptop-landscape", 1600, 1050, 0.72, 0.70, 0.90),
    "tablet-portrait": Profile("tablet-portrait", 1100, 1450, 0.82, 0.58, 0.76),
    "tablet-landscape": Profile("tablet-landscape", 1500, 1100, 0.72, 0.68, 0.88),
    "watch-square": Profile("watch-square", 1200, 1200, 0.76, 0.44, 0.66),
}


IMAGE_SUFFIXES = {".jpg", ".jpeg", ".png", ".webp"}


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--profile", choices=sorted(PROFILES), required=True)
    parser.add_argument("--source-dir", type=Path)
    parser.add_argument("--output-dir", type=Path, required=True)
    parser.add_argument("--filename-prefix", default="product-display")
    parser.add_argument("--source", action="append", type=Path, default=[])
    parser.add_argument("--limit", type=int, default=0)
    parser.add_argument("--contact-sheet", type=Path)
    parser.add_argument("--report", type=Path)
    return parser.parse_args()


def image_files(source_dir: Path | None, explicit_sources: Iterable[Path]) -> list[Path]:
    files: list[Path] = []
    for source in explicit_sources:
        if source.is_file() and source.suffix.lower() in IMAGE_SUFFIXES:
            files.append(source)

    if source_dir and source_dir.is_dir():
        for source in sorted(source_dir.iterdir(), key=lambda item: item.name.lower()):
            if source.is_file() and source.suffix.lower() in IMAGE_SUFFIXES:
                files.append(source)

    seen: set[Path] = set()
    unique: list[Path] = []
    for source in files:
        resolved = source.resolve()
        if resolved in seen:
            continue
        seen.add(resolved)
        unique.append(source)

    return unique


def alpha_bbox(image: Image.Image) -> tuple[int, int, int, int] | None:
    rgba = image.convert("RGBA")
    alpha = rgba.getchannel("A")
    if alpha.getextrema()[0] < 255:
        return alpha.point(lambda value: 255 if value > 8 else 0).getbbox()
    return None


def background_bbox(image: Image.Image) -> tuple[int, int, int, int] | None:
    rgba = image.convert("RGBA")
    width, height = rgba.size
    pixels = rgba.load()
    mask = Image.new("L", (width, height), 0)
    mask_pixels = mask.load()

    corners = [
        pixels[0, 0],
        pixels[width - 1, 0],
        pixels[0, height - 1],
        pixels[width - 1, height - 1],
    ]
    avg = tuple(sum(pixel[index] for pixel in corners) // len(corners) for index in range(3))

    for y in range(height):
        for x in range(width):
            r, g, b, a = pixels[x, y]
            if a <= 8:
                continue

            max_other = max(r, b)
            green_dominance = g - max_other
            is_green_screen = g > 70 and green_dominance > 24 and g > r * 1.12 and g > b * 1.04
            color_distance = math.sqrt((r - avg[0]) ** 2 + (g - avg[1]) ** 2 + (b - avg[2]) ** 2)
            is_near_background = color_distance < 34 and max(r, g, b) > 178

            if not is_green_screen and not is_near_background:
                mask_pixels[x, y] = 255

    return mask.getbbox()


def detect_bbox(image: Image.Image) -> tuple[int, int, int, int]:
    bbox = alpha_bbox(image) or background_bbox(image)
    if bbox:
        return bbox
    return (0, 0, image.width, image.height)


def suppress_green_spill(image: Image.Image) -> Image.Image:
    rgba = image.convert("RGBA")
    pixels = rgba.load()
    width, height = rgba.size

    for y in range(height):
        for x in range(width):
            r, g, b, a = pixels[x, y]
            if a <= 8:
                continue
            dominance = g - max(r, b)
            if g > 70 and dominance > 12:
                reduction = int((dominance - 12) * 0.45)
                pixels[x, y] = (r, max(0, g - reduction), b, a)

    return rgba


def normalize_image(source: Path, output: Path, profile: Profile) -> dict:
    image = Image.open(source).convert("RGBA")
    before_bbox = detect_bbox(image)
    source_width, source_height = image.size
    x0, y0, x1, y1 = before_bbox
    object_width = x1 - x0
    object_height = y1 - y0

    pad_x = max(18, round(object_width * 0.035))
    pad_y = max(18, round(object_height * 0.035))
    crop_box = (
        max(0, x0 - pad_x),
        max(0, y0 - pad_y),
        min(source_width, x1 + pad_x),
        min(source_height, y1 + pad_y),
    )

    crop = suppress_green_spill(image.crop(crop_box))
    crop_bbox = detect_bbox(crop)
    crop_object_width = crop_bbox[2] - crop_bbox[0]
    crop_object_height = crop_bbox[3] - crop_bbox[1]

    target_object_height = profile.canvas_height * profile.target_height_pct
    scale = target_object_height / max(1, crop_object_height)
    scaled_object_width_pct = (crop_object_width * scale) / profile.canvas_width
    if scaled_object_width_pct > profile.max_width_pct:
        scale = (profile.canvas_width * profile.max_width_pct) / max(1, crop_object_width)

    resized_width = max(1, round(crop.width * scale))
    resized_height = max(1, round(crop.height * scale))
    resized = crop.resize((resized_width, resized_height), Image.Resampling.LANCZOS)

    canvas = Image.new("RGBA", (profile.canvas_width, profile.canvas_height), (255, 255, 255, 0))
    dest_x = round((profile.canvas_width - resized_width) / 2)
    dest_y = round((profile.canvas_height - resized_height) / 2)
    canvas.alpha_composite(resized, (dest_x, dest_y))

    output.parent.mkdir(parents=True, exist_ok=True)
    canvas.save(output, "PNG", optimize=True)

    after_bbox = detect_bbox(canvas)
    after_width = after_bbox[2] - after_bbox[0]
    after_height = after_bbox[3] - after_bbox[1]

    return {
        "source": str(source),
        "output": str(output),
        "profile": profile.name,
        "before": {
            "dimensions": [source_width, source_height],
            "bbox": [x0, y0, object_width, object_height],
            "object_width_pct": round((object_width / source_width) * 100, 2),
            "object_height_pct": round((object_height / source_height) * 100, 2),
        },
        "after": {
            "dimensions": [profile.canvas_width, profile.canvas_height],
            "bbox": [after_bbox[0], after_bbox[1], after_width, after_height],
            "object_width_pct": round((after_width / profile.canvas_width) * 100, 2),
            "object_height_pct": round((after_height / profile.canvas_height) * 100, 2),
            "meets_profile": (
                profile.min_width_pct * 100 <= round((after_width / profile.canvas_width) * 100, 2) <= profile.max_width_pct * 100
                and 80 <= round((after_height / profile.canvas_height) * 100, 2) <= 86
            ),
        },
    }


def checkerboard(size: tuple[int, int], square: int = 18) -> Image.Image:
    width, height = size
    image = Image.new("RGB", size, "#f8fafc")
    draw = ImageDraw.Draw(image)
    for y in range(0, height, square):
        for x in range(0, width, square):
            if (x // square + y // square) % 2:
                draw.rectangle([x, y, x + square - 1, y + square - 1], fill="#eef2f7")
    return image


def fit_on_panel(source: Image.Image, size: tuple[int, int]) -> Image.Image:
    panel = checkerboard(size)
    image = source.convert("RGBA")
    image.thumbnail((size[0] - 24, size[1] - 42), Image.Resampling.LANCZOS)
    x = round((size[0] - image.width) / 2)
    y = round((size[1] - image.height) / 2)
    panel.paste(image, (x, y), image)
    return panel


def create_contact_sheet(results: list[dict], output: Path) -> None:
    if not results:
        return

    panel_w, panel_h = 360, 460
    label_h = 72
    width = panel_w * 2
    height = (panel_h + label_h) * len(results)
    sheet = Image.new("RGB", (width, height), "#ffffff")
    draw = ImageDraw.Draw(sheet)
    font = ImageFont.load_default()

    for index, result in enumerate(results):
        y = index * (panel_h + label_h)
        source_image = Image.open(result["source"]).convert("RGBA")
        output_image = Image.open(result["output"]).convert("RGBA")
        sheet.paste(fit_on_panel(source_image, (panel_w, panel_h)), (0, y))
        sheet.paste(fit_on_panel(output_image, (panel_w, panel_h)), (panel_w, y))
        label = (
            f"{Path(result['source']).name} -> {Path(result['output']).name}\\n"
            f"before {result['before']['object_width_pct']}%w/{result['before']['object_height_pct']}%h | "
            f"after {result['after']['object_width_pct']}%w/{result['after']['object_height_pct']}%h | "
            f"ok={result['after']['meets_profile']}"
        )
        draw.rectangle([0, y + panel_h, width, y + panel_h + label_h], fill="#ffffff")
        draw.text((14, y + panel_h + 12), label, fill="#111820", font=font)
        draw.line([0, y + panel_h + label_h - 1, width, y + panel_h + label_h - 1], fill="#d8dee8", width=1)

    output.parent.mkdir(parents=True, exist_ok=True)
    sheet.save(output, "PNG", optimize=True)


def main() -> int:
    args = parse_args()
    profile = PROFILES[args.profile]
    sources = image_files(args.source_dir, args.source)
    if args.limit > 0:
        sources = sources[: args.limit]

    if not sources:
        raise SystemExit("No source images found.")

    args.output_dir.mkdir(parents=True, exist_ok=True)

    results: list[dict] = []
    for index, source in enumerate(sources, start=1):
        output = args.output_dir / f"{args.filename_prefix}-{index:02d}.png"
        results.append(normalize_image(source, output, profile))

    if args.contact_sheet:
        create_contact_sheet(results, args.contact_sheet)

    report = {
        "profile": profile.name,
        "source_count": len(sources),
        "results": results,
        "contact_sheet": str(args.contact_sheet) if args.contact_sheet else None,
    }

    if args.report:
        args.report.parent.mkdir(parents=True, exist_ok=True)
        args.report.write_text(json.dumps(report, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")

    print(json.dumps(report, indent=2, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
