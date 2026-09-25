const http = require('http');
const fs = require('fs');
const path = require('path');
const https = require('https');

// Prevent server from crashing on uncaught errors
process.on('uncaughtException', err => {
  console.error('[UNCAUGHT EXCEPTION]', err);
});
process.on('unhandledRejection', reason => {
  console.error('[UNHANDLED REJECTION]', reason);
});

// Load credentials from .env
const dotenv = {};
const envPath = path.join(__dirname, '.env');
if (fs.existsSync(envPath)) {
  fs.readFileSync(envPath, 'utf8').split('\n').forEach(line => {
    const parts = line.split('=');
    if (parts.length > 1) {
      dotenv[parts[0].trim()] = parts.slice(1).join('=').trim();
    }
  });
}

// Payment Gateway (BlackCat) Configurations
const BLACKCAT_API_KEY = process.env.BLACKCAT_API_KEY || dotenv.BLACKCAT_API_KEY || '';
const BLACKCAT_API_URL = process.env.BLACKCAT_API_URL || dotenv.BLACKCAT_API_URL || 'https://api.blackcatoficial.com/api';
const PORT = process.env.PORT || dotenv.PORT || 3000;

console.log('--- Server Configurations ---');
console.log('BLACKCAT_API_URL:', BLACKCAT_API_URL);
console.log('Server Port:', PORT);
console.log('-----------------------------------');

const MIME_TYPES = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'application/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.gif': 'image/gif',
  '.webp': 'image/webp',
  '.svg': 'image/svg+xml',
  '.woff': 'font/woff',
  '.woff2': 'font/woff2',
  '.ttf': 'font/ttf',
  '.mp4': 'video/mp4',
  '.ico': 'image/x-icon'
};

// Serve Primary Landing Page
function servePrimaryLandingPage(req, res, parsedUrl) {
  const presellPath = path.join(__dirname, 'presell.html');
  const indexPath = path.join(__dirname, 'index.html');

  if (parsedUrl.searchParams.has('loja')) {
    serveFile(indexPath, res);
  } else if (fs.existsSync(presellPath)) {
    serveFile(presellPath, res);
  } else {
    serveFile(indexPath, res);
  }
}

// Helper for making requests to BlackCat API
function blackcatRequest(method, endpoint, payloadObj = null) {
  return new Promise((resolve, reject) => {
    const urlObj = new URL(BLACKCAT_API_URL + endpoint);

    const options = {
      hostname: urlObj.hostname,
      port: 443,
      path: urlObj.pathname + urlObj.search,
      method: method,
      headers: {
        'Content-Type': 'application/json',
        'X-API-Key': BLACKCAT_API_KEY
      }
    };
    
    let bodyData = '';
    if (payloadObj) {
      bodyData = JSON.stringify(payloadObj);
      options.headers['Content-Length'] = Buffer.byteLength(bodyData);
    }
    
    const req = https.request(options, (res) => {
      let responseBody = '';
      res.on('data', (chunk) => { responseBody += chunk; });
      res.on('end', () => {
        try {
          const parsed = JSON.parse(responseBody);
          resolve({ statusCode: res.statusCode, data: parsed });
        } catch (e) {
          resolve({ statusCode: res.statusCode, raw: responseBody });
        }
      });
    });
    
    req.on('error', (err) => {
      reject(err);
    });
    
    if (payloadObj) {
      req.write(bodyData);
    }
    req.end();
  });
}

function serveFile(filePath, res) {
  const ext = path.extname(filePath).toLowerCase();
  const contentType = MIME_TYPES[ext] || 'application/octet-stream';
  
  fs.readFile(filePath, (err, data) => {
    if (err) {
      res.writeHead(500, { 'Content-Type': 'text/plain' });
      res.end('500 Internal Server Error');
    } else {
      res.writeHead(200, { 
        'Content-Type': contentType,
        'Cache-Control': 'no-cache, no-store, must-revalidate'
      });
      res.end(data);
    }
  });
}

// ==========================================
// HTTP SERVER & ROUTING ENGINE
// ==========================================
const server = http.createServer(async (req, res) => {
  const reqUrl = req.url || '';
  const parsedUrl = new URL(reqUrl, `http://localhost:${PORT}`);
  const pathname = parsedUrl.pathname;
  
  // Log incoming requests
  console.log(`[${new Date().toLocaleTimeString()}] ${req.method} ${pathname}`);
  
  // 1. API: CREATE PIX
  if ((pathname === '/api/create-pix' || pathname === '/create-pix.php') && req.method === 'POST') {
    const bodyChunks = [];
    req.on('data', chunk => bodyChunks.push(chunk));
    req.on('end', async () => {
      try {
        const body = JSON.parse(Buffer.concat(bodyChunks).toString());
        console.log('Received Pix Order Body:', JSON.stringify(body, null, 2));
        
        const customer = body.customer || {};
        const shipping = body.shipping || {};
        const items = Array.isArray(body.items) ? body.items : [];
        const document = customer.documentNumber || '';
        const cpf = document.replace(/\D/g, '');
        const amountCents = body.amountCents ? Math.round(body.amountCents) : 0;

        if (amountCents <= 0) {
          res.writeHead(400, { 'Content-Type': 'application/json' });
          res.end(JSON.stringify({ success: false, error: 'O valor do pedido deve ser maior que zero.' }));
          return;
        }

        let payloadItems = items.map(item => ({
          title: item.title || 'Produto',
          unitPrice: Math.round(item.unitPrice || 0),
          quantity: item.quantity || 1,
          tangible: true
        }));

        if (payloadItems.length === 0) {
          payloadItems = [{
            title: 'Pedido',
            unitPrice: amountCents,
            quantity: 1,
            tangible: true
          }];
        }

        const blackcatPayload = {
          amount: amountCents,
          currency: 'BRL',
          paymentMethod: 'pix',
          items: payloadItems,
          customer: {
            name: customer.name || 'Cliente',
            email: customer.email || 'cliente@email.com',
            phone: customer.phone ? customer.phone.replace(/\D/g, '') : '',
            document: {
              number: cpf,
              type: cpf.length > 11 ? 'cnpj' : 'cpf'
            }
          },
          pix: {
            expiresInDays: 1
          },
          shipping: {
            name: shipping.name || customer.name || 'Cliente',
            street: shipping.street || '',
            number: shipping.number || '',
            complement: shipping.complement || '',
            neighborhood: shipping.neighborhood || '',
            city: shipping.city || '',
            state: shipping.state || '',
            zipCode: (shipping.zipCode || '').replace(/\D/g, '')
          },
          externalRef: body.idempotencyKey || 'PED-' + Date.now()
        };

        console.log('Sending payload to BlackCat:', blackcatPayload);

        const blackcatRes = await blackcatRequest('POST', '/sales/create-sale', blackcatPayload);
        console.log('BlackCat API Status:', blackcatRes.statusCode);
        console.log('BlackCat API Data:', blackcatRes.data);

        const data = blackcatRes.data && blackcatRes.data.data;

        if (blackcatRes.statusCode >= 200 && blackcatRes.statusCode < 300 && blackcatRes.data && blackcatRes.data.success && data && data.transactionId) {
          res.writeHead(200, { 'Content-Type': 'application/json' });
          res.end(JSON.stringify({
            success: true,
            transactionId: data.transactionId,
            paymentData: {
              qrCode: data.paymentData?.qrCode || data.paymentData?.copyPaste || '',
              qrCodeBase64: data.paymentData?.qrCodeBase64 || null
            },
            status: data.status || 'PENDING'
          }));
        } else {
          const errMsg = blackcatRes.data?.message || blackcatRes.data?.error || 'Erro na resposta do gateway de pagamento.';
          res.writeHead(400, { 'Content-Type': 'application/json' });
          res.end(JSON.stringify({ success: false, error: errMsg }));
        }
      } catch (e) {
        console.error('Error generating PIX:', e);
        res.writeHead(500, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ success: false, error: 'Ocorreu um erro no servidor ao processar o pagamento.' }));
      }
    });
    return;
  }
  
  // 2. API: CHECK STATUS
  if ((pathname === '/api/check-status' || pathname === '/check-status.php') && req.method === 'GET') {
    const transactionId = parsedUrl.searchParams.get('id') || '';
    if (!transactionId) {
      res.writeHead(400, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ success: false, error: 'transactionId não fornecido.' }));
      return;
    }
    
    console.log(`Checking BlackCat status for: ${transactionId}`);

    blackcatRequest('GET', `/sales/${encodeURIComponent(transactionId)}/status`)
      .then(blackcatRes => {
        console.log(`BlackCat Status Response for ${transactionId}:`, blackcatRes.data);

        const data = blackcatRes.data && blackcatRes.data.data;

        if (blackcatRes.statusCode >= 200 && blackcatRes.statusCode < 300 && blackcatRes.data && blackcatRes.data.success && data && data.status) {
          res.writeHead(200, { 'Content-Type': 'application/json' });
          res.end(JSON.stringify({
            success: true,
            status: data.status
          }));
        } else {
          res.writeHead(200, { 'Content-Type': 'application/json' });
          res.end(JSON.stringify({
            success: true,
            status: 'PENDING',
            message: blackcatRes.data?.message || 'Aguardando atualização do gateway.'
          }));
        }
      })
      .catch(err => {
        console.error('Error checking status:', err);
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({
          success: true,
          status: 'PENDING',
          error: err.message
        }));
      });
    return;
  }
  
  // 3. API: PROOF UPLOAD (MOCK)
  if ((pathname === '/api/pix-proof-upload.php' || pathname === '/pix-proof-upload.php') && req.method === 'POST') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ ok: true, message: 'Comprovante enviado com sucesso!' }));
    return;
  }

  // 4. STATIC ASSET SERVING
  const ext = path.extname(pathname).toLowerCase();
  const isAsset = (ext && ext !== '.html' && ext !== '.php') || pathname.startsWith('/assets/');
  if (isAsset) {
    const filePath = path.join(__dirname, pathname);
    fs.stat(filePath, (err, stats) => {
      if (!err && stats.isFile()) {
        serveFile(filePath, res);
      } else {
        res.writeHead(404, { 'Content-Type': 'text/plain' });
        res.end('404 Not Found');
      }
    });
    return;
  }

  // 5. PAGE ROUTING & SPA FALLBACK
  // Root dynamic route
  if (pathname === '/' || pathname === '') {
    servePrimaryLandingPage(req, res, parsedUrl);
    return;
  }

  // Checkout route
  if (pathname === '/checkout') {
    const checkoutHtmlPath = path.join(__dirname, 'checkout.html');
    serveFile(checkoutHtmlPath, res);
    return;
  }

  // Presell / Offer routes
  if (pathname === '/presell' || pathname === '/ofertas' || pathname === '/oferta') {
    const presellHtmlPath = path.join(__dirname, 'presell.html');
    if (fs.existsSync(presellHtmlPath)) {
      serveFile(presellHtmlPath, res);
    } else {
      servePrimaryLandingPage(req, res, parsedUrl);
    }
    return;
  }

  // Other routes: check if file exists or fallback to primary landing page
  const possibleFilePath = path.join(__dirname, pathname);
  fs.stat(possibleFilePath, (err, stats) => {
    if (!err && stats.isFile()) {
      serveFile(possibleFilePath, res);
    } else {
      servePrimaryLandingPage(req, res, parsedUrl);
    }
  });
});

server.listen(PORT, () => {
  console.log(`\n======================================================`);
  console.log(`🚀 Landing Page rodando em: http://localhost:${PORT}/`);
  console.log(`📁 Diretório de arquivos: ${__dirname}`);
  console.log(`======================================================\n`);
});
