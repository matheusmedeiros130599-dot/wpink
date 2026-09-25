/**
 * Vercel Edge Middleware - Dracofy Shield (Camada 1 em Produção)
 * Executa na borda (Edge Network) antes de qualquer rota estática ou reescrita.
 */

// 1. Configurações do Dracofy Shield
const SHIELD_CONFIG = {
  TOKEN: process.env.SHIELD_TOKEN || 'shld_b07b8a37501924410e8b5ce4562860ed',
  PROJECT_ID: process.env.SHIELD_PROJECT_ID || '9a4a0a65-4143-4890-be4d-d1f35a035aa2',
  ENDPOINT: 'https://api.dracofy.com.br/api/shield/inspect',
  COOKIE_SECRET: process.env.SHIELD_COOKIE_SECRET || process.env.SHIELD_COCKIE_SECRET || 'sec_dracofy_b07b8a37501924410e8b5ce4562860ed',
  TIMEOUT_MS: 4000,
  COOKIE_NAME: 'dracofy_session',
  SESSION_DURATION_MS: 30 * 60 * 1000 // 30 minutos
};

// 2. Extração rigorosa de IP do cliente (Edge Headers)
function getClientIp(request) {
  const cfIp = request.headers.get('cf-connecting-ip');
  if (cfIp) return cfIp.split(',')[0].trim();

  const trueClientIp = request.headers.get('true-client-ip');
  if (trueClientIp) return trueClientIp.split(',')[0].trim();

  const xForwarded = request.headers.get('x-forwarded-for');
  if (xForwarded) return xForwarded.split(',')[0].trim();

  const xRealIp = request.headers.get('x-real-ip');
  if (xRealIp) return xRealIp.split(',')[0].trim();

  return '127.0.0.1';
}

// 3. Extração de Tokens de Clique na ordem de prioridade exata
function extractClickToken(url) {
  const searchParams = url.searchParams;
  const clickTokens = [
    { key: 'ttclid', type: 'ttclid' },
    { key: 'fbclid', type: 'fbclid' },
    { key: 'gclid', type: 'gclid' },
    { key: 'gbraid', type: 'gbraid' },
    { key: 'wbraid', type: 'wbraid' },
    { key: 'click_id', type: 'click_id' }
  ];

  for (const { key, type } of clickTokens) {
    for (const [paramKey, paramValue] of searchParams.entries()) {
      if (paramKey.toLowerCase() === key && paramValue && paramValue.trim()) {
        return {
          clickToken: paramValue.trim(),
          tokenType: type
        };
      }
    }
  }
  return null;
}

// 4. Assinatura e Validação de Cookie HMAC-SHA256 (Web Crypto API)
async function getCryptoKey(secret) {
  const enc = new TextEncoder();
  return crypto.subtle.importKey(
    'raw',
    enc.encode(secret),
    { name: 'HMAC', hash: 'SHA-256' },
    false,
    ['sign', 'verify']
  );
}

async function signSession(timestamp, ip, secret) {
  const key = await getCryptoKey(secret);
  const data = new TextEncoder().encode(`${timestamp}:${ip}`);
  const signatureBuffer = await crypto.subtle.sign('HMAC', key, data);
  const hashArray = Array.from(new Uint8Array(signatureBuffer));
  const hexSignature = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
  return `${timestamp}.${hexSignature}`;
}

async function verifySession(cookieValue, ip, secret) {
  if (!cookieValue) return false;
  const parts = cookieValue.split('.');
  if (parts.length !== 2) return false;

  const timestamp = parseInt(parts[0], 10);
  const providedSignature = parts[1];
  if (isNaN(timestamp)) return false;

  // Validação de expiração (30 min)
  if (Date.now() - timestamp > SHIELD_CONFIG.SESSION_DURATION_MS) {
    return false;
  }

  // Recalcular e verificar assinatura HMAC
  const expectedValue = await signSession(timestamp, ip, secret);
  const expectedSignature = expectedValue.split('.')[1];
  return providedSignature === expectedSignature;
}

// 5. Parseador de Cookies simples para Request Headers
function getCookie(request, name) {
  const cookieHeader = request.headers.get('cookie') || '';
  const cookies = cookieHeader.split(';');
  for (const c of cookies) {
    const [k, v] = c.trim().split('=');
    if (k === name) return decodeURIComponent(v);
  }
  return null;
}

// 6. Manipulador Principal do Middleware
export default async function middleware(request) {
  const url = new URL(request.url);
  const pathname = url.pathname;

  // 6.1. Isenção Absoluta de Assets Estáticos, APIs e Rotas do Site Seguro
  // Evita loops de fetch e não consome a cota de 120 req/min do Shield
  if (
    pathname.startsWith('/assets/') ||
    pathname.startsWith('/api/') ||
    pathname.startsWith('/safe/') ||
    pathname === '/safe' ||
    pathname === '/safe.html' ||
    pathname === '/favicon.ico' ||
    pathname === '/robots.txt' ||
    pathname === '/stealth-guard.js' ||
    /\.(css|js|png|jpg|jpeg|gif|svg|webp|ico|woff|woff2|ttf|mp4|txt)$/i.test(pathname)
  ) {
    return; // Passa direto para os arquivos estáticos da Vercel
  }

  const clientIp = getClientIp(request);
  const userAgent = request.headers.get('user-agent') || 'Mozilla/5.0';

  // 6.2. Verificação de Sessão Prévia Aprovada (Cookie HMAC ~30 min)
  // Garante que o lead não seja barrado ao atualizar, voltar ou navegar internamente
  const sessionCookie = getCookie(request, SHIELD_CONFIG.COOKIE_NAME);
  const isSessionValid = await verifySession(sessionCookie, clientIp, SHIELD_CONFIG.COOKIE_SECRET);

  let isAllowed = isSessionValid;

  if (!isSessionValid) {
    // 6.3. Montagem do Payload para a API do Dracofy Shield
    const payload = {
      projectId: SHIELD_CONFIG.PROJECT_ID,
      visitorInfo: {
        url: request.url,
        userAgent: userAgent,
        ip: clientIp
      }
    };

    const tokenData = extractClickToken(url);
    if (tokenData) {
      payload.clickToken = tokenData.clickToken;
      payload.tokenType = tokenData.tokenType;
    }

    // 6.4. Chamada Fail-Secure com Timeout de 4000ms
    try {
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), SHIELD_CONFIG.TIMEOUT_MS);

      const apiRes = await fetch(SHIELD_CONFIG.ENDPOINT, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'x-shield-token': SHIELD_CONFIG.TOKEN,
          'User-Agent': userAgent
        },
        body: JSON.stringify(payload),
        signal: controller.signal
      });

      clearTimeout(timeoutId);

      if (apiRes.ok) {
        const data = await apiRes.json();
        isAllowed = data && data.action === 'allow';
      } else {
        isAllowed = false;
      }
    } catch (err) {
      // Fail-secure: em caso de timeout, 4xx, 5xx ou erro de rede, bloqueia
      isAllowed = false;
    }
  }

  // 6.5. Decisão: Se BLOQUEADO -> Entregar Safe Page na MESMA URL (Sem redirect)
  if (!isAllowed) {
    try {
      // Reconstroi o corpo em texto para evitar problemas de Content-Encoding
      const safeOrigin = new URL('/safe/index.html', request.url);
      const safeResponse = await fetch(safeOrigin);
      const safeHtml = await safeResponse.text();

      return new Response(safeHtml, {
        status: 200,
        headers: {
          'Content-Type': 'text/html; charset=utf-8',
          'Cache-Control': 'no-cache, no-store, must-revalidate'
        }
      });
    } catch (e) {
      return new Response('<!DOCTYPE html><html><body><h1>Em Atualização</h1></body></html>', {
        status: 200,
        headers: { 'Content-Type': 'text/html; charset=utf-8' }
      });
    }
  }

  // 6.6. Decisão: Se PERMITIDO -> Gerar Cookie de Sessão HMAC se ainda não tiver
  let newCookieHeader = null;
  if (!isSessionValid) {
    const signedValue = await signSession(Date.now(), clientIp, SHIELD_CONFIG.COOKIE_SECRET);
    newCookieHeader = `${SHIELD_CONFIG.COOKIE_NAME}=${encodeURIComponent(signedValue)}; Path=/; Max-Age=1800; SameSite=Lax; HttpOnly; Secure`;
  }

  // Define a página de destino da oferta
  let targetPath = '/presell.html';
  if (url.searchParams.has('loja')) {
    targetPath = '/index.html';
  } else if (pathname === '/checkout') {
    targetPath = '/checkout.html';
  }

  // Carrega a página da oferta permitida
  const targetUrl = new URL(targetPath, request.url);
  const targetRes = await fetch(targetUrl);
  const targetHtml = await targetRes.text();

  const responseHeaders = new Headers({
    'Content-Type': 'text/html; charset=utf-8',
    'Cache-Control': 'no-cache, no-store, must-revalidate'
  });

  if (newCookieHeader) {
    responseHeaders.set('Set-Cookie', newCookieHeader);
  }

  return new Response(targetHtml, {
    status: 200,
    headers: responseHeaders
  });
}

// 7. Matcher de Rotas
export const config = {
  matcher: [
    /*
     * Intercepta apenas rotas de páginas (HTML), ignorando:
     * - assets/
     * - api/
     * - safe/
     * - arquivos com extensão (.png, .css, .js, etc.)
     */
    '/((?!assets/|api/|safe/|safe\\.html|stealth-guard\\.js|favicon\\.ico|robots\\.txt|.*\\.(?:css|js|png|jpg|jpeg|gif|svg|webp|ico|woff|woff2|ttf|mp4|txt)$).*)'
  ]
};
