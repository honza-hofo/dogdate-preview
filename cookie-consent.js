(function() {
    // Inject CSS
    var style = document.createElement('style');
    style.textContent = '\
    .cookie-banner { position: fixed; bottom: 0; left: 0; right: 0; background: #fff; z-index: 2000; box-shadow: 0 -4px 20px rgba(0,0,0,0.1); padding: 28px 30px; display: flex; align-items: center; justify-content: center; gap: 40px; transform: translateY(100%); transition: transform 0.4s ease; }\
    .cookie-banner.visible { transform: translateY(0); }\
    .cookie-banner-text { max-width: 600px; }\
    .cookie-banner-text h3 { font-family: "Montserrat", sans-serif; font-size: 16px; font-weight: 700; color: #222; margin-bottom: 6px; }\
    .cookie-banner-text p { font-size: 13px; color: #777; line-height: 1.6; }\
    .cookie-banner-text a { color: #E30A14; text-decoration: underline; cursor: pointer; font-weight: 500; }\
    .cookie-banner-btns { display: flex; gap: 12px; flex-shrink: 0; }\
    .cookie-btn { font-family: "Montserrat", sans-serif; font-size: 13px; font-weight: 700; padding: 14px 28px; border-radius: 50px; cursor: pointer; transition: all 0.3s ease; border: 2px solid transparent; text-transform: uppercase; letter-spacing: 0.5px; }\
    .cookie-btn-accept { background: #222; color: #fff; border-color: #222; }\
    .cookie-btn-accept:hover { background: #E30A14; border-color: #E30A14; }\
    .cookie-btn-reject { background: #fff; color: #333; border-color: #ddd; }\
    .cookie-btn-reject:hover { border-color: #333; }\
    .cookie-modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 3500; align-items: center; justify-content: center; }\
    .cookie-modal-overlay.open { display: flex; }\
    .cookie-modal { background: #fff; border-radius: 16px; max-width: 560px; width: 90%; max-height: 85vh; overflow-y: auto; padding: 40px; position: relative; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }\
    .cookie-modal-close { position: absolute; top: 14px; right: 18px; background: none; border: none; font-size: 28px; color: #999; cursor: pointer; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: all 0.2s; }\
    .cookie-modal-close:hover { background: #f0f0f0; color: #333; }\
    .cookie-modal h2 { font-family: "Montserrat", sans-serif; font-size: 22px; font-weight: 800; color: #222; margin-bottom: 24px; }\
    .cookie-modal h3 { font-family: "Montserrat", sans-serif; font-size: 16px; font-weight: 700; color: #E30A14; margin-bottom: 10px; }\
    .cookie-modal > p { font-size: 14px; color: #666; line-height: 1.7; margin-bottom: 24px; }\
    .cookie-category { border: 1px solid #eee; border-radius: 12px; margin-bottom: 12px; overflow: hidden; }\
    .cookie-category-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; cursor: pointer; user-select: none; }\
    .cookie-category-header span { font-family: "Montserrat", sans-serif; font-size: 14px; font-weight: 600; color: #333; }\
    .cookie-category-header .chevron { width: 18px; height: 18px; color: #999; transition: transform 0.3s; margin-right: 8px; }\
    .cookie-category-header .chevron.rotated { transform: rotate(180deg); }\
    .cookie-category-body { padding: 0 20px 16px; font-size: 13px; color: #777; line-height: 1.7; display: none; }\
    .cookie-category-body.open { display: block; }\
    .cookie-toggle { position: relative; width: 44px; height: 24px; flex-shrink: 0; }\
    .cookie-toggle input { opacity: 0; width: 0; height: 0; }\
    .cookie-toggle-slider { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: #ccc; border-radius: 24px; cursor: pointer; transition: background 0.3s; }\
    .cookie-toggle-slider::before { content: ""; position: absolute; width: 18px; height: 18px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: transform 0.3s; }\
    .cookie-toggle input:checked + .cookie-toggle-slider { background: #E30A14; }\
    .cookie-toggle input:checked + .cookie-toggle-slider::before { transform: translateX(20px); }\
    .cookie-toggle input:disabled + .cookie-toggle-slider { opacity: 0.6; cursor: default; }\
    .cookie-info-box { background: #f8f9fa; border-radius: 12px; padding: 20px; margin: 20px 0; }\
    .cookie-info-box h4 { font-family: "Montserrat", sans-serif; font-size: 14px; font-weight: 700; color: #222; margin-bottom: 6px; }\
    .cookie-info-box p { font-size: 13px; color: #777; line-height: 1.7; }\
    .cookie-info-box a { color: #E30A14; text-decoration: underline; }\
    .cookie-modal-btns { display: flex; gap: 12px; margin-top: 24px; flex-wrap: wrap; }\
    .cookie-modal-btns .cookie-btn { flex: 1; min-width: 140px; text-align: center; }\
    .cookie-btn-save { background: #fff; color: #333; border-color: #ddd; }\
    .cookie-btn-save:hover { border-color: #333; }\
    @media (max-width: 768px) {\
        .cookie-banner { flex-direction: column; gap: 20px; padding: 24px 20px; text-align: center; }\
        .cookie-banner-btns { width: 100%; }\
        .cookie-btn { flex: 1; padding: 12px 16px; font-size: 12px; }\
        .cookie-modal { padding: 30px 24px; }\
        .cookie-modal-btns { flex-direction: column; }\
        .cookie-modal-btns .cookie-btn { min-width: auto; }\
    }';
    document.head.appendChild(style);

    // Inject HTML
    var bannerHTML = '\
    <div class="cookie-banner" id="cookieBanner">\
        <div class="cookie-banner-text">\
            <h3>Pou\u017E\u00EDv\u00E1me cookies</h3>\
            <p>Soubory cookies pou\u017E\u00EDv\u00E1me na tomto webu. Pou\u017E\u00EDv\u00E1n\u00EDm tohoto webu souhlas\u00EDte s ukl\u00E1d\u00E1n\u00EDm a pou\u017E\u00EDv\u00E1n\u00EDm nezbytn\u00FDch soubor\u016F cookies. Zakliknut\u00EDm tla\u010D\u00EDtka "P\u0159ijmout v\u0161e" n\u00E1m ud\u011Bl\u00EDte souhlas k ukl\u00E1d\u00E1n\u00ED a pou\u017E\u00EDv\u00E1n\u00ED i dal\u0161\u00EDch soubor\u016F cookies jako jsou analytick\u00E9 a marketingov\u00E9. <a onclick="window._cookieOpenSettings()">Nastaven\u00ED</a></p>\
        </div>\
        <div class="cookie-banner-btns">\
            <button class="cookie-btn cookie-btn-accept" onclick="window._cookieAcceptAll()">P\u0159ijmout v\u0161e</button>\
            <button class="cookie-btn cookie-btn-reject" onclick="window._cookieRejectAll()">Odm\u00EDtnout</button>\
        </div>\
    </div>\
    <div class="cookie-modal-overlay" id="cookieModal">\
        <div class="cookie-modal">\
            <button class="cookie-modal-close" onclick="window._cookieCloseSettings()">&times;</button>\
            <h2>Nastaven\u00ED cookies</h2>\
            <h3>Vyu\u017Eit\u00ED cookies</h3>\
            <p>Soubory cookies pou\u017E\u00EDv\u00E1me k zaji\u0161t\u011Bn\u00ED z\u00E1kladn\u00EDch funkc\u00ED webu jako je p\u0159ihl\u00E1\u0161en\u00ED nebo odes\u00EDl\u00E1n\u00ED jednotliv\u00FDch formul\u00E1\u0159\u016F a ke zlep\u0161en\u00ED va\u0161eho online z\u00E1\u017Eitku. Pro ka\u017Edou kategorii se m\u016F\u017Eete rozhodnout, zda se chcete p\u0159ihl\u00E1sit/odhl\u00E1sit, a to na z\u00E1klad\u011B va\u0161eho svobodn\u00E9ho rozhodnut\u00ED.</p>\
            <div class="cookie-category">\
                <div class="cookie-category-header" onclick="window._cookieToggleCategory(this)">\
                    <div style="display:flex;align-items:center;gap:8px;">\
                        <svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>\
                        <span>Nezbytn\u00E9 cookies</span>\
                    </div>\
                    <label class="cookie-toggle"><input type="checkbox" checked disabled><span class="cookie-toggle-slider"></span></label>\
                </div>\
                <div class="cookie-category-body">Tyto cookies jsou nezbytn\u00E9 pro spr\u00E1vn\u00E9 fungov\u00E1n\u00ED webu. Zaji\u0161\u0165uj\u00ED z\u00E1kladn\u00ED funkce jako navigaci na str\u00E1nce a p\u0159\u00EDstup k zabezpe\u010Den\u00FDm oblastem. Bez t\u011Bchto cookies nem\u016F\u017Ee web spr\u00E1vn\u011B fungovat.</div>\
            </div>\
            <div class="cookie-category">\
                <div class="cookie-category-header" onclick="window._cookieToggleCategory(this)">\
                    <div style="display:flex;align-items:center;gap:8px;">\
                        <svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>\
                        <span>Analytick\u00E9 cookies</span>\
                    </div>\
                    <label class="cookie-toggle"><input type="checkbox" id="cookieAnalytics"><span class="cookie-toggle-slider"></span></label>\
                </div>\
                <div class="cookie-category-body">Analytick\u00E9 cookies n\u00E1m pom\u00E1haj\u00ED pochopit, jak n\u00E1v\u0161t\u011Bvn\u00EDci pou\u017E\u00EDvaj\u00ED n\u00E1\u0161 web. Sb\u00EDraj\u00ED anonymn\u00ED \u00FAdaje o po\u010Dtu n\u00E1v\u0161t\u011Bvn\u00EDk\u016F, nejnav\u0161t\u011Bvovan\u011Bj\u0161\u00EDch str\u00E1nk\u00E1ch a zp\u016Fsobu proch\u00E1zen\u00ED webu.</div>\
            </div>\
            <div class="cookie-category">\
                <div class="cookie-category-header" onclick="window._cookieToggleCategory(this)">\
                    <div style="display:flex;align-items:center;gap:8px;">\
                        <svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>\
                        <span>Marketingov\u00E9 cookies</span>\
                    </div>\
                    <label class="cookie-toggle"><input type="checkbox" id="cookieMarketing"><span class="cookie-toggle-slider"></span></label>\
                </div>\
                <div class="cookie-category-body">Marketingov\u00E9 cookies se pou\u017E\u00EDvaj\u00ED ke sledov\u00E1n\u00ED n\u00E1v\u0161t\u011Bvn\u00EDk\u016F nap\u0159\u00ED\u010D weby. \u00DA\u010Delem je zobrazovat reklamy, kter\u00E9 jsou relevantn\u00ED a zaj\u00EDmav\u00E9 pro konkr\u00E9tn\u00EDho u\u017Eivatele.</div>\
            </div>\
            <div class="cookie-info-box">\
                <h4>V\u00EDce informac\u00ED</h4>\
                <p>M\u00E1te-li jak\u00E9koli dotazy t\u00FDkaj\u00EDc\u00ED se na\u0161ich z\u00E1sad t\u00FDkaj\u00EDc\u00EDch se soubor\u016F cookies a va\u0161ich zvolen\u00FDch preferenc\u00ED, <a href="kontakt.html">kontaktujte n\u00E1s</a>.</p>\
            </div>\
            <div class="cookie-modal-btns">\
                <button class="cookie-btn cookie-btn-accept" onclick="window._cookieAcceptAll()">P\u0159ijmout v\u0161e</button>\
                <button class="cookie-btn cookie-btn-reject" onclick="window._cookieRejectAll()">Odm\u00EDtnout v\u0161e</button>\
                <button class="cookie-btn cookie-btn-save" onclick="window._cookieSaveSettings()">Ulo\u017Eit nastaven\u00ED</button>\
            </div>\
        </div>\
    </div>';

    var container = document.createElement('div');
    container.innerHTML = bannerHTML;
    while (container.firstChild) {
        document.body.appendChild(container.firstChild);
    }

    // Logic
    var cookieConsentGiven = false;

    function getCookieConsent() {
        try { return localStorage.getItem('cookieConsent'); } catch(e) { return null; }
    }
    function setCookieConsent(val) {
        try { localStorage.setItem('cookieConsent', val); } catch(e) {}
        cookieConsentGiven = true;
    }
    function showBanner() {
        setTimeout(function() {
            document.getElementById('cookieBanner').classList.add('visible');
        }, 500);
    }
    function hideBanner() {
        document.getElementById('cookieBanner').classList.remove('visible');
    }

    window._cookieAcceptAll = function() {
        setCookieConsent(JSON.stringify({ necessary: true, analytics: true, marketing: true }));
        hideBanner();
        window._cookieCloseSettings();
    };
    window._cookieRejectAll = function() {
        setCookieConsent(JSON.stringify({ necessary: true, analytics: false, marketing: false }));
        hideBanner();
        window._cookieCloseSettings();
    };
    window._cookieSaveSettings = function() {
        var analytics = document.getElementById('cookieAnalytics').checked;
        var marketing = document.getElementById('cookieMarketing').checked;
        setCookieConsent(JSON.stringify({ necessary: true, analytics: analytics, marketing: marketing }));
        hideBanner();
        window._cookieCloseSettings();
    };
    window._cookieOpenSettings = function() {
        hideBanner();
        document.getElementById('cookieModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    };
    window._cookieCloseSettings = function() {
        document.getElementById('cookieModal').classList.remove('open');
        document.body.style.overflow = '';
        if (!cookieConsentGiven && !getCookieConsent()) showBanner();
    };
    window._cookieToggleCategory = function(header) {
        var body = header.nextElementSibling;
        var chevron = header.querySelector('.chevron');
        body.classList.toggle('open');
        chevron.classList.toggle('rotated');
    };

    // Close modal on overlay click
    document.getElementById('cookieModal').addEventListener('click', function(e) {
        if (e.target === this) window._cookieCloseSettings();
    });

    // Show banner if no consent
    if (!getCookieConsent()) {
        showBanner();
    }
})();
