// Dracofy Shield - camada 1 (v1.0.5). Gerado por `npx @dracofy/shield init`.
// Decide NO SERVIDOR, antes da pagina ser entregue: "allow" mostra a pagina real,
// qualquer outra coisa mostra a Safe Page (/white.html) na mesma URL.
//
// Configuracao (variaveis de ambiente do host, NUNCA no codigo):
//   DRACOFY_SHIELD_TOKEN   token do Shield
//   DRACOFY_PROJECT_ID     ID da campanha
//   DRACOFY_SESSION_MINUTES (opcional, padrao 240) quanto tempo o lead aprovado
//                           continua liberado sem precisar do parametro na URL

const SHIELD_TOKEN = process.env.DRACOFY_SHIELD_TOKEN || process.env.SHIELD_TOKEN || '';
const PROJECT_ID = process.env.DRACOFY_PROJECT_ID || process.env.SHIELD_PROJECT_ID || '';
const SESSION_MINUTES = Number(process.env.DRACOFY_SESSION_MINUTES) > 0 ? Number(process.env.DRACOFY_SESSION_MINUTES) : 240;
const SESSION_SECONDS = SESSION_MINUTES * 60;
const VERSION = '1.0.5';
const COOKIE_NAME = 'dracofy_shield_verified';
const API_URL = 'https://api.dracofy.com.br/api/shield/inspect';

export const config = {
  // Fora da checagem: arquivos do proprio framework, estaticos e a Safe Page.
  // index.html NAO fica de fora: o acesso direto a /index.html tambem e inspecionado.
  matcher: ['/((?!_next|_vercel|assets|static|images|fonts|api|safe-site|favicon|white\\.html).*)'],
};

const STATIC_EXT = /\.(css|js|mjs|map|png|jpe?g|webp|gif|svg|ico|avif|woff2?|ttf|eot|otf|json|txt|xml|pdf|mp4|webm|mp3|zip|wasm)$/i;

// Parametros de clique, na ordem das plataformas. A API so le clickToken/tokenType
// como campos separados: nunca extrai nada de dentro de visitorInfo.url.
const TOKEN_PARAMS = ['ttclid', 'fbclid', 'gclid', 'gbraid', 'wbraid', 'click_id'];

function extractClickToken(searchParams) {
  for (const tokenType of TOKEN_PARAMS) {
    const clickToken = searchParams.get(tokenType);
    if (clickToken) return { clickToken, tokenType };
  }
  return {};
}

// IP do visitante: decide pais, datacenter e proxy, entao NAO pode ser forjavel.
// Na Vercel (testado em producao) os cabecalhos x-vercel-forwarded-for, x-real-ip e
// x-forwarded-for sao SOBRESCRITOS com o IP real da conexao, mas cf-connecting-ip e
// true-client-ip passam do jeito que o cliente mandou: qualquer um escolheria o
// proprio IP. Por isso so os primeiros sao usados.
// Se o site esta atras do Cloudflare (e o dominio .vercel.app nao e acessivel
// direto), defina DRACOFY_TRUST_CLOUDFLARE=1 para usar cf-connecting-ip.
const TRUST_CLOUDFLARE = process.env.DRACOFY_TRUST_CLOUDFLARE === '1';

function getClientIp(headers) {
  const first = (v) => (v ? v.split(',')[0].trim() : '');
  return (
    (TRUST_CLOUDFLARE ? headers.get('cf-connecting-ip') : '') ||
    first(headers.get('x-vercel-forwarded-for')) ||
    headers.get('x-real-ip') ||
    first(headers.get('x-forwarded-for')) ||
    '127.0.0.1'
  );
}

const FALLBACK_HTML =
  '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Inicio</title></head>' +
  '<body><main><h1>Bem-vindo</h1></main></body></html>';

async function hmacHex(message) {
  const enc = new TextEncoder();
  const key = await crypto.subtle.importKey('raw', enc.encode(SHIELD_TOKEN), { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']);
  const sig = await crypto.subtle.sign('HMAC', key, enc.encode(message));
  return [...new Uint8Array(sig)].map(b => b.toString(16).padStart(2, '0')).join('');
}

function safeEqual(a, b) {
  if (typeof a !== 'string' || typeof b !== 'string' || a.length !== b.length) return false;
  let diff = 0;
  for (let i = 0; i < a.length; i++) diff |= a.charCodeAt(i) ^ b.charCodeAt(i);
  return diff === 0;
}

function readCookie(header, name) {
  if (!header) return null;
  for (const part of header.split(';')) {
    const [k, ...rest] = part.trim().split('=');
    if (k === name) {
      try { return decodeURIComponent(rest.join('=')); } catch (e) { return null; }
    }
  }
  return null;
}

// Assinatura com prefixo por finalidade: um valor de cookie nunca serve de
// cabecalho interno nem de sonda do doctor (e vice-versa).
async function signedValue(purpose, ttlSeconds) {
  const exp = Math.floor(Date.now() / 1000) + ttlSeconds;
  return exp + '.' + (await hmacHex(purpose + ':' + exp));
}

async function checkSigned(purpose, value, toleranceSeconds) {
  if (!value) return false;
  const [expStr, sig] = String(value).split('.');
  const exp = Number(expStr);
  if (!exp || !sig) return false;
  if (toleranceSeconds === undefined) {
    if (exp < Math.floor(Date.now() / 1000)) return false;
  } else if (Math.abs(exp - Math.floor(Date.now() / 1000)) > toleranceSeconds) {
    return false;
  }
  return safeEqual(await hmacHex(purpose + ':' + expStr), sig);
}

function sessionCookie(value, url) {
  const secure = url.protocol === 'https:' ? '; Secure' : '';
  return COOKIE_NAME + '=' + value + '; Path=/; Max-Age=' + SESSION_SECONDS + '; HttpOnly; SameSite=Lax' + secure;
}

function diag(code, body) {
  return new Response(JSON.stringify(body), {
    status: 200,
    headers: { 'content-type': 'application/json', 'cache-control': 'no-store', 'x-dracofy-diag': code },
  });
}

// Sonda do `npx @dracofy/shield doctor`. Nao chama a API do Shield (nao gera log
// nem risco de banimento). So responde "ok" a quem conhece o token; sem as
// variaveis de ambiente responde "missing-env" para o doctor apontar o problema.
async function answerProbe(value) {
  if (!SHIELD_TOKEN || !PROJECT_ID) {
    return diag('missing-env', { ok: false, hasToken: !!SHIELD_TOKEN, hasProjectId: !!PROJECT_ID });
  }
  if (!(await checkSigned('doctor', value, 600))) return null;
  return diag('ok', { ok: true, version: VERSION, projectId: PROJECT_ID.slice(0, 8), sessionMinutes: SESSION_MINUTES });
}

async function safePage(url) {
  try {
    // /white.html fica fora do matcher, entao o cabecalho interno nem e necessario;
    // sem token (variaveis nao configuradas) nao ha como assinar, e tudo bem.
    const headers = SHIELD_TOKEN ? { 'x-dracofy-internal': await signedValue('internal', 60) } : {};
    const res = await fetch(new URL('/white.html', url), { headers });
    if (res.ok) {
      return new Response(await res.text(), {
        status: 200,
        headers: { 'content-type': 'text/html; charset=utf-8', 'cache-control': 'private, no-store' },
      });
    }
  } catch (e) {}
  return new Response(FALLBACK_HTML, {
    status: 200,
    headers: { 'content-type': 'text/html; charset=utf-8', 'cache-control': 'private, no-store' },
  });
}

// "allow": busca a MESMA pagina (com a mesma query) por uma requisicao interna
// marcada com o cabecalho assinado (o middleware deixa essa passar) e devolve
// junto o cookie de sessao. Funciona em qualquer rota: home, /checkout, /produto...
async function allowWithSession(request, url) {
  try {
    const headers = new Headers({ 'x-dracofy-internal': await signedValue('internal', 60) });
    for (const name of ['user-agent', 'accept', 'accept-language']) {
      const v = request.headers.get(name);
      if (v) headers.set(name, v);
    }
    const upstream = await fetch(url.href, { method: request.method, headers, redirect: 'manual' });

    const out = new Headers();
    for (const name of ['content-type', 'location', 'content-language', 'x-robots-tag']) {
      const v = upstream.headers.get(name);
      if (v) out.set(name, v);
    }
    out.set('cache-control', 'private, no-store');
    if (typeof upstream.headers.getSetCookie === 'function') {
      for (const c of upstream.headers.getSetCookie()) out.append('set-cookie', c);
    }
    out.append('set-cookie', sessionCookie(await signedValue('session', SESSION_SECONDS), url));

    const noBody = request.method === 'HEAD' || [101, 204, 205, 304].includes(upstream.status);
    return new Response(noBody ? null : await upstream.arrayBuffer(), { status: upstream.status, headers: out });
  } catch (e) {
    // Shield ja liberou: se so o cookie falhou, deixa o visitante seguir normalmente.
    return undefined;
  }
}

export default async function middleware(request) {
  const url = new URL(request.url);

  const probe = request.headers.get('x-dracofy-doctor');
  if (probe) {
    const answer = await answerProbe(probe);
    if (answer) return answer;
  }

  // Requisicao interna do proprio middleware (cabecalho assinado): deixa passar.
  const internal = request.headers.get('x-dracofy-internal');
  if (internal && SHIELD_TOKEN && (await checkSigned('internal', internal))) return undefined;

  if (STATIC_EXT.test(url.pathname)) return undefined;
  if (request.method !== 'GET' && request.method !== 'HEAD') return undefined;

  if (!SHIELD_TOKEN || !PROJECT_ID) {
    console.error('[Dracofy Shield] DRACOFY_SHIELD_TOKEN / DRACOFY_PROJECT_ID nao configurados: todo o trafego esta vendo a Safe Page. Configure no host e faca redeploy.');
    return safePage(url);
  }

  // Lead ja aprovado: nao depende do parametro de clique continuar na URL.
  if (await checkSigned('session', readCookie(request.headers.get('cookie'), COOKIE_NAME))) return undefined;

  const { clickToken, tokenType } = extractClickToken(url.searchParams);
  const payload = {
    projectId: PROJECT_ID,
    visitorInfo: { url: request.url, userAgent: request.headers.get('user-agent') || '', ip: getClientIp(request.headers) },
    ...(clickToken ? { clickToken, tokenType } : {}),
  };

  // Fail-closed: so "allow" libera. Timeout, erro de rede, HTTP != 200, 429,
  // JSON invalido ou qualquer outra resposta => Safe Page.
  let action = 'block';
  try {
    const res = await fetch(API_URL, {
      method: 'POST',
      headers: { 'x-shield-token': SHIELD_TOKEN, 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      signal: AbortSignal.timeout(4000),
    });
    if (res.ok) {
      const data = await res.json();
      if (data && data.action === 'allow') action = 'allow';
    }
  } catch (e) {}

  if (action !== 'allow') return safePage(url);
  return allowWithSession(request, url);
}
