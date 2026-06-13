"""
PS9 part-1 smoke (NO generation, 0 credits): verify the qameraai module tab
renders on PrestaShop 9 — the 8<->9 platform risk (Symfony route, asset loading,
AJAX controller routing) that broke PS8 in M3. Checks:
  - admin login + product edit page reachable
  - #qamera-product-tab present and visible (hook fired on PS9 Symfony route)
  - CSS loaded (qamera class has non-default styling)
  - JS initialised (data-qamera-init=1)
  - AJAX controller routes (getJob returns JSON, not 404/500 HTML)
  - no console errors / failed asset requests
NOT shipped.
"""
import time
from playwright.sync_api import sync_playwright

BASE = "http://localhost:8091"
OUT = "tools/_shots"


def log(*a):
    print(*a, flush=True)


console_errors = []
failed_requests = []

with sync_playwright() as p:
    b = p.chromium.launch(headless=True)
    pg = b.new_page(viewport={"width": 1500, "height": 1400})
    pg.on("console", lambda m: console_errors.append(m.text) if m.type == "error" else None)
    pg.on("requestfailed", lambda r: failed_requests.append(r.url + " :: " + str(r.failure)))

    # 1) login
    pg.goto(BASE + "/admin-dev/", wait_until="networkidle")
    pg.fill('input[name="email"]', "admin@qamera.test")
    pg.fill('input[name="passwd"]', "qameraadmin1")
    pg.locator('input[name="passwd"]').press("Enter")
    pg.wait_for_url(lambda u: "AdminLogin" not in u, timeout=20000)
    log("login OK")

    # 2) first product -> edit (PS9 route: sell/catalog/products/ ; edit: products/{id}/edit)
    h = pg.eval_on_selector_all(
        "a",
        "els=>els.map(e=>e.href).filter(x=>x&&x.indexOf('sell/catalog/products/')>-1&&x.indexOf('create')==-1&&x.indexOf('preferences')==-1)",
    )
    log("products list link:", h[0] if h else "NONE")
    pg.goto(h[0], wait_until="networkidle")
    pg.wait_for_timeout(2500)
    ed = pg.eval_on_selector_all(
        "table a, a",
        "els=>els.map(e=>e.getAttribute('href')).find(x=>x&&x.indexOf('sell/catalog/products')>-1&&x.indexOf('/edit')>-1&&x.indexOf('#')==-1)",
    )
    log("edit url:", ed)
    if not ed:
        pg.screenshot(path=OUT + "/ps9-no-product.png", full_page=True)
        raise SystemExit("no product to edit on PS9 (demo data missing?)")
    pg.goto((BASE + ed) if ed.startswith("/") else ed, wait_until="networkidle")
    pg.wait_for_timeout(3000)

    # 3) Modules tab + Konfiguruj (open the qameraai extra tab)
    t = pg.locator('[data-bs-target="#product_extra_modules-tab"], [href="#product_extra_modules-tab"]')
    if t.count():
        t.first.click(); pg.wait_for_timeout(1200)
    cfg = pg.locator('button:has-text("Konfiguruj"), a:has-text("Konfiguruj")')
    if cfg.count():
        cfg.first.click(); pg.wait_for_timeout(2500)

    root = pg.locator("#qamera-product-tab")
    has_tab = root.count() > 0
    visible = root.first.is_visible() if has_tab else False
    log("tab present:", has_tab, "| visible:", visible)

    # 4) has-key (no "Brak klucza API" alert) — proves Configuration read works
    no_key = pg.locator('.qamera-alert--warning:has-text("Brak klucza API")').count()
    log("'Brak klucza API' alert (want 0):", no_key)

    # 5) CSS loaded: primary button should carry the teal accent, not browser default
    accent = pg.eval_on_selector(
        ".qamera-btn--primary",
        "el=>getComputedStyle(el).backgroundColor",
    ) if pg.locator(".qamera-btn--primary").count() else "N/A"
    log("primary btn bg (expect teal ~rgb(131,186,188)):", accent)

    # 6) JS initialised
    inited = root.first.get_attribute("data-qamera-init") if has_tab else None
    ajax_url = root.first.get_attribute("data-ajax-url") if has_tab else None
    log("data-qamera-init (want 1):", inited)

    # 7) AJAX controller routes — call getJob with a bogus id, expect JSON (ok:false), not 404/500 HTML
    route_status = "N/A"
    route_body = ""
    if ajax_url:
        res = pg.evaluate(
            """async (u)=>{
                const sep = u.indexOf('?')===-1?'?':'&';
                const r = await fetch(u+sep+'ajax=1&action=getJob', {
                    method:'POST',
                    headers:{'X-Requested-With':'XMLHttpRequest'},
                    body:new URLSearchParams({job_id:'00000000-0000-0000-0000-000000000000'})
                });
                const txt = await r.text();
                return {status:r.status, body:txt.slice(0,160)};
            }""",
            ajax_url,
        )
        route_status = res["status"]
        route_body = res["body"]
    log("AJAX getJob HTTP status (want 200):", route_status)
    log("AJAX getJob body head:", route_body)

    if has_tab:
        root.first.screenshot(path=OUT + "/ps9-tab.png")
    else:
        pg.screenshot(path=OUT + "/ps9-notab.png", full_page=True)

    log("--- console errors:", len(console_errors))
    for e in console_errors[:10]:
        log("   ERR:", e)
    log("--- failed requests:", len(failed_requests))
    for f in failed_requests[:10]:
        log("   REQFAIL:", f)

    b.close()
    log("DONE")
