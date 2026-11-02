(() => {
    if (window.__mockupSweep) {
        return;
    }

    const HIDDEN_SELECTORS = [
        '#onetrust-consent-sdk', '#onetrust-banner-sdk', '#CybotCookiebotDialog', '#CybotCookiebotDialogBodyUnderlay',
        '#usercentrics-root', '#truste-consent-track', '#cookie-law-info-bar', '#cookie-notice', '#cookie-banner',
        '#cookieConsent', '#cookie-consent', '#cmplz-cookiebanner-container', '#moove_gdpr_cookie_info_bar',
        '#didomi-host', '#qc-cmp2-container', '#sp_message_container', '#axeptio_overlay', '#tarteaucitronRoot',
        '#iubenda-cs-banner', '#BorlabsCookieBox', '#lgpd-cookie', '#adopt-controller-button', '#adopt-banner',
        '.cc-window', '.cc-banner', '.cky-consent-container', '.cky-overlay', '.cmplz-cookiebanner',
        '.cookie-notice-container', '.cookiealert', '.cookie-consent', '.cookie-banner', '.cookies-banner',
        '.gdpr-banner', '.lgpd-banner', '.osano-cm-window', '.termly-styles-root', '.fc-consent-root',
        '.truste_overlay', '.truste_box_overlay', '[aria-label="cookieconsent"]',
    ];

    const ACCEPT_SELECTORS = [
        '#onetrust-accept-btn-handler',
        '#accept-recommended-btn-handler',
        '#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll',
        '#CybotCookiebotDialogBodyButtonAccept',
        '#didomi-notice-agree-button',
        '#truste-consent-button',
        '#cookie_action_close_header',
        '#wt-cli-accept-all-btn',
        '#cn-accept-cookie',
        '#moove_gdpr_cookie_modal .mgbutton',
        '.cky-btn-accept',
        '.cmplz-accept',
        '.cc-allow',
        '.cc-dismiss',
        '.qc-cmp2-summary-buttons button[mode="primary"]',
        '.fc-cta-consent',
        '.osano-cm-accept-all',
        '[data-testid="uc-accept-all-button"]',
        '[data-cookiebanner="accept_button"]',
        '[data-action="accept-all"]',
    ];

    const ACCEPT_TEXT = /^(aceitar|aceito|aceitar todos|aceitar tudo|aceitar e fechar|aceitar cookies|aceitar todos os cookies|permitir|permitir todos|permitir todos os cookies|concordo|eu concordo|concordar|concordar e fechar|entendi|ok|ok, entendi|prosseguir|continuar|fechar|accept|accept all|accept all cookies|accept cookies|allow|allow all|allow all cookies|agree|i agree|agree and close|got it|understood|okay)$/i;
    const CONSENT_CONTEXT = /cookie|lgpd|gdpr|privacidade|privacy|consent|consentimento|dados pessoais|rastreamento|tracking/i;
    const CLOSE_LABEL = /^(fechar|close|dismiss|cancelar|não, obrigado|nao, obrigado|agora não|agora nao|no thanks|no, thanks|maybe later|×|✕|✖|x)$/i;

    const CONSENT_TAG = /cookie|consent|lgpd|gdpr|privacy/i;
    const AD_NAMING = /(^|[\s_-])(ad|ads|advert|advertising|publicidade|anuncio|anchor-ad|sticky-ad|adhesion|dfp|gpt)([\s_-]|$)/i;

    const clicked = new WeakSet();
    const started = Date.now();

    function roots() {
        const list = [document];
        for (let index = 0; index < list.length; index += 1) {
            list[index].querySelectorAll('*').forEach((element) => {
                if (element.shadowRoot) {
                    list.push(element.shadowRoot);
                }
            });
        }
        return list;
    }

    function queryAll(selector, scope = roots()) {
        return scope.flatMap((root) => [...root.querySelectorAll(selector)]);
    }

    function parentOf(node) {
        if (node.parentElement) {
            return node.parentElement;
        }
        const root = node.getRootNode?.();
        return root instanceof ShadowRoot ? root.host : null;
    }

    function visible(element) {
        if (!(element instanceof HTMLElement)) {
            return false;
        }
        const rect = element.getBoundingClientRect();
        if (rect.width < 2 || rect.height < 2) {
            return false;
        }
        const style = getComputedStyle(element);
        return style.visibility !== 'hidden' && style.display !== 'none' && Number(style.opacity) > 0.05;
    }

    function label(element) {
        return (element.innerText || element.value || element.getAttribute('aria-label') || element.title || '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function press(element) {
        if (clicked.has(element) || !visible(element)) {
            return false;
        }
        clicked.add(element);
        element.click();
        return true;
    }

    function hasConsentContext(element) {
        let node = element;
        for (let depth = 0; node && depth < 8; depth += 1) {
            const text = (node.innerText || '').slice(0, 2000);
            if (CONSENT_CONTEXT.test(text) || CONSENT_CONTEXT.test(String(node.id) + ' ' + String(node.className) + ' ' + node.tagName)) {
                return true;
            }
            node = parentOf(node);
        }
        return false;
    }

    function overlayRoot(element) {
        let node = element;
        while (node && node !== document.body && node !== document.documentElement) {
            const style = getComputedStyle(node);
            if (style.position === 'fixed' || style.position === 'sticky' || node.getAttribute('role') === 'dialog' || node.getAttribute('aria-modal') === 'true' || node.tagName === 'DIALOG') {
                return node;
            }
            node = parentOf(node);
        }
        return null;
    }

    function acceptConsent(scope) {
        ACCEPT_SELECTORS.forEach((selector) => {
            queryAll(selector, scope).forEach(press);
        });

        queryAll('button, a, [role="button"], input[type="button"], input[type="submit"]', scope).forEach((element) => {
            const text = label(element);
            if (text === '' || text.length > 40 || !ACCEPT_TEXT.test(text)) {
                return;
            }
            if (hasConsentContext(element) && overlayRoot(element)) {
                press(element);
            }
        });
    }

    function closeDialogs(scope) {
        queryAll('button, a, [role="button"], [aria-label], [class*="close"]', scope).forEach((element) => {
            const text = label(element);
            const className = typeof element.className === 'string' ? element.className : '';
            const looksClose = (text !== '' && text.length <= 20 && CLOSE_LABEL.test(text)) || /(^|[\s_-])close([\s_-]|$)/i.test(className);
            if (!looksClose) {
                return;
            }
            const root = overlayRoot(element);
            if (root && covers(root, 0.2)) {
                press(element);
            }
        });
    }

    function covers(element, ratio) {
        const rect = element.getBoundingClientRect();
        const area = Math.max(0, Math.min(rect.right, innerWidth) - Math.max(rect.left, 0))
            * Math.max(0, Math.min(rect.bottom, innerHeight) - Math.max(rect.top, 0));
        return area >= innerWidth * innerHeight * ratio;
    }

    function hide(element) {
        element.dataset.mockupHidden = '1';
        element.style.setProperty('display', 'none', 'important');
    }

    function hideLeftovers(scope) {
        scope.flatMap((root) => [...root.querySelectorAll(root instanceof ShadowRoot ? '*' : 'body *')]).forEach((element) => {
            if (!(element instanceof HTMLElement) || element.dataset.mockupHidden) {
                return;
            }
            if (element.tagName.includes('-') && CONSENT_TAG.test(element.tagName)) {
                hide(element);
                return;
            }
            const style = getComputedStyle(element);
            if (style.position !== 'fixed' || !visible(element)) {
                return;
            }
            const rect = element.getBoundingClientRect();
            const naming = `${element.id} ${typeof element.className === 'string' ? element.className : ''}`;
            const isAnchoredAd = rect.width >= innerWidth * 0.6
                && rect.height < innerHeight * 0.35
                && (rect.bottom >= innerHeight - 2 || rect.top <= 1)
                && (element.querySelector('iframe, ins.adsbygoogle') || AD_NAMING.test(naming));
            if (isAnchoredAd) {
                hide(element);
                return;
            }
            const isHeader = rect.top <= 1 && rect.height < innerHeight * 0.25 && rect.width >= innerWidth * 0.9;
            if (isHeader) {
                return;
            }
            const text = (element.innerText || '').slice(0, 3000);
            const isConsent = CONSENT_CONTEXT.test(text) || CONSENT_CONTEXT.test(element.id + ' ' + (typeof element.className === 'string' ? element.className : ''));
            const isModal = element.getAttribute('aria-modal') === 'true' || element.getAttribute('role') === 'dialog';
            const isBackdrop = covers(element, 0.6) && Number(style.zIndex) >= 10;
            if (isConsent || isModal || isBackdrop) {
                hide(element);
            }
        });
        queryAll('dialog[open]', scope).forEach((dialog) => dialog.close());
    }

    function unlockScroll() {
        [document.documentElement, document.body].forEach((element) => {
            if (!element) {
                return;
            }
            const style = getComputedStyle(element);
            if (style.overflow === 'hidden' || style.overflowY === 'hidden') {
                element.style.setProperty('overflow', 'visible', 'important');
            }
            if (style.position === 'fixed') {
                element.style.setProperty('position', 'static', 'important');
            }
        });
        document.body?.classList.remove('modal-open', 'no-scroll', 'noscroll', 'overflow-hidden', 'disable-scroll');
    }

    function injectStyle() {
        if (document.getElementById('mockup-capture-cleaner')) {
            return;
        }
        const style = document.createElement('style');
        style.id = 'mockup-capture-cleaner';
        style.textContent = `${HIDDEN_SELECTORS.join(',')}{display:none!important}`;
        (document.head || document.documentElement).append(style);
    }

    function sweep() {
        if (!document.documentElement) {
            return;
        }
        injectStyle();
        if (!document.body) {
            return;
        }
        const scope = roots();
        acceptConsent(scope);
        closeDialogs(scope);
        if (window.top === window) {
            hideLeftovers(scope);
            unlockScroll();
        }
    }

    window.__mockupSweep = sweep;

    const timer = setInterval(() => {
        sweep();
        if (Date.now() - started > 15000) {
            clearInterval(timer);
        }
    }, 300);

    document.addEventListener('DOMContentLoaded', sweep);
    window.addEventListener('load', () => {
        sweep();
        setTimeout(sweep, 800);
        setTimeout(sweep, 2500);
    });
})();
