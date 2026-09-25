/**
 * Dracofy Shield - Layer 2: Client-Side Stealth Fingerprint & Bundle Isolation
 * Weighted Score threshold >= 40
 * (Corrigido para evitar falsos positivos de leads reais em mobile/in-app)
 */

(function () {
  'use strict';

  // 1. Client-Side Stealth Fingerprint Check
  function detectAutomation() {
    let score = 0;

    // Flag explícita do navegador automatizado
    if (navigator.webdriver) score += 40;

    // Tentativa de mascarar navigator.webdriver
    try {
      const d =
        Object.getOwnPropertyDescriptor(Navigator.prototype, 'webdriver') ??
        Object.getOwnPropertyDescriptor(navigator, 'webdriver');
      if (d && d.get && !Function.prototype.toString.call(d.get).includes('[native code]')) {
        score += 45;
      }
    } catch (e) {}

    // Anomalia de janela zerada (típica de headless em servidores)
    if (window.outerWidth === 0 && window.outerHeight === 0) score += 30;

    // Falta de contexto WebGL
    try {
      const c = document.createElement('canvas');
      if (!(c.getContext('webgl') || c.getContext('experimental-webgl'))) score += 15;
    } catch (e) {
      score += 15;
    }

    // Ausência total de plugins ou idiomas
    if (navigator.plugins && navigator.plugins.length === 0) score += 10;
    if (!navigator.languages || navigator.languages.length === 0) score += 15;

    // Navegadores in-app não possuem window.chrome mas são humanos legítimos
    const inAppBrowserTokens = ['musical_ly', 'trill_', 'Instagram', 'FBAN', 'FBAV', 'Snapchat', 'Kwai'];
    const isKnownInAppBrowser = inAppBrowserTokens.some(t => navigator.userAgent.includes(t));
    if (!isKnownInAppBrowser && /Chrome\//.test(navigator.userAgent) && !window.chrome) score += 20;

    // Ausência de núcleos de processador
    if (!navigator.hardwareConcurrency) score += 15;

    // Entropia de renderização 2D Canvas
    try {
      const c = document.createElement('canvas');
      c.width = 200;
      c.height = 40;
      const ctx2d = c.getContext('2d');
      if (ctx2d) {
        ctx2d.textBaseline = 'top';
        ctx2d.font = '14px Arial';
        ctx2d.fillStyle = '#f60';
        ctx2d.fillRect(0, 0, 200, 40);
        ctx2d.fillStyle = '#069';
        ctx2d.fillText('fp-check', 2, 12);
        const data = ctx2d.getImageData(0, 0, 200, 40).data;
        const colors = new Set();
        for (let i = 0; i < data.length; i += 4) {
          colors.add(data[i] + ',' + data[i + 1] + ',' + data[i + 2]);
        }
        if (colors.size < 10) score += 5;
      }
    } catch (e) {
      score += 5;
    }

    // Spoofing de User-Agent Mobile
    const uaClaimsMobile = /Mobile|Android|iPhone/.test(navigator.userAgent);
    if (uaClaimsMobile && navigator.maxTouchPoints === 0) score += 20;
    if (uaClaimsMobile && screen.width > 1400) score += 20;

    return score >= 40;
  }

  window.detectAutomation = detectAutomation;

  // Função central de revelação da página (Humano legítimo)
  function revealPage() {
    const shieldStyle = document.getElementById('stealth-shield-hide');
    if (shieldStyle && shieldStyle.parentNode) {
      shieldStyle.parentNode.removeChild(shieldStyle);
    }
    document.documentElement.style.opacity = '1';
    document.documentElement.style.visibility = 'visible';
  }

  // 2. Zero-Flicker Execution & Bundle Isolation
  const isBot = detectAutomation();

  if (isBot) {
    // BOT DETECTADO: Busca dinâmica isolada da Safe Page
    fetch('/safe.html', { cache: 'no-store' })
      .then(function (res) {
        return res.text();
      })
      .then(function (html) {
        document.open();
        document.write(html);
        document.close();
      })
      .catch(function () {
        window.location.replace('/safe.html');
      });
  } else {
    // HUMANO REAL: Libera a renderização da página
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', revealPage);
    } else {
      revealPage();
    }
  }

  // PLANO B: Fallback de contingência (revela após 1.2s se houver qualquer travamento)
  setTimeout(revealPage, 1200);
})();
