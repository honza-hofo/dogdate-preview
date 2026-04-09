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
    .cookie-btn-reject { background: #fff; color: #333; border-color: #222; }\
    .cookie-btn-reject:hover { background: #f4f5f7; border-color: #333; }\
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
    .cookie-btn-save { background: #fff; color: #333; border-color: #222; }\
    .cookie-btn-save:hover { background: #f4f5f7; border-color: #333; }\
    .cookie-btn-withdraw { background: none; border: none; color: #999; font-size: 12px; cursor: pointer; text-decoration: underline; padding: 8px 0; margin-top: 4px; }\
    .cookie-btn-withdraw:hover { color: #E30A14; }\
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
            <p>Tento web pou\u017E\u00EDv\u00E1 nezbytn\u00E9 cookies pro spr\u00E1vn\u00E9 fungov\u00E1n\u00ED. Analytick\u00E9 a marketingov\u00E9 cookies pou\u017E\u00EDv\u00E1me <strong>pouze s va\u0161\u00EDm souhlasem</strong>. Bez va\u0161eho souhlasu nebudou ne-nezbytn\u00E9 cookies aktivov\u00E1ny. <a onclick="window._cookieOpenSettings()">Nastaven\u00ED</a> &middot; <a href="dogdate-podminky.html">Z\u00E1sady ochrany \u00FAdaj\u016F</a></p>\
        </div>\
        <div class="cookie-banner-btns">\
            <button class="cookie-btn cookie-btn-accept" onclick="window._cookieAcceptAll()">P\u0159ijmout v\u0161e</button>\
            <button class="cookie-btn cookie-btn-reject" onclick="window._cookieRejectAll()">Odm\u00EDtnout nepovinn\u00E9</button>\
        </div>\
    </div>\
    <div class="cookie-modal-overlay" id="cookieModal">\
        <div class="cookie-modal">\
            <button class="cookie-modal-close" onclick="window._cookieCloseSettings()">&times;</button>\
            <h2>Nastaven\u00ED cookies</h2>\
            <h3>Spr\u00E1va souhlas\u016F s cookies</h3>\
            <p>Dle Na\u0159\u00EDzen\u00ED GDPR a z\u00E1kona o elektronick\u00FDch komunikac\u00EDch v\u00E1s informujeme o pou\u017E\u00EDvan\u00FDch cookies. Nezbytn\u00E9 cookies nelze vypnout, ostatn\u00ED kategorie m\u016F\u017Eete ovl\u00E1dat n\u00ED\u017Ee. Sv\u016Fj souhlas m\u016F\u017Eete kdykoli zm\u011Bnit nebo odvolat.</p>\
            <div class="cookie-category">\
                <div class="cookie-category-header" onclick="window._cookieToggleCategory(this)">\
                    <div style="display:flex;align-items:center;gap:8px;">\
                        <svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>\
                        <span>Nezbytn\u00E9 cookies</span>\
                    </div>\
                    <label class="cookie-toggle"><input type="checkbox" checked disabled><span class="cookie-toggle-slider"></span></label>\
                </div>\
                <div class="cookie-category-body">Tyto cookies jsou nezbytn\u00E9 pro spr\u00E1vn\u00E9 fungov\u00E1n\u00ED webu (p\u0159ihl\u00E1\u0161en\u00ED, session, CSRF ochrana). Pr\u00E1vn\u00ED z\u00E1klad: opr\u00E1vn\u011Bn\u00FD z\u00E1jem (\u010Dl. 6 odst. 1 p\u00EDsm. f) GDPR). Nelze je vypnout.</div>\
            </div>\
            <div class="cookie-category">\
                <div class="cookie-category-header" onclick="window._cookieToggleCategory(this)">\
                    <div style="display:flex;align-items:center;gap:8px;">\
                        <svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>\
                        <span>Analytick\u00E9 cookies</span>\
                    </div>\
                    <label class="cookie-toggle"><input type="checkbox" id="cookieAnalytics"><span class="cookie-toggle-slider"></span></label>\
                </div>\
                <div class="cookie-category-body">Analytick\u00E9 cookies n\u00E1m pom\u00E1haj\u00ED pochopit, jak n\u00E1v\u0161t\u011Bvn\u00EDci pou\u017E\u00EDvaj\u00ED web. Sb\u00EDraj\u00ED anonymn\u00ED statistiky. Pr\u00E1vn\u00ED z\u00E1klad: souhlas (\u010Dl. 6 odst. 1 p\u00EDsm. a) GDPR). Bez va\u0161eho souhlasu nebudou aktivov\u00E1ny.</div>\
            </div>\
            <div class="cookie-category">\
                <div class="cookie-category-header" onclick="window._cookieToggleCategory(this)">\
                    <div style="display:flex;align-items:center;gap:8px;">\
                        <svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>\
                        <span>Marketingov\u00E9 cookies</span>\
                    </div>\
                    <label class="cookie-toggle"><input type="checkbox" id="cookieMarketing"><span class="cookie-toggle-slider"></span></label>\
                </div>\
                <div class="cookie-category-body">Marketingov\u00E9 cookies slou\u017E\u00ED k zobrazov\u00E1n\u00ED relevantn\u00EDch reklam. Pr\u00E1vn\u00ED z\u00E1klad: souhlas (\u010Dl. 6 odst. 1 p\u00EDsm. a) GDPR). Bez va\u0161eho souhlasu nebudou aktivov\u00E1ny.</div>\
            </div>\
            <div class="cookie-info-box">\
                <h4>Spr\u00E1vce \u00FAdaj\u016F</h4>\
                <p>MANMAT s.r.o., I\u010CO: 03166236<br>Kontakt: <a href="mailto:formanek@manmat.cz">formanek@manmat.cz</a><br><a href="dogdate-podminky.html">Z\u00E1sady ochrany osobn\u00EDch \u00FAdaj\u016F</a></p>\
            </div>\
            <div class="cookie-modal-btns">\
                <button class="cookie-btn cookie-btn-accept" onclick="window._cookieAcceptAll()">P\u0159ijmout v\u0161e</button>\
                <button class="cookie-btn cookie-btn-reject" onclick="window._cookieRejectAll()">Odm\u00EDtnout nepovinn\u00E9</button>\
                <button class="cookie-btn cookie-btn-save" onclick="window._cookieSaveSettings()">Ulo\u017Eit nastaven\u00ED</button>\
            </div>\
            <div style="text-align:center; margin-top: 8px;">\
                <button class="cookie-btn-withdraw" onclick="window._cookieWithdrawAll()">Odvolat v\u0161echny souhlasy</button>\
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
        try {
            localStorage.setItem('cookieConsent', val);
            localStorage.setItem('cookieConsentDate', new Date().toISOString());
        } catch(e) {}
        // Also set a simple cookie for server-side detection
        var consent = JSON.parse(val);
        document.cookie = 'cookie_consent=' + encodeURIComponent(val) + ';path=/;max-age=31536000;SameSite=Lax';
        cookieConsentGiven = true;
        applyConsent(consent);
    }
    function applyConsent(consent) {
        // Block or allow non-essential scripts based on consent
        if (consent.analytics) {
            enableAnalytics();
        } else {
            disableAnalytics();
        }
        if (consent.marketing) {
            enableMarketing();
        } else {
            disableMarketing();
        }
    }
    function enableAnalytics() {
        // Activate analytics scripts (placeholder - add GA/Matomo here)
        document.querySelectorAll('script[data-cookie-category="analytics"]').forEach(function(s) {
            if (!s.dataset.loaded) {
                var ns = document.createElement('script');
                ns.src = s.dataset.src || '';
                ns.dataset.loaded = 'true';
                document.head.appendChild(ns);
            }
        });
    }
    function disableAnalytics() {
        // Remove analytics cookies
        var analyticsCookies = ['_ga', '_gat', '_gid', '_pk_id', '_pk_ses'];
        analyticsCookies.forEach(function(name) {
            document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/';
        });
    }
    function enableMarketing() {
        document.querySelectorAll('script[data-cookie-category="marketing"]').forEach(function(s) {
            if (!s.dataset.loaded) {
                var ns = document.createElement('script');
                ns.src = s.dataset.src || '';
                ns.dataset.loaded = 'true';
                document.head.appendChild(ns);
            }
        });
    }
    function disableMarketing() {
        var marketingCookies = ['_fbp', '_fbc', 'fr'];
        marketingCookies.forEach(function(name) {
            document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/';
        });
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
    window._cookieWithdrawAll = function() {
        setCookieConsent(JSON.stringify({ necessary: true, analytics: false, marketing: false }));
        document.getElementById('cookieAnalytics').checked = false;
        document.getElementById('cookieMarketing').checked = false;
        window._cookieCloseSettings();
        // Show banner again so user knows consent was withdrawn
        showBanner();
    };
    window._cookieOpenSettings = function() {
        hideBanner();
        // Restore toggle states from saved consent
        var saved = getCookieConsent();
        if (saved) {
            try {
                var c = JSON.parse(saved);
                document.getElementById('cookieAnalytics').checked = !!c.analytics;
                document.getElementById('cookieMarketing').checked = !!c.marketing;
            } catch(e) {}
        }
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

    // Public method to re-open cookie settings (for footer link etc.)
    window.openCookieSettings = window._cookieOpenSettings;

    // Close modal on overlay click
    document.getElementById('cookieModal').addEventListener('click', function(e) {
        if (e.target === this) window._cookieCloseSettings();
    });

    // On load: apply saved consent or show banner
    var existing = getCookieConsent();
    if (existing) {
        try {
            var c = JSON.parse(existing);
            applyConsent(c);
            cookieConsentGiven = true;
        } catch(e) {
            showBanner();
        }
    } else {
        // GDPR: block non-essential by default until consent
        disableAnalytics();
        disableMarketing();
        showBanner();
    }
})();
