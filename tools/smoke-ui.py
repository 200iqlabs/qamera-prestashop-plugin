"""
M2 smoke: log into PS8 admin, open a product, reveal the Qamera AI tab,
screenshot the empty-state. NOT shipped. Outputs to tools/_shots/.
"""
import re
import sys
from playwright.sync_api import sync_playwright

BASE = "http://localhost:8082"
ADMIN = BASE + "/admin-dev/"
EMAIL = "admin@qamera.test"
PASSWD = "qameraadmin1"
OUT = "tools/_shots"

import os
os.makedirs(OUT, exist_ok=True)


def log(*a):
    print(*a, flush=True)


with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_page(viewport={"width": 1500, "height": 1200})

    # 1) Login (submit via Enter — the button click does not navigate reliably).
    page.goto(ADMIN, wait_until="networkidle")
    if page.locator('input[name="email"]').count():
        page.fill('input[name="email"]', EMAIL)
        page.fill('input[name="passwd"]', PASSWD)
        page.locator('input[name="passwd"]').press("Enter")
        page.wait_for_url(lambda u: "AdminLogin" not in u, timeout=20000)
    log("after login url:", page.url)

    # 2) PS8 uses the Symfony product page (/sell/catalog/products-v2). Grab the
    #    Products menu href (carries _token), open the list.
    hrefs = page.eval_on_selector_all(
        "a", "els=>els.map(e=>e.href).filter(h=>h.indexOf('sell/catalog/products?')>-1)"
    )
    log("products href:", hrefs[0] if hrefs else "NONE")
    page.goto(hrefs[0], wait_until="networkidle")
    page.wait_for_timeout(3000)

    # 3) Open first product edit (/products-v2/{id}/edit, no #anchor).
    edit_href = page.eval_on_selector_all(
        "table a",
        "els=>els.map(e=>e.getAttribute('href')).find(x=>x&&/products-v2\\/\\d+\\/edit/.test(x)&&x.indexOf('#')==-1)",
    )
    log("edit href:", edit_href)
    page.goto(BASE + edit_href, wait_until="networkidle")
    page.wait_for_timeout(4000)
    log("product page url:", page.url)

    # 4) The displayAdminProductsExtra hook renders in the "Moduły" tab
    #    (#product_extra_modules-tab). Activate it (Bootstrap data-bs-target).
    for sel in [
        '[data-bs-target="#product_extra_modules-tab"]',
        '[href="#product_extra_modules-tab"]',
        '#product_extra_modules-tab-nav',
    ]:
        t = page.locator(sel)
        if t.count():
            try:
                t.first.click(timeout=4000)
                log("clicked tab via", sel)
                break
            except Exception as e:
                log("tab click fail", sel, e)
    # Force the pane visible regardless (Bootstrap classes), so we always capture.
    page.evaluate(
        "()=>{const p=document.querySelector('#product_extra_modules-tab');"
        "if(p){p.classList.add('show','active');p.style.display='block';}}"
    )
    page.wait_for_timeout(1000)

    # The hook panel is collapsed behind the module card's "Konfiguruj" button.
    for label in ["Konfiguruj", "Configure"]:
        cfg = page.locator(f'button:has-text("{label}"), a:has-text("{label}")')
        if cfg.count():
            try:
                cfg.first.click(timeout=4000)
                log("clicked module configure:", label)
                page.wait_for_timeout(2000)
                break
            except Exception as e:
                log("configure click fail", label, e)
    page.wait_for_timeout(1500)

    node = page.locator("#qamera-product-tab")
    log("qamera-product-tab count:", node.count(), "| visible:", node.first.is_visible() if node.count() else "-")

    page.screenshot(path=OUT + "/01-product-fullpage.png", full_page=True)

    if node.count():
        try:
            node.first.scroll_into_view_if_needed(timeout=5000)
        except Exception as e:
            log("scroll skip:", e)
        page.wait_for_timeout(500)
        try:
            node.first.screenshot(path=OUT + "/02-qamera-tab.png")
        except Exception as e:
            log("element shot skip:", e)
        try:
            txt = node.first.inner_text()
            log("---- TAB TEXT ----")
            log(txt[:1500])
        except Exception as e:
            log("inner_text skip:", e)
        for sel in ["#qamera-preset", "#qamera-model", "#qamera-scenery", "#qamera-ai-model"]:
            log("options", sel, "=", page.locator(sel + " option").count())
        log("empty-state present:", page.locator("#qamera-product-tab .qamera-empty").count())
        log("catalog error present:", page.locator("#qamera-product-tab .qamera-alert--error").count())
    else:
        log("TAB NOT FOUND — body snippet:")
        log(page.locator("body").inner_text()[:800])

    browser.close()
