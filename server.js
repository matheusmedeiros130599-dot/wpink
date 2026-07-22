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

const ZUCKPAY_CLIENT_ID = process.env.ZUCKPAY_CLIENT_ID || dotenv.ZUCKPAY_CLIENT_ID || 'matheusmedeiros130599_2781149172';
const ZUCKPAY_CLIENT_SECRET = process.env.ZUCKPAY_CLIENT_SECRET || dotenv.ZUCKPAY_CLIENT_SECRET || 'e2df3cd0c85ea4570627bb3699c57245281de3ecafe195e59a90f747a86ec7d3';
const ZUCKPAY_API_URL = process.env.ZUCKPAY_API_URL || dotenv.ZUCKPAY_API_URL || 'https://www.zuckpay.com.br';
const PORT = process.env.PORT || dotenv.PORT || 3000;

console.log('--- Wpink Server Configurations ---');
console.log('ZUCKPAY_CLIENT_ID:', ZUCKPAY_CLIENT_ID);
console.log('ZUCKPAY_API_URL:', ZUCKPAY_API_URL);
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
  '.mp4': 'video/mp4'
};

// Helper for making requests to ZuckPay API
function zuckRequest(method, endpoint, payloadObj = null) {
  return new Promise((resolve, reject) => {
    const auth = Buffer.from(ZUCKPAY_CLIENT_ID + ":" + ZUCKPAY_CLIENT_SECRET).toString('base64');
    
    // Parse hostname and path from ZuckPay API URL
    const urlObj = new URL(ZUCKPAY_API_URL + endpoint);
    
    const options = {
      hostname: urlObj.hostname,
      port: 443,
      path: urlObj.pathname + urlObj.search,
      method: method,
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Basic ' + auth
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

// Helper to download an image as base64 string
function downloadImageAsBase64(imageUrl) {
  return new Promise((resolve) => {
    if (!imageUrl) return resolve(null);
    
    https.get(imageUrl, (res) => {
      if (res.statusCode !== 200) {
        return resolve(null);
      }
      
      const chunks = [];
      res.on('data', (chunk) => chunks.push(chunk));
      res.on('end', () => {
        const buffer = Buffer.concat(chunks);
        resolve(buffer.toString('base64'));
      });
    }).on('error', () => {
      resolve(null);
    });
  });
}

// Generate base64 QR Code using public QRServer API fallback
function generateFallbackQrBase64(qrText) {
  return new Promise((resolve) => {
    const qrApiUrl = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(qrText)}`;
    https.get(qrApiUrl, (res) => {
      if (res.statusCode !== 200) return resolve(null);
      const chunks = [];
      res.on('data', (chunk) => chunks.push(chunk));
      res.on('end', () => {
        const buffer = Buffer.concat(chunks);
        resolve(buffer.toString('base64'));
      });
    }).on('error', () => {
      resolve(null);
    });
  });
}

const server = http.createServer((req, res) => {
  const reqUrl = req.url || '';
  const parsedUrl = new URL(reqUrl, `http://localhost:${PORT}`);
  const pathname = parsedUrl.pathname;
  
  // LOG incoming requests
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
        const document = customer.documentNumber || '';
        const amount = body.amountCents ? (body.amountCents / 100) : 0;
        
        if (amount <= 0) {
          res.writeHead(400, { 'Content-Type': 'application/json' });
          res.end(JSON.stringify({ success: false, error: 'O valor do pedido deve ser maior que zero.' }));
          return;
        }
        
        // ZuckPay Payload format
        const zuckPayload = {
          nome: customer.name || 'Cliente WPINK',
          cpf: document.replace(/\D/g, ''),
          valor: parseFloat(amount.toFixed(2)),
          email: customer.email || 'wepinksuplementos@gmail.com',
          telefone: customer.phone ? customer.phone.replace(/\D/g, '') : '',
          descricao: `Pedido WPINK — ${customer.name || 'PIX'}`,
          external_id_client: body.idempotencyKey || 'WP-' + Date.now()
        };
        
        console.log('Sending payload to ZuckPay:', zuckPayload);
        
        const zuckRes = await zuckRequest('POST', '/conta/v3/pix/qrcode', zuckPayload);
        console.log('ZuckPay API Status:', zuckRes.statusCode);
        console.log('ZuckPay API Data:', zuckRes.data);
        
        if (zuckRes.statusCode >= 200 && zuckRes.statusCode < 300 && zuckRes.data && zuckRes.data.transactionId) {
          const transId = zuckRes.data.transactionId;
          const pixCode = zuckRes.data.qrcode || zuckRes.data.pix_code || '';
          
          let qrCodeBase64 = null;
          
          // Try to convert ZuckPay's qrcode_image URL to base64
          if (zuckRes.data.qrcode_image) {
            qrCodeBase64 = await downloadImageAsBase64(zuckRes.data.qrcode_image);
          }
          
          // Fallback to public QR server if base64 is missing
          if (!qrCodeBase64 && pixCode) {
            qrCodeBase64 = await generateFallbackQrBase64(pixCode);
          }
          
          res.writeHead(200, { 'Content-Type': 'application/json' });
          res.end(JSON.stringify({
            success: true,
            transactionId: transId,
            paymentData: {
              qrCode: pixCode,
              qrCodeBase64: qrCodeBase64
            },
            status: 'pending'
          }));
        } else {
          const errMsg = zuckRes.data?.message || zuckRes.data?.error || 'Erro na resposta do gateway de pagamento.';
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
    
    console.log(`Checking ZuckPay status for: ${transactionId}`);
    
    zuckRequest('GET', `/conta/v3/pix/status?transactionId=${encodeURIComponent(transactionId)}`)
      .then(zuckRes => {
        console.log(`ZuckPay Status Response for ${transactionId}:`, zuckRes.data);
        
        if (zuckRes.statusCode >= 200 && zuckRes.statusCode < 300 && zuckRes.data && zuckRes.data.status) {
          const gatewayStatus = zuckRes.data.status.toUpperCase();
          const mappedStatus = gatewayStatus === 'PAID' ? 'paid' : 'pending';
          
          res.writeHead(200, { 'Content-Type': 'application/json' });
          res.end(JSON.stringify({
            success: true,
            status: mappedStatus
          }));
        } else {
          res.writeHead(200, { 'Content-Type': 'application/json' });
          res.end(JSON.stringify({
            success: true,
            status: 'pending',
            message: zuckRes.data?.message || 'Aguardando atualização do gateway.'
          }));
        }
      })
      .catch(err => {
        console.error('Error checking status:', err);
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({
          success: true,
          status: 'pending',
          error: err.message
        }));
      });
    return;
  }
  
  // 3. API: PROOF UPLOAD (MOCK)
  if ((pathname === '/api/pix-proof-upload.php' || pathname === '/pix-proof-upload.php') && req.method === 'POST') {
    // Simply mock success upload response
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ ok: true, message: 'Comprovante enviado com sucesso!' }));
    return;
  }
  
  // 4. STATIC FILE SERVING WITH SPA ROUTING FALLBACK
  let filePath = path.join(__dirname, pathname);
  
  fs.stat(filePath, (err, stats) => {
    if (!err && stats.isFile()) {
      // Serve file
      serveFile(filePath, res);
    } else {
      // SPA Fallback: if asset extension is requested, return 404
      const ext = path.extname(filePath);
      if (ext && ext !== '.html') {
        res.writeHead(404, { 'Content-Type': 'text/plain' });
        res.end('404 Not Found');
      } else {
        // Fallback to root index.html
        const indexHtmlPath = path.join(__dirname, 'index.html');
        serveFile(indexHtmlPath, res);
      }
    }
  });
});

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
        'Cache-Control': 'no-cache'
      });
      res.end(data);
    }
  });
}

server.listen(PORT, () => {
  console.log(`\n======================================================`);
  console.log(`🚀 WPINK Landing Page Clone rodando em: http://localhost:${PORT}/`);
  console.log(`📁 Diretório de arquivos: ${__dirname}`);
  console.log(`======================================================\n`);
});
