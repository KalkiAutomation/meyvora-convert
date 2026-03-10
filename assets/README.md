# CRO Toolkit – Plugin Assets

This folder contains screenshots, banners, and icons used by the WordPress.org
plugin directory and included in the distribution zip.

## Screenshots

Each `screenshot-N.png` corresponds to the matching entry in `readme.txt`
under `== Screenshots ==`. WordPress requires the numbering to match exactly.

| File | Dimensions | Description |
|---|---|---|
| `screenshot-1.png` | 1200 × 675 px (16:9) | Dashboard overview — KPI cards |
| `screenshot-2.png` | 1200 × 675 px | Visual campaign builder with live preview |
| `screenshot-3.png` | 1200 × 675 px | Cart optimizer — trust strip, shipping bar, urgency |
| `screenshot-4.png` | 1200 × 675 px | Checkout optimizer — secure badge, guarantee, trust |
| `screenshot-5.png` | 1200 × 675 px | Dynamic offers rule builder |
| `screenshot-6.png` | 1200 × 675 px | System Status panel |

### Guidelines

- Format: PNG (preferred) or JPEG. No transparency needed.
- Max file size: keep each screenshot under **300 KB** (optimize with TinyPNG
  or similar before committing).
- Dimensions: **1200 × 675 px** (16:9) is the recommended size.
  WordPress.org displays them at 772 × 434 px; a 1200 px wide source stays
  crisp on retina screens.
- Crop to the relevant UI area — no browser chrome, no OS window frame.
- Use real or realistic demo data; avoid empty states.

## Banners (WordPress.org plugin page header)

| File | Dimensions | Required |
|---|---|---|
| `banner-772x250.png` | 772 × 250 px | Yes (standard) |
| `banner-1544x500.png` | 1544 × 500 px | Recommended (retina / high-DPI) |

## Icons (WordPress.org plugin listing)

| File | Dimensions | Required |
|---|---|---|
| `icon-128x128.png` | 128 × 128 px | Yes (standard) |
| `icon-256x256.png` | 256 × 256 px | Recommended (retina / high-DPI) |

## Adding assets

1. Place the final image files in this folder using the exact names above.
2. Run `bash build-zip.sh` or `bash scripts/release.sh` — both scripts
   automatically include `assets/` in the distribution zip.
3. The build scripts verify that all 6 screenshots referenced in `readme.txt`
   are present and warn if any are missing.
