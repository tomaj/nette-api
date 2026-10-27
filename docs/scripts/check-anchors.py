"""Check that every in-site link with a #fragment points at an existing id."""
import re
import sys
from html import unescape
from pathlib import Path
from urllib.parse import unquote

dist = Path(sys.argv[1] if len(sys.argv) > 1 else ".vitepress/dist")
base = "/nette-api/"
ids: dict[Path, set[str]] = {}


def page_ids(page: Path) -> set[str]:
    if page not in ids:
        text = page.read_text(encoding="utf-8")
        ids[page] = set(re.findall(r'\sid="([^"]+)"', text))
    return ids[page]


def resolve(current: Path, path: str) -> Path | None:
    if path == "":
        return current
    if path.startswith(base):
        path = path[len(base):]
    elif path.startswith("/"):
        return None
    else:
        path = str((current.parent / path).relative_to(dist))
    target = dist / path
    for candidate in (target, target.with_suffix(".html") if target.suffix == "" else target, target / "index.html"):
        if candidate.is_file():
            return candidate
    return None


broken = []
count = 0
for page in dist.rglob("*.html"):
    text = page.read_text(encoding="utf-8")
    for href in re.findall(r'href="([^"]*#[^"]+)"', text):
        href = unescape(href)
        if href.startswith(("http:", "https:", "mailto:")):
            continue
        path, fragment = href.split("#", 1)
        target = resolve(page, path)
        if target is None:
            broken.append(f"{page.relative_to(dist)}: {href} (no page)")
            continue
        count += 1
        if unquote(fragment) not in page_ids(target):
            broken.append(f"{page.relative_to(dist)}: {href}")
if broken:
    print("Broken anchors:", *broken, sep="\n", file=sys.stderr)
    sys.exit(1)
print(f"ok {count} anchor links")
