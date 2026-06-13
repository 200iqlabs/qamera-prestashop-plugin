"""
PS9 helper: open the qameraai module config (getContent), read the Model AI
dropdown (populated from the live API ai-models, output_type=image), pick the
first real option, save the form. Confirms QAMERA_AI_MODEL is set so Flow A/B
generation can run on PS9. NOT shipped.
"""
from playwright.sync_api import sync_playwright

BASE = "http://localhost:8091"

with sync_playwright() as p:
    b = p.chromium.launch(headless=True)
    pg = b.new_page(viewport={"width": 1400, "height": 1200})
    pg.goto(BASE + "/admin-dev/", wait_until="networkidle")
    pg.fill('input[name="email"]', "admin@qamera.test")
    pg.fill('input[name="passwd"]', "qameraadmin1")
    pg.locator('input[name="passwd"]').press("Enter")
    pg.wait_for_url(lambda u: "AdminLogin" not in u, timeout=20000)

    # Grab a valid admin token from any AdminModules link, build the configure URL.
    link = pg.eval_on_selector_all(
        "a", "els=>els.map(e=>e.href).find(x=>x&&x.indexOf('controller=AdminModules')>-1&&x.indexOf('token=')>-1)"
    )
    token = link.split("token=")[1].split("&")[0]
    cfg_url = BASE + "/admin-dev/index.php?controller=AdminModules&token=" + token + "&configure=qameraai"
    print("configure url:", cfg_url)
    pg.goto(cfg_url, wait_until="networkidle")
    pg.wait_for_timeout(2000)

    sel = pg.locator('select[name="QAMERA_AI_MODEL"], input[name="QAMERA_AI_MODEL"]')
    print("AI model field count:", sel.count())
    if not sel.count():
        pg.screenshot(path="tools/_shots/ps9-config.png", full_page=True)
        raise SystemExit("no QAMERA_AI_MODEL field on config page")

    tag = sel.first.evaluate("e=>e.tagName.toLowerCase()")
    if tag == "select":
        opts = pg.eval_on_selector_all('select[name="QAMERA_AI_MODEL"] option', "els=>els.map(e=>({v:e.value,t:e.textContent.trim()}))")
        print("options:", opts)
        real = [o for o in opts if o["v"]]
        if not real:
            raise SystemExit("no real AI model options (API unreachable?)")
        pick = real[0]["v"]
        sel.first.select_option(pick)
        print("picked:", pick, "-", real[0]["t"])
    else:
        # text-id fallback when catalog unreachable — leave note
        print("AI model is a text field (catalog unreachable). Set manually.")
        raise SystemExit("text fallback — need a model id from API")

    pg.locator('button[name="submitQameraSettings"], #module_form_submit_btn, button:has-text("Zapisz")').first.click()
    pg.wait_for_timeout(2500)
    ok = pg.locator('.alert-success, .module_confirmation').count()
    print("save confirmation present:", ok)
    pg.screenshot(path="tools/_shots/ps9-config-saved.png", full_page=True)
    b.close()
    print("DONE")
