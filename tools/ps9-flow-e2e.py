"""
PS9 part-2 e2e (consumes real Qamera credits, count=1): full Core Flow on PS9.
  A: register a gallery image as source -> generate packshot -> poll -> accept.
  reload (proves state reconstructs from API).
  B: generate session from the approved packshot -> poll -> accept -> gallery import.
Confirms PrestaShop 9 runs the whole pipeline, not just rendering. NOT shipped.
"""
import json
import time
from playwright.sync_api import sync_playwright

BASE = "http://localhost:8091"
OUT = "tools/_shots"
EDIT_URL = None  # discovered (tokenized) after login; PS9 edit route needs _token


def log(*a):
    print(*a, flush=True)


ajax = {}
getjob = []


def open_tab(pg):
    for attempt in range(3):
        t = pg.locator('[data-bs-target="#product_extra_modules-tab"], [href="#product_extra_modules-tab"]')
        if t.count():
            t.first.click(); pg.wait_for_timeout(1200)
        cfg = pg.locator('button:has-text("Konfiguruj"), a:has-text("Konfiguruj")')
        if cfg.count():
            cfg.first.click(); pg.wait_for_timeout(2500)
        try:
            pg.wait_for_selector("#qamera-product-tab", state="visible", timeout=8000)
            pg.wait_for_timeout(1500)  # let the gallery/state render
            return
        except Exception:
            log("open_tab retry", attempt + 1)
    log("WARN: #qamera-product-tab not visible after retries")


with sync_playwright() as p:
    b = p.chromium.launch(headless=True)
    pg = b.new_page(viewport={"width": 1500, "height": 1500})
    pg.set_default_navigation_timeout(60000)
    pg.set_default_timeout(30000)

    def on_resp(r):
        if "AdminQameraAjax" not in r.url:
            return
        act = r.url.split("action=")[1].split("&")[0] if "action=" in r.url else "?"
        try:
            raw = r.text()
        except Exception:
            log("AJAX", act, "status", r.status, "(no body)")
            return
        try:
            data = json.loads(raw)
        except Exception:
            log("AJAX", act, "status", r.status, "NON-JSON:", raw[:160])
            return
        for k in ("registerImage", "generatePackshot", "acceptJob", "generateSession", "acceptSession"):
            if "action=" + k in r.url:
                ajax[k] = data
                log("AJAX", k, "->", json.dumps(data)[:240])
        if "action=getJob" in r.url:
            getjob.append(data)

    pg.on("response", on_resp)

    # login (PS9 admin never reaches networkidle: dashboard widgets keep polling)
    pg.goto(BASE + "/admin-dev/", wait_until="commit")
    pg.wait_for_selector('input[name="email"]', timeout=20000)
    pg.fill('input[name="email"]', "admin@qamera.test")
    pg.fill('input[name="passwd"]', "qameraadmin1")
    pg.locator('input[name="passwd"]').press("Enter")
    for _ in range(30):
        pg.wait_for_timeout(1000)
        if "AdminLogin" not in pg.url and pg.locator('input[name="email"]').count() == 0:
            break
    log("login OK, url:", pg.url[:60])

    # discover the tokenized edit URL (PS9 edit route requires _token)
    h = pg.eval_on_selector_all(
        "a",
        "els=>els.map(e=>e.href).filter(x=>x&&x.indexOf('sell/catalog/products/')>-1&&x.indexOf('create')==-1&&x.indexOf('preferences')==-1)",
    )
    pg.goto(h[0], wait_until="commit"); pg.wait_for_timeout(2500)
    EDIT_URL = pg.eval_on_selector_all(
        "table a, a",
        "els=>els.map(e=>e.getAttribute('href')).find(x=>x&&x.indexOf('sell/catalog/products')>-1&&x.indexOf('/edit')>-1&&x.indexOf('#')==-1)",
    )
    if EDIT_URL and EDIT_URL.startswith("/"):
        EDIT_URL = BASE + EDIT_URL
    log("edit url:", EDIT_URL)
    if not EDIT_URL:
        raise SystemExit("no product to edit on PS9")

    # ---- Flow A ----
    pg.goto(EDIT_URL, wait_until="commit"); pg.wait_for_timeout(2500)
    open_tab(pg)

    reg = pg.locator('[data-action="register-image"]')
    if not reg.count():
        # already registered from a previous run? look for generate-packshot
        log("no register-image button; gallery state:",
            pg.locator('.qamera-gallery__item').count(), "items")
    else:
        reg.first.click(); log("clicked register-image")
        for _ in range(60):
            pg.wait_for_timeout(1000)
            if ajax.get("registerImage"):
                break
        log("registerImage:", ajax.get("registerImage"))

    genp = pg.locator('[data-action="generate-packshot"]')
    pg.wait_for_timeout(1000)
    if not genp.count():
        pg.screenshot(path=OUT + "/ps9-e2e-noGenP.png", full_page=True)
        raise SystemExit("no generate-packshot button after register")
    genp.first.click(); log("clicked generate-packshot")

    # wait for generatePackshot ok (JS retries on analysis_pending internally)
    deadline = time.time() + 120
    while time.time() < deadline and not (ajax.get("generatePackshot") or {}).get("ok"):
        pg.wait_for_timeout(2000)
    gp = ajax.get("generatePackshot")
    log("generatePackshot:", gp)
    if not gp or not gp.get("ok"):
        pg.screenshot(path=OUT + "/ps9-e2e-genPfail.png", full_page=True)
        raise SystemExit("generatePackshot failed")

    # poll for the packshot accept button (data-vote=accept) to appear
    pk_job = gp.get("job_id")
    deadline = time.time() + 360
    acc = None
    while time.time() < deadline:
        pg.wait_for_timeout(5000)
        btn = pg.locator('[data-vote="accept"][data-job-id="%s"]' % pk_job)
        if btn.count() and btn.first.is_visible():
            acc = btn.first; log("packshot ready, job", pk_job); break
        log("waiting packshot... getJob polls:", len(getjob))
    pg.screenshot(path=OUT + "/ps9-e2e-packshot.png", full_page=True)
    if not acc:
        raise SystemExit("packshot did not complete; last getJob: %s" % (getjob[-1:],))

    acc.click()
    for _ in range(30):
        pg.wait_for_timeout(1000)
        if ajax.get("acceptJob"):
            break
    log("acceptJob:", ajax.get("acceptJob"))

    # ---- reload: state must reconstruct from API ----
    pg.goto(EDIT_URL, wait_until="commit"); pg.wait_for_timeout(2500)
    open_tab(pg)
    gens = pg.locator('[data-action="generate-session"]')
    log("after reload, generate-session buttons:", gens.count())
    if not gens.count():
        pg.screenshot(path=OUT + "/ps9-e2e-noGenS.png", full_page=True)
        raise SystemExit("no generate-session after accept+reload (state not reconstructed)")

    # ---- Flow B ----
    pg.evaluate("()=>{const c=document.querySelector('#qamera-count'); if(c) c.value='1';}")
    gens.first.scroll_into_view_if_needed(); gens.first.click(); log("clicked generate-session")
    deadline = time.time() + 30
    while time.time() < deadline and not ajax.get("generateSession"):
        pg.wait_for_timeout(1000)
    gs = ajax.get("generateSession")
    log("generateSession:", gs)
    if not gs or not gs.get("ok"):
        pg.screenshot(path=OUT + "/ps9-e2e-genSfail.png", full_page=True)
        raise SystemExit("generateSession failed")
    s_job = (gs.get("job_ids") or [None])[0]

    deadline = time.time() + 360
    sacc = None
    while time.time() < deadline:
        pg.wait_for_timeout(5000)
        btn = pg.locator('[data-action="accept-session"][data-job-id="%s"]' % s_job)
        if btn.count() and btn.first.is_visible():
            sacc = btn.first; log("session image ready, job", s_job); break
        log("waiting session... getJob polls:", len(getjob))
    pg.screenshot(path=OUT + "/ps9-e2e-session.png", full_page=True)
    if not sacc:
        raise SystemExit("session did not complete; last getJob: %s" % (getjob[-1:],))

    sacc.click()
    for _ in range(60):
        pg.wait_for_timeout(1000)
        if ajax.get("acceptSession"):
            break
    asn = ajax.get("acceptSession")
    log("acceptSession:", asn)
    pg.screenshot(path=OUT + "/ps9-e2e-published.png", full_page=True)
    if not asn or not asn.get("ok"):
        raise SystemExit("acceptSession failed")
    log("PUBLISHED id_image:", asn.get("id_image"), "duplicate:", asn.get("duplicate"))
    b.close()
    log("E2E DONE")
