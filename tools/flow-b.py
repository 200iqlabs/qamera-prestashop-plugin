"""
M4 Flow B live smoke: from an approved/direct packshot, generate a session
(count=1 to limit credits), poll to completion, accept the generated image,
and confirm it is imported into the product gallery (ps_image + ps_qamera_*).
Consumes real Qamera credits. NOT shipped.
"""
import json
import time
from playwright.sync_api import sync_playwright

BASE = "http://localhost:8082"
OUT = "tools/_shots"


def log(*a):
    print(*a, flush=True)


ajax = {"generateSession": None, "acceptSession": None, "getJob": []}

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_page(viewport={"width": 1500, "height": 1400})

    def on_resp(r):
        u = r.url
        if "AdminQameraAjax" not in u:
            return
        try:
            body = r.text()
        except Exception:
            return
        for key in ("generateSession", "acceptSession", "getJob"):
            if "action=" + key in u:
                try:
                    data = json.loads(body)
                except Exception:
                    data = body[:200]
                if key == "getJob":
                    ajax["getJob"].append(data)
                else:
                    ajax[key] = data
                    log("AJAX", key, "->", json.dumps(data)[:300] if isinstance(data, dict) else data)

    page.on("response", on_resp)

    # 1) login
    page.goto(BASE + "/admin-dev/", wait_until="networkidle")
    page.fill('input[name="email"]', "admin@qamera.test")
    page.fill('input[name="passwd"]', "qameraadmin1")
    page.locator('input[name="passwd"]').press("Enter")
    page.wait_for_url(lambda u: "AdminLogin" not in u, timeout=20000)

    # 2) first product -> edit
    h = page.eval_on_selector_all("a", "els=>els.map(e=>e.href).filter(x=>x.indexOf('sell/catalog/products?')>-1)")
    page.goto(h[0], wait_until="networkidle")
    page.wait_for_timeout(2500)
    ed = page.eval_on_selector_all(
        "table a",
        "els=>els.map(e=>e.getAttribute('href')).find(x=>x&&x.indexOf('products-v2/')>-1&&x.indexOf('/edit')>-1&&x.indexOf('#')==-1)",
    )
    log("edit:", ed)
    page.goto(BASE + ed, wait_until="networkidle")
    page.wait_for_timeout(3000)

    # 3) Moduly tab + Konfiguruj
    t = page.locator('[data-bs-target="#product_extra_modules-tab"], [href="#product_extra_modules-tab"]')
    if t.count():
        t.first.click()
        page.wait_for_timeout(1200)
    cfg = page.locator('button:has-text("Konfiguruj"), a:has-text("Konfiguruj")')
    if cfg.count():
        cfg.first.click()
        page.wait_for_timeout(2500)

    root = page.locator("#qamera-product-tab")
    log("tab visible:", root.first.is_visible() if root.count() else "NO TAB")

    gen = page.locator('[data-action="generate-session"]')
    log("generate-session buttons:", gen.count())
    if gen.count() == 0:
        log("NO APPROVED PACKSHOT — cannot run Flow B. Abort.")
        page.screenshot(path=OUT + "/b0-no-packshot.png", full_page=True)
        browser.close()
        raise SystemExit(1)

    # 4) count=1 to minimize credits, then generate the session
    page.evaluate("()=>{const c=document.querySelector('#qamera-count'); if(c) c.value='1';}")
    gen.first.scroll_into_view_if_needed()
    gen.first.click()
    log("clicked generate-session")

    # 5) wait for the generateSession AJAX (order_id + job_ids)
    deadline = time.time() + 30
    while time.time() < deadline and ajax["generateSession"] is None:
        page.wait_for_timeout(1000)
    gs = ajax["generateSession"]
    if not gs or not gs.get("ok"):
        log("generateSession FAILED:", gs)
        page.screenshot(path=OUT + "/b1-gen-fail.png", full_page=True)
        browser.close()
        raise SystemExit(1)
    job_ids = gs.get("job_ids", [])
    log("session order_id:", gs.get("order_id"), "job_ids:", job_ids)

    # 6) poll up to 6 min for the accept-session button of one of the jobs
    target_job = job_ids[0] if job_ids else None
    deadline = time.time() + 360
    accept_btn = None
    while time.time() < deadline:
        page.wait_for_timeout(5000)
        sel = '[data-action="accept-session"][data-job-id="%s"]' % target_job
        btn = page.locator(sel)
        if btn.count() and btn.first.is_visible():
            accept_btn = btn.first
            log("session image ready for job", target_job)
            break
        # surface failure tiles
        fails = page.locator(".qamera-output.qamera-vote--rejected").count()
        log("waiting... rejected/failed tiles:", fails, "| getJob polls:", len(ajax["getJob"]))

    page.screenshot(path=OUT + "/b2-session-generated.png", full_page=True)
    if accept_btn is None:
        log("SESSION DID NOT COMPLETE in time. last getJob:", ajax["getJob"][-1:] )
        browser.close()
        raise SystemExit(1)

    # 7) accept -> publish to gallery
    accept_btn.click()
    deadline = time.time() + 60
    while time.time() < deadline and ajax["acceptSession"] is None:
        page.wait_for_timeout(1000)
    acc = ajax["acceptSession"]
    log("acceptSession:", acc)
    page.wait_for_timeout(1500)
    page.screenshot(path=OUT + "/b3-accepted.png", full_page=True)

    if not acc or not acc.get("ok"):
        log("acceptSession FAILED")
        browser.close()
        raise SystemExit(1)

    log("PUBLISHED id_image:", acc.get("id_image"), "duplicate:", acc.get("duplicate"))
    browser.close()
    log("DONE")
