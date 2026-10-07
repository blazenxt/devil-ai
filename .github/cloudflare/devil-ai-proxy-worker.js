const ORIGIN = 'https://blazepanel.mywp.info';
const BACKEND_ORIGIN = 'https://rdp.mywp.info';
const APP_BASE = '/devil-ai';
const PRIMARY_HOST = 'ai.devil.blazenxt.com';
const HOST_REDIRECTS = {
  'ai.blazenxt.in': 'ai.devil.blazenxt.in',
  'ai.blazenxt.com': 'ai.devil.blazenxt.com'
};
const API_HOST = 'api.devil.blazenxt.in';
const API_DOC_PATHS = new Set(['/', '/docs', '/docs/', '/developers_docs.php']);
const LOGO_DATA_URI = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxMjggMTI4IiB3aWR0aD0iMTI4IiBoZWlnaHQ9IjEyOCIgcm9sZT0iaW1nIiBhcmlhLWxhYmVsPSJEZXZpbCBBSSBsb2dvIj4KICA8ZGVmcz4KICAgIDxyYWRpYWxHcmFkaWVudCBpZD0iZmFjZUdyYWQiIGN4PSIzOCUiIGN5PSIzMCUiIHI9IjgwJSI+CiAgICAgIDxzdG9wIG9mZnNldD0iMCUiIHN0b3AtY29sb3I9IiNmZjdhNGQiLz4KICAgICAgPHN0b3Agb2Zmc2V0PSI0NSUiIHN0b3AtY29sb3I9IiNlNTM4M2IiLz4KICAgICAgPHN0b3Agb2Zmc2V0PSIxMDAlIiBzdG9wLWNvbG9yPSIjNWMwYTBlIi8+CiAgICA8L3JhZGlhbEdyYWRpZW50PgogICAgPHJhZGlhbEdyYWRpZW50IGlkPSJiZ0dsb3ciIGN4PSI1MCUiIGN5PSI0MiUiIHI9IjY1JSI+CiAgICAgIDxzdG9wIG9mZnNldD0iMCUiIHN0b3AtY29sb3I9IiNlMTFkNDgiIHN0b3Atb3BhY2l0eT0iMC4zNSIvPgogICAgICA8c3RvcCBvZmZzZXQ9IjEwMCUiIHN0b3AtY29sb3I9IiNlMTFkNDgiIHN0b3Atb3BhY2l0eT0iMCIvPgogICAgPC9yYWRpYWxHcmFkaWVudD4KICAgIDxsaW5lYXJHcmFkaWVudCBpZD0iaG9ybkdyYWQiIHgxPSIwIiB5MT0iMSIgeDI9IjAiIHkyPSIwIj4KICAgICAgPHN0b3Agb2Zmc2V0PSIwJSIgc3RvcC1jb2xvcj0iIzlmMTIzOSIvPgogICAgICA8c3RvcCBvZmZzZXQ9IjEwMCUiIHN0b3AtY29sb3I9IiNmZjVhNWYiLz4KICAgIDwvbGluZWFyR3JhZGllbnQ+CiAgICA8ZmlsdGVyIGlkPSJzb2Z0R2xvdyIgeD0iLTUwJSIgeT0iLTUwJSIgd2lkdGg9IjIwMCUiIGhlaWdodD0iMjAwJSI+CiAgICAgIDxmZUdhdXNzaWFuQmx1ciBpbj0iU291cmNlR3JhcGhpYyIgc3RkRGV2aWF0aW9uPSIxLjQiIHJlc3VsdD0iYmx1ciIvPgogICAgICA8ZmVNZXJnZT4KICAgICAgICA8ZmVNZXJnZU5vZGUgaW49ImJsdXIiLz4KICAgICAgICA8ZmVNZXJnZU5vZGUgaW49IlNvdXJjZUdyYXBoaWMiLz4KICAgICAgPC9mZU1lcmdlPgogICAgPC9maWx0ZXI+CiAgPC9kZWZzPgoKICA8IS0tIGFtYmllbnQgZ2xvdyAtLT4KICA8Y2lyY2xlIGN4PSI2NCIgY3k9IjU4IiByPSI1OCIgZmlsbD0idXJsKCNiZ0dsb3cpIi8+CgogIDwhLS0gaG9ybnMgLS0+CiAgPHBhdGggZD0iTTMwIDQ0IEMyMiAyNiAyNSAxMiAzNCA0IEMzOCAyMCA0NiAzMCA1NCAzNiBaIiBmaWxsPSJ1cmwoI2hvcm5HcmFkKSIvPgogIDxwYXRoIGQ9Ik05OCA0NCBDMTA2IDI2IDEwMyAxMiA5NCA0IEM5MCAyMCA4MiAzMCA3NCAzNiBaIiBmaWxsPSJ1cmwoI2hvcm5HcmFkKSIvPgoKICA8IS0tIGZhY2UgLS0+CiAgPGNpcmNsZSBjeD0iNjQiIGN5PSI3MCIgcj0iNDIiIGZpbGw9InVybCgjZmFjZUdyYWQpIi8+CiAgPHBhdGggZD0iTTY0IDI4IGE0MiA0MiAwIDAgMSAwIDg0IGE1OCA1OCAwIDAgMCAwIC04NCIgZmlsbD0iIzAwMDAwMCIgb3BhY2l0eT0iMC4xMiIvPgoKICA8IS0tIGdsb3dpbmcgZXllcyAtLT4KICA8ZyBmaWxsPSIjZmZlMDY2IiBmaWx0ZXI9InVybCgjc29mdEdsb3cpIj4KICAgIDxwYXRoIGQ9Ik0zOCA2MiBMNTYgNjguNSBMMzggNzUgWiIvPgogICAgPHBhdGggZD0iTTkwIDYyIEw3MiA2OC41IEw5MCA3NSBaIi8+CiAgPC9nPgoKICA8IS0tIHdpY2tlZCBzbWlsZSAtLT4KICA8cGF0aCBkPSJNNDQgODggUTY0IDEwMiA4NCA4OCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSIjMzMwNjBhIiBzdHJva2Utd2lkdGg9IjUiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIvPgoKICA8IS0tIGZhbmdzIC0tPgogIDxwYXRoIGQ9Ik01MyA5MSBMNTggOTEuNiBMNTUuNSA5OSBaIiBmaWxsPSIjZmZmNWY1Ii8+CiAgPHBhdGggZD0iTTc1IDkxIEw3MCA5MS42IEw3Mi41IDk5IFoiIGZpbGw9IiNmZmY1ZjUiLz4KCiAgPCEtLSBmbG9hdGluZyBlbWJlcnMgLS0+CiAgPGNpcmNsZSBjeD0iMjQiIGN5PSIxOCIgcj0iMi40IiBmaWxsPSIjZmI3MTg1IiBvcGFjaXR5PSIwLjkiLz4KICA8Y2lyY2xlIGN4PSIxMDQiIGN5PSIyNCIgcj0iMS44IiBmaWxsPSIjZmRhNGFmIiBvcGFjaXR5PSIwLjgiLz4KICA8Y2lyY2xlIGN4PSIxMTIiIGN5PSI2MCIgcj0iMS41IiBmaWxsPSIjZmI3MTg1IiBvcGFjaXR5PSIwLjYiLz4KICA8Y2lyY2xlIGN4PSIxNCIgY3k9IjY2IiByPSIxLjYiIGZpbGw9IiNmZGE0YWYiIG9wYWNpdHk9IjAuNSIvPgogIDxjaXJjbGUgY3g9Ijk4IiBjeT0iMTAiIHI9IjEuMiIgZmlsbD0iI2ZlY2RkMyIgb3BhY2l0eT0iMC43Ii8+Cjwvc3ZnPgo=';

const PROXYCHECK_CACHE_SECONDS = 21600; // 6 hours per IP at Cloudflare edge
const PROXYCHECK_TIMEOUT_MS = 1400;


const BLOCKED_ASNS = new Set([
  13335, 14618, 16509, 8075, 15169, 396982, 14061, 63949, 20473, 53667,
  16276, 24940, 9009, 60068, 62240, 51167, 31898, 398101, 12876, 60781,
  28753, 45102, 132203, 6939, 202053, 47583, 20454, 29802, 29838, 55286,
  29854, 8100, 35916, 40676, 46606, 36352, 53667, 55293, 32244, 399629
]);

const BLOCKED_ORG_RE = /(?:\bvpn\b|proxy|tor\b|anonymous|anonymizer|privacy|tunnel|mullvad|nordvpn|expressvpn|surfshark|proton\s*(?:vpn)?|windscribe|private\s*internet\s*access|cyberghost|torguard|hidemyass|hide\s*my|purevpn|ivpn|airvpn|vyprvpn|hotspot\s*shield|ipvanish|warp|cloudflare\s*warp|datacenter|data\s*center|colo(?:cation)?|hosting|hoster|\bvps\b|dedicated\s*server|amazon|aws|google\s*cloud|microsoft\s*azure|digitalocean|akamai\s*linode|linode|vultr|ovh|hetzner|contabo|leaseweb|scaleway|oracle\s*cloud|alibaba|tencent|choopa|m247|datacamp|cdn77|hivelocity|psychz|shinjiru|quadra|frantech|racknerd|hostwinds|upcloud|clouvider|packet\s*exchange|servermania|ionos|strato|kamatera|netcup|timeweb|worldstream|g-core|gcore|edis|green\s*floid|performive|nocix|wholesale\s*internet|constant|servers\.com|serverion|rapidseedbox|seedbox|vpnsecure|privado|atlas\s*vpn|avast|avg\s*vpn|mozilla\s*vpn|hide\.me|zenmate|urban\s*vpn|hola|tunnelbear|strongvpn|perfect\s*privacy|privatevpn|wevpn|vpn\s*unlimited|keepSolid|opera\s*vpn|psiphon|windscribe|torguard)/i;

const RESIDENTIAL_ORG_RE = /(?:jio|reliance\s*jio|airtel|bharti|vodafone|idea|vi\s*india|bsnl|mtnl|hathway|excitel|act\s*fibernet|a\s*tria|alliance\s*broadband|railwire|railtel|asianet|siti\s*cable|den\s*networks|gtpl|you\s*broadband|tikona|spectra|tata\s*(?:play|teleservices)|broadband|telecom|telco|internet\s*service|cable|fiber|fibre|ftth|wireless|mobile|cellular|communications)/i;

// Only explicit proxy-auth/proxy-control headers are hard-blocked.
// Generic Via/X-Forwarded-* headers are often added by mobile carriers and should not trap real users.
const PROXY_HEADER_NAMES = [
  'x-proxy-id', 'x-proxy-authorization', 'proxy-authorization', 'proxy-connection'
];

function hasDeveloperApiKey(request, url) {
  const auth = request.headers.get('authorization') || '';
  if (/^Bearer\s+(?:devil_blazenxt_|dv_live_)/i.test(auth)) return true;
  if (request.headers.get('x-devil-api-key')) return true;
  return ['key', 'api_key', 'apikey'].some((k) => url.searchParams.has(k));
}

function isDeveloperApiRequest(url) {
  return url.pathname === '/v1' || url.pathname.startsWith('/v1/');
}

function proxyHeaderPresent(request) {
  for (const name of PROXY_HEADER_NAMES) {
    if (request.headers.get(name)) return name;
  }
  return '';
}

function clientIp(request) {
  const cfIp = (request.headers.get('CF-Connecting-IP') || '').trim();
  if (cfIp) return cfIp;
  const xff = request.headers.get('x-forwarded-for') || '';
  return xff.split(',')[0].trim();
}

function isPublicIp(ip) {
  if (!ip) return false;
  if (ip.includes(':')) return true; // Cloudflare only sends public IPv6 to Workers here.
  const p = ip.split('.').map((x) => Number(x));
  if (p.length !== 4 || p.some((x) => !Number.isInteger(x) || x < 0 || x > 255)) return false;
  if (p[0] === 10 || p[0] === 127 || p[0] === 0) return false;
  if (p[0] === 172 && p[1] >= 16 && p[1] <= 31) return false;
  if (p[0] === 192 && p[1] === 168) return false;
  if (p[0] === 169 && p[1] === 254) return false;
  return true;
}

function proxycheckDecision(data, ip) {
  const item = data && (data[ip] || data.query || data.result || data.ip);
  if (!item || typeof item !== 'object') return '';
  const proxy = String(item.proxy || item.vpn || item.tor || '').toLowerCase();
  const type = String(item.type || item.category || '').toLowerCase();
  const provider = String(item.provider || item.organisation || item.organization || '').toLowerCase();
  const risk = Number(item.risk || 0);
  if (proxy === 'yes' || proxy === 'true' || proxy === '1') return `proxycheck ${type || 'proxy'}`;
  if (/(vpn|proxy|tor|relay|hosting|server|business)/i.test(type) && risk >= 50) return `proxycheck ${type}`;
  if (risk >= 75 && BLOCKED_ORG_RE.test(provider)) return 'proxycheck high risk provider';
  return '';
}

async function proxycheckReason(request) {
  const ip = clientIp(request);
  if (!isPublicIp(ip)) return '';
  const cacheKey = new Request('https://devil-ai.local/proxycheck/' + encodeURIComponent(ip));
  try {
    const cached = await caches.default.match(cacheKey);
    if (cached) return proxycheckDecision(await cached.json(), ip);
  } catch (e) {}

  const api = 'https://proxycheck.io/v2/' + encodeURIComponent(ip) + '?vpn=1&asn=1&risk=1&port=1&seen=1&node=1&time=1&tag=devil-ai';
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort('timeout'), PROXYCHECK_TIMEOUT_MS);
  try {
    const res = await fetch(api, {
      headers: { 'Accept': 'application/json', 'User-Agent': 'DevilAI/1.0 (+https://ai.devil.blazenxt.com)' },
      signal: controller.signal,
      cf: { cacheTtl: 0, cacheEverything: false }
    });
    if (!res.ok) return '';
    const data = await res.json();
    try {
      await caches.default.put(cacheKey, new Response(JSON.stringify(data), {
        headers: { 'Content-Type': 'application/json', 'Cache-Control': 'public, max-age=' + PROXYCHECK_CACHE_SECONDS }
      }));
    } catch (e) {}
    return proxycheckDecision(data, ip);
  } catch (e) {
    return '';
  } finally {
    clearTimeout(timer);
  }
}

async function blockReason(request, url) {
  const apiWithKey = isDeveloperApiRequest(url) && hasDeveloperApiKey(request, url);
  if (apiWithKey) return '';

  const header = proxyHeaderPresent(request);
  if (header) return `explicit proxy header: ${header}`;

  const cf = request.cf || {};
  const country = String(cf.country || '').toUpperCase();
  if (country === 'T1') return 'tor/proxy network';

  const org = String(cf.asOrganization || cf.asnOrganization || '');
  const residential = org && RESIDENTIAL_ORG_RE.test(org) && !BLOCKED_ORG_RE.test(org);

  if (!residential) {
    const threat = Number(cf.threatScore || cf.threat_score || 0);
    if (Number.isFinite(threat) && threat >= 80) return 'high risk IP reputation';

    const asn = Number(cf.asn || 0);
    if (BLOCKED_ASNS.has(asn)) return `blocked ASN ${asn}`;

    if (org && BLOCKED_ORG_RE.test(org)) return `blocked network: ${org}`;
  }

  const checked = await proxycheckReason(request);
  if (checked) return checked;

  return '';
}

function securityBlockResponse(request, reason) {
  const accept = request.headers.get('accept') || '';
  const url = new URL(request.url);
  const message = 'Please disconnect VPN or proxy, then refresh Devil AI.';
  const wantsJson = (url.pathname.startsWith('/v1/') || url.pathname.endsWith('/api.php') || (accept.includes('application/json') && !accept.includes('text/html')));
  const headers = {
    'Content-Type': wantsJson ? 'application/json; charset=utf-8' : 'text/html; charset=utf-8',
    'X-Robots-Tag': 'noindex',
    'Cache-Control': 'no-store',
    'X-Devil-AI-Blocked': 'proxy-vpn',
    'X-Devil-AI-Block-Reason': String(reason || 'proxy-vpn').slice(0, 140)
  };
  if (wantsJson) {
    return new Response(JSON.stringify({ ok: false, error: message }), { status: 403, headers });
  }
  const html = `<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Security check — Devil AI</title>
<style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:radial-gradient(900px 420px at 70% -10%,rgba(244,63,94,.20),transparent 62%),#0c0709;color:#efe6ea;font-family:Segoe UI,system-ui,-apple-system,Roboto,sans-serif}.card{width:min(92vw,560px);padding:30px;border:1px solid rgba(244,63,94,.28);border-radius:24px;background:linear-gradient(180deg,rgba(255,255,255,.04),rgba(255,255,255,.015)),#171014;box-shadow:0 28px 80px rgba(0,0,0,.42)}.brand{display:flex;align-items:center;gap:12px;font-weight:900;font-size:1.15rem;margin-bottom:18px}.logo{width:46px;height:46px;border-radius:14px;display:grid;place-items:center;background:rgba(244,63,94,.10);box-shadow:0 0 26px rgba(244,63,94,.32);overflow:hidden}.logo img{width:42px;height:42px;display:block}h1{font-size:1.65rem;line-height:1.1;margin:0 0 10px}p{color:#b99aa5;line-height:1.65;margin:0 0 18px}.steps{border:1px solid rgba(244,63,94,.18);background:#100a0d;border-radius:16px;padding:14px 16px;color:#f5c8d0}.btn{display:inline-flex;margin-top:18px;padding:12px 16px;border-radius:12px;background:linear-gradient(135deg,#f43f5e,#be123c);color:white;text-decoration:none;font-weight:800}</style></head>
<body><main class="card"><div class="brand"><div class="logo"><img src="${LOGO_DATA_URI}" alt="Devil AI logo"></div><span>Devil AI</span></div><h1>Security check</h1><p>${message}</p><div class="steps">Turn off VPN / Proxy / Tor / WARP, then reload this page. Access will work automatically from a normal network.</div><a class="btn" href="${url.pathname + url.search}">Refresh Devil AI</a></main></body></html>`;
  return new Response(html, { status: 403, headers });
}

function mapToOriginPath(pathname, host) {
  if (host === API_HOST) {
    if (API_DOC_PATHS.has(pathname)) return '/developers_docs.php';
    if (pathname === '/widget.js' || pathname === '/support-widget.js') return '/support-widget.js';
    if (pathname === '/openapi.json') return '/openapi.json';
    return pathname;
  }
  if (pathname === APP_BASE || pathname.startsWith(APP_BASE + '/')) return pathname;
  if (pathname === '/') return APP_BASE + '/';
  return APP_BASE + pathname;
}

function publicBase(url) {
  return `${url.protocol}//${url.host}`;
}

function rewriteLocation(value, url) {
  if (!value) return value;
  const base = publicBase(url);
  return value
    .replace(/^https?:\/\/blazepanel\.mywp\.info\/devil-ai\/?/i, base + '/')
    .replace(/^https?:\/\/blazepanel\.mywp\.info\/?/i, base + '/')
    .replace(/^\/devil-ai\/?/i, '/');
}

function rewriteCookie(value) {
  if (!value) return value;
  return value
    .replace(/;\s*Domain=blazepanel\.mywp\.info/ig, '')
    .replace(/;\s*Path=\/devil-ai\/?/ig, '; Path=/');
}

function rewriteBody(text, url) {
  const base = publicBase(url);
  const escBase = base.replace(/\//g, '\\/');
  return text
    .replace(/https:\/\/blazepanel\.mywp\.info\/devil-ai/gi, base)
    .replace(/http:\/\/blazepanel\.mywp\.info\/devil-ai/gi, base)
    .split('https:\\/\\/blazepanel.mywp.info\\/devil-ai').join(escBase)
    .split('http:\\/\\/blazepanel.mywp.info\\/devil-ai').join(escBase)
    .replace(/(['"(=\s])\/devil-ai\//g, '$1/')
    .replace(/(['"(=\s])\/devil-ai(['"\s?#>])/g, '$1/$2')
    .replace(/Path=\/devil-ai\//g, 'Path=/')
    .replace(/Path=\/devil-ai/g, 'Path=/')
    .split('\\/devil-ai\\/').join('\\/')
    .split('\\/devil-ai').join('\\/')
    // If JSON-escaped PHP base path becomes just / on the proxied root, keep app URL builders relative.
    .replace(/const\s+APP_BASE_PATH\s*=\s*["'](?:\\\/|\/)["'];/g, 'const APP_BASE_PATH = "";')
    .replace(/(<base\s+href=["'])\/\/(["'])/gi, '$1/$2');
}

addEventListener('fetch', event => {
  event.respondWith(handleRequest(event.request));
});

async function handleRequest(request) {
    const incomingUrl = new URL(request.url);
    const host = incomingUrl.hostname.toLowerCase();
    const redirectHost = HOST_REDIRECTS[host];
    if (redirectHost) {
      incomingUrl.protocol = 'https:';
      incomingUrl.hostname = redirectHost;
      if (incomingUrl.pathname === APP_BASE || incomingUrl.pathname.startsWith(APP_BASE + '/')) {
        incomingUrl.pathname = incomingUrl.pathname.slice(APP_BASE.length) || '/';
      }
      return Response.redirect(incomingUrl.toString(), 301);
    }
    if (incomingUrl.protocol === 'http:') {
      incomingUrl.protocol = 'https:';
      return Response.redirect(incomingUrl.toString(), 301);
    }
    if (host === API_HOST && request.method === 'OPTIONS') {
      const origin = request.headers.get('Origin') || '';
      const allowed = ['https://ai.devil.blazenxt.com','https://ai.devil.blazenxt.in'].includes(origin) ? origin : 'https://ai.devil.blazenxt.com';
      return new Response(null, {status:204, headers:{'Access-Control-Allow-Origin':allowed,'Access-Control-Allow-Credentials':'true','Access-Control-Allow-Headers':'Authorization, Content-Type, X-Devil-API-Key','Access-Control-Allow-Methods':'GET, POST, OPTIONS','Vary':'Origin'}});
    }
    const reason = await blockReason(request, incomingUrl);
    if (reason) return securityBlockResponse(request, reason);
    const originUrl = new URL(host === API_HOST ? BACKEND_ORIGIN : ORIGIN);
    originUrl.pathname = mapToOriginPath(incomingUrl.pathname, host);
    originUrl.search = incomingUrl.search;

    const headers = new Headers(request.headers);
    const cf = request.cf || {};
    headers.set('X-Forwarded-Host', incomingUrl.host);
    headers.set('X-Forwarded-Proto', incomingUrl.protocol.replace(':', ''));
    headers.set('X-Devil-AI-Proxy', 'cloudflare');
    headers.set('X-Devil-Client-IP', request.headers.get('CF-Connecting-IP') || '');
    headers.set('X-Forwarded-For', request.headers.get('CF-Connecting-IP') || '');
    headers.set('X-Devil-Client-ASN', String(cf.asn || ''));
    headers.set('X-Devil-Client-ASO', String(cf.asOrganization || cf.asnOrganization || ''));
    headers.set('X-Devil-Client-Country', String(cf.country || ''));
    headers.set('X-Devil-Client-Threat', String(cf.threatScore || cf.threat_score || ''));
    headers.delete('cf-connecting-ip');
    headers.delete('x-forwarded-for');
    headers.delete('x-real-ip');
    headers.delete('via');
    headers.delete('forwarded');
    headers.delete('x-forwarded');
    headers.delete('forwarded-for');
    headers.delete('client-ip');
    headers.delete('x-client-ip');
    headers.delete('x-cluster-client-ip');
    headers.delete('proxy-connection');
    headers.delete('proxy-authorization');
    headers.delete('x-proxy-authorization');
    headers.delete('x-proxy-id');

    const originRequest = new Request(originUrl.toString(), {
      method: request.method,
      headers,
      body: ['GET', 'HEAD'].includes(request.method) ? undefined : request.body,
      redirect: 'manual'
    });

    const originResponse = await fetch(originRequest);
    const responseHeaders = new Headers(originResponse.headers);

    const location = responseHeaders.get('Location');
    if (location) responseHeaders.set('Location', rewriteLocation(location, incomingUrl));

    const setCookie = responseHeaders.get('Set-Cookie');
    if (setCookie) responseHeaders.set('Set-Cookie', rewriteCookie(setCookie));

    responseHeaders.delete('content-security-policy');
    responseHeaders.delete('content-length');
    responseHeaders.set('X-Devil-AI-Proxy', 'cloudflare');

    const contentType = responseHeaders.get('Content-Type') || '';
    const shouldRewrite = /text\/html|application\/javascript|text\/javascript|application\/json|text\/css|application\/manifest\+json|text\/plain/i.test(contentType);
    if (shouldRewrite) {
      const body = rewriteBody(await originResponse.text(), incomingUrl);
      return new Response(body, {
        status: originResponse.status,
        statusText: originResponse.statusText,
        headers: responseHeaders
      });
    }

    return new Response(originResponse.body, {
      status: originResponse.status,
      statusText: originResponse.statusText,
      headers: responseHeaders
    });
}
