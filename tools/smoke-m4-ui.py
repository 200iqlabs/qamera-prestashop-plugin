"""
M4 UI-rework render smoke (NO generation): verify the 3-section vertical layout,
that session-imported images are excluded from the source picker, that "Generuj
packshot" lives only in the platform section, and packshot/session controls.
NOT shipped.
"""
from playwright.sync_api import sync_playwright

BASE = "http://localhost:8082"
OUT = "tools/_shots"


def log(*a):
    print(*a, flush=True)


with sync_playwright() as p:
    b = p.chromium.launch(headless=True)
    pg = b.new_page(viewport={"width": 1500, "height": 1600})
    pg.goto(BASE + "/admin-dev/", wait_until="networkidle")
    pg.fill('input[name="email"]', "admin@qamera.test")
    pg.fill('input[name="passwd"]', "qameraadmin1")
    pg.locator('input[name="passwd"]').press("Enter")
    pg.wait_for_url(lambda u: "AdminLogin" not in u, timeout=20000)

    h = pg.eval_on_selector_all("a", "els=>els.map(e=>e.href).filter(x=>x.indexOf('sell/catalog/products?')>-1)")
    pg.goto(h[0], wait_until="networkidle")
    pg.wait_for_timeout(2500)
    ed = pg.eval_on_selector_all(
        "table a",
        "els=>els.map(e=>e.getAttribute('href')).find(x=>x&&x.indexOf('products-v2/')>-1&&x.indexOf('/edit')>-1&&x.indexOf('#')==-1)",
    )
    log("edit:", ed)
    pg.goto(BASE + ed, wait_until="networkidle")
    pg.wait_for_timeout(3000)
    t = pg.locator('[data-bs-target="#product_extra_modules-tab"], [href="#product_extra_modules-tab"]')
    if t.count():
        t.first.click(); pg.wait_for_timeout(1000)
    cfg = pg.locator('button:has-text("Konfiguruj"), a:has-text("Konfiguruj")')
    if cfg.count():
        cfg.first.click(); pg.wait_for_timeout(2500)

    # 1) section order
    titles = pg.eval_on_selector_all("#qamera-product-tab .qamera-card__title", "els=>els.map(e=>e.textContent.trim())")
    log("section titles:", titles)

    # 2) section 1 gallery: NO generate-packshot button inside the gallery
    gal_gen = pg.locator('.qamera-gallery [data-action="generate-packshot"]').count()
    gal_items = pg.locator('.qamera-gallery__item').count()
    log("gallery items:", gal_items, "| generate-packshot in gallery (want 0):", gal_gen)

    # 3) imported session image (id_image=26) excluded from the source picker
    img26 = pg.locator('.qamera-gallery__item[data-id-image="26"]').count()
    log("gallery tile for imported id_image=26 (want 0):", img26)

    # 4) platform section: generate-packshot lives here
    plat_gen = pg.locator('#qamera-platform-list [data-action="generate-packshot"]').count()
    containers = pg.locator('#qamera-platform-list .qamera-container').count()
    log("platform containers:", containers, "| generate-packshot in platform:", plat_gen)

    # 5) packshot/session controls present
    gs = pg.locator('[data-action="generate-session"]').count()
    log("generate-session buttons:", gs)

    # 6) no leftover fresh-section / AI dropdown
    log("old #qamera-new-packshots (want 0):", pg.locator('#qamera-new-packshots').count())
    log("#qamera-ai-model (want 0):", pg.locator('#qamera-ai-model').count())

    pg.locator("#qamera-product-tab").first.screenshot(path=OUT + "/m4-ui-tab.png")
    b.close()
    log("DONE")
