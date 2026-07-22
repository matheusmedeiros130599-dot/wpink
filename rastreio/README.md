# Sistema de Rastreio de Pedidos

## 1. O que é este projeto

Sistema de rastreio de pedidos simulado em PHP puro, sem banco de dados. O seller configura sua plataforma de vendas (Hotmart, Kiwify, etc.) para enviar um webhook a cada venda aprovada. O sistema extrai os dados de entrega da string UTM, salva um arquivo JSON por pedido e exibe uma página de rastreio ao cliente final — idêntica visualmente ao rastreio dos Correios, porém com identidade visual da loja.

Funciona em qualquer hospedagem compartilhada compatível com PHP 7.4+, incluindo servidores com WordPress instalado.

---

## 2. Requisitos de hospedagem

- PHP 7.4 ou superior
- Apache com `mod_rewrite` habilitado (para o `.htaccess` da pasta `/pedidos/`)
- Permissão de escrita na pasta `/pedidos/` (`chmod 755 pedidos/`)
- Sem dependências externas — nenhum Composer, nenhum banco de dados

---

## 3. Como instalar

1. **Descompacte** a pasta `rastreio/` no diretório público do seu servidor (ex: `public_html/rastreio/`)
2. **Configure** o arquivo `config.php` com os dados da sua loja (nome, cor, cidade de origem, etc.)
3. **Aponte o webhook** da sua plataforma de vendas para:
   ```
   https://seusite.com/rastreio/webhook.php
   ```
4. Acesse `https://seusite.com/rastreio/` para ver a tela de consulta

> **Importante:** verifique se a pasta `pedidos/` tem permissão de escrita. No cPanel, clique com o botão direito na pasta e selecione "Alterar permissões" → 755.

---

## 4. Parâmetros `tr_` aceitos no campo UTM

Estes parâmetros devem ser inseridos no campo UTM da sua plataforma de vendas (ex: UTM Content ou UTM Term), concatenados como query string.

| Parâmetro       | Obrigatório | Descrição                                      | Exemplo                    |
|-----------------|:-----------:|------------------------------------------------|----------------------------|
| `tr_codigo`     | Sim         | Código de rastreio dos Correios                | `QN749955838BR`            |
| `tr_prazo_min`  | Não         | Prazo mínimo de entrega em dias                | `5`                        |
| `tr_prazo_max`  | Não         | Prazo máximo de entrega em dias                | `7`                        |
| `tr_produto`    | Não         | Nome do produto enviado                        | `Kit+Presencial+Premium`   |
| `tr_cidade`     | Não         | Cidade de destino                              | `São+Paulo`                |
| `tr_estado`     | Não         | Estado de destino (sigla)                      | `SP`                       |
| `tr_cep`        | Não         | CEP de destino                                 | `01310-100`                |
| `tr_rua`        | Não         | Rua de destino                                 | `Avenida+Paulista`         |
| `tr_numero`     | Não         | Número do endereço                             | `1000`                     |
| `tr_bairro`     | Não         | Bairro de destino                              | `Bela+Vista`               |

**Fallbacks automáticos:**
- `tr_prazo_min` / `tr_prazo_max` → usa os valores de `config.php` se ausentes
- `tr_cidade` / `tr_estado` → tenta `utm_city` / `utm_state` se os `tr_` não existirem
- `tr_produto` → usa o campo `items.title` do payload se ausente

---

## 5. Como testar com Postman

1. Abra o Postman e crie uma nova requisição `POST`
2. Defina a URL: `https://seusite.com/rastreio/webhook.php`
3. Na aba **Headers**, adicione:
   - `Content-Type: application/json`
4. Na aba **Body**, selecione **raw → JSON** e cole o conteúdo do arquivo `payload_teste.json`:

```json
{
  "status": "COMPLETED",
  "customer": {
    "name": "Maria Oliveira",
    "email": "maria.oliveira@gmail.com"
  },
  "items": {
    "title": "Curso Premium de Marketing Digital"
  },
  "approvedAt": "2026-05-28T10:15:00.000Z",
  "utm": "utm_source=facebook&utm_medium=cpc&utm_campaign=vendas-maio&tr_codigo=QN749955838BR&tr_prazo_min=5&tr_prazo_max=7&tr_produto=Kit+Presencial+Premium&tr_cidade=São+Paulo&tr_estado=SP&tr_cep=01310-100&tr_rua=Avenida+Paulista&tr_numero=1000&tr_bairro=Bela+Vista"
}
```

5. Clique em **Send**
6. Resposta esperada: `{"success": true, "codigo": "QN749955838BR"}`
7. Para testar no cliente: acesse `https://seusite.com/rastreio/?codigo=QN749955838BR`

---

## 6. Como o cliente consulta o rastreio

O cliente recebe o link de rastreio por e-mail ou WhatsApp no formato:

```
https://seusite.com/rastreio/?codigo=QN749955838BR
```

Ao acessar, o sistema exibe automaticamente a linha do tempo com os eventos já ocorridos com base no tempo decorrido desde a postagem, incluindo a previsão de entrega.

---

## 7. Estrutura de arquivos

```
rastreio/
├── config.php          ← Configurações da loja (edite este arquivo)
├── webhook.php         ← Endpoint que recebe o POST da plataforma
├── index.php           ← Página de busca (formulário de consulta)
├── rastreio.php        ← Página de resultado com a linha do tempo
├── payload_teste.json  ← Payload de exemplo para testes no Postman
├── pedidos/
│   ├── .htaccess       ← Bloqueia acesso direto aos JSONs via browser
│   └── *.json          ← Um arquivo por pedido (gerado automaticamente)
└── assets/
    └── style.css       ← Estilos CSS (sem frameworks externos)
```
