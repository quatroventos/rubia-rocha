# Rubia Rocha Fotografia

Um container Laravel. O visitante vê um site estático (Blade + CSS/JS). O CMS é um SPA React no path secreto. Não existe API pública.

## O que sobe

- Site: `http://localhost:8000`
- CMS: `http://localhost:8000/{ADMIN_PATH}/login` (padrão local: `/gestao/login`)
- Healthcheck (nginx, sem PHP): `GET /healthz`
- Mailpit (local): `http://localhost:8025`

Não use `php artisan migrate:fresh`. Não rode prune no Docker sem pedido explícito.

## Variáveis

| Variável | Função |
|---|---|
| `APP_KEY` | Chave da aplicação. O entrypoint gera se estiver vazia. |
| `APP_URL` | URL pública (canonical, mídia, sitemap). |
| `ADMIN_PATH` | Slug do painel. Não use `admin`. Em produção use um valor opaco. |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | Usuário inicial do seed. |
| `DB_*` | MySQL. |
| `REDIS_*` | Cache, fila e sessão em prod. |
| `MAIL_*` | Destino das mensagens de contato/currículo. |
| `SESSION_COOKIE` | Nome do cookie (`sid`). Não deixe o default `laravel-session`. |
| `SESSION_SECURE_COOKIE` | `true` em HTTPS. |
| `RUN_MIGRATIONS` | `true` no boot do container para `migrate --force`. |
| `RUN_SEED` | `true` **uma vez** na primeira subida. O seeder é idempotente (`updateOrCreate`). |

## Local

```bash
cp .env.example .env
docker compose up --build
```

O entrypoint instala o Composer se faltar `vendor`, cria o `APP_KEY`, dá `storage:link`, migra e (se `RUN_SEED=true`) popula conteúdo e o admin.

Login do CMS: `http://localhost:8000/gestao/login`

- e-mail: `ADMIN_EMAIL`
- senha: `ADMIN_PASSWORD`

Mailpit: `http://localhost:8025`.

MySQL e Redis ficam só na rede Docker (sem publicar 3306/6379 no host). Para inspecionar o banco: `docker compose exec mysql mysql -urubiarocha -prubiarocha rubiarocha`.

Para parar: `docker compose down`. Os dados do MySQL ficam no volume `mysql_data`.

## Homologação (EasyPanel)

1. Crie MySQL e Redis **no painel**, fora deste compose.
2. Crie o app a partir deste repositório, **um serviço = este Dockerfile**.
3. Porta interna: `8000`. Healthcheck: `/healthz`.
4. Domínio no Cloudflare apontando para o app (SSL Full Strict).
5. Env mínimo:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seu-dominio
ADMIN_PATH=um-slug-opaco
DB_CONNECTION=mysql
DB_HOST=<host do MySQL do EasyPanel>
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
REDIS_HOST=<host Redis do EasyPanel>
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_COOKIE=sid
SESSION_SECURE_COOKIE=true
RUN_MIGRATIONS=true
RUN_SEED=true
```

6. Primeira subida: `RUN_SEED=true`. Depois de validar o conteúdo, **desligue** `RUN_SEED`.
7. Fila e scheduler: crie serviços extras com a **mesma imagem**, sem segundo Dockerfile:

- queue: `php artisan queue:work --sleep=1 --tries=3`
- scheduler: `php artisan schedule:work`

8. Cloudflare Access no path `/{ADMIN_PATH}*` é camada extra recomendada.

## Produção (VPS + Docker)

Cloudflare na frente (Full Strict). O compose publica só `127.0.0.1:8000`.

```bash
git clone <repo>
cd <repo>
cp .env.example .env
# edite APP_URL, APP_KEY, ADMIN_PATH, DB_*, MYSQL_ROOT_PASSWORD, senhas
docker compose -f compose.prod.yml up -d --build
```

Primeira vez, suba o app com `RUN_SEED=true` no `.env` **uma vez**, depois volte para `false` e recrie só o serviço `app` se precisar.

Firewall: 80/443 (ou só o proxy do Cloudflare). Não publique 3306 nem 6379.

Backup: volume `mysql_data` e `app_storage` (currículos em disco `local` + mídia pública). Snapshot dos dois.

Atualizar:

```bash
git pull
docker compose -f compose.prod.yml up -d --build
```

O entrypoint roda `migrate --force` quando `RUN_MIGRATIONS=true`. Nunca `migrate:fresh`.

## cPanel (shared, sem container)

PHP 8.3 ou 8.4 no MultiPHP. Extensões: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `fileinfo`, `gd`, `zip`, `intl`, `bcmath`, `exif`, `curl`. Redis não entra.

Na sua máquina, na raiz do projeto:

```bash
bash package-cpanel.sh
```

Isso gera `rubiarocha-cpanel.zip` com `vendor` (`--no-dev`) e o build do CMS (`public/build`). O `.env` não vai no zip.

1. Extraia o zip fora do público, por exemplo `/home/USER/rubiarocha`.
2. No cPanel, no domínio, aponte o document root para `/home/USER/rubiarocha/public`. Se o painel não deixar mudar o document root, extraia dentro de `public_html`: o `.htaccess` da raiz manda o tráfego para `public/` e bloqueia `.env`, `vendor`, `app` e o resto.
3. MySQL Databases: crie o banco e o usuário, com ALL PRIVILEGES. No phpMyAdmin, importe o dump. Host no `.env` é `localhost`.
4. Copie `.env.cpanel.example` para `.env` e preencha `APP_URL`, `ADMIN_PATH`, `DB_*`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` e o SMTP da conta de e-mail do cPanel (`MAIL_HOST=localhost`, porta 587).
5. No Terminal do cPanel, na pasta do projeto:

```bash
php artisan key:generate --force
php artisan storage:link
chmod -R ug+rwx storage bootstrap/cache
```

Se o banco importado já tem senha SMTP ou MFA gravados, use o mesmo `APP_KEY` do ambiente de origem. `key:generate` nesse caso invalida esses campos.

Banco vazio, sem dump: no Terminal, uma vez,

```bash
php artisan migrate --force
php artisan db:seed --force
```

O seed cria o admin, as configurações, o menu, as páginas (`database/data/pages/*.json`) e os cases. Não use `migrate:fresh`.

Para recarregar só o conteúdo, sem tocar nos usuários: `php artisan db:seed --class=SiteContentSeeder --force`. Antes de sobrescrever uma página, o seeder salva uma revisão dela.

Para completar páginas já editadas no CMS com campos ou seções novas dos JSONs, sem sobrescrever conteúdo: `php artisan cms:backfill-sections --dry-run` (lista as mudanças) e, depois, `php artisan cms:backfill-sections`. Cada página alterada ganha uma revisão antes de salvar.

Cache, sessão e fila ficam no MySQL. Não há worker. O cron do cPanel (PHP 8.3, todo minuto) cobre backup e Lighthouse:

```bash
/usr/local/bin/ea-php83 /home/USER/rubiarocha/artisan schedule:run >> /dev/null 2>&1
```

Troque o binário pelo que o seletor de PHP mostrar, se não for `ea-php83`.

Mídia pública: `public/storage` aponta para `storage/app/public`. Se o servidor recusar symlink, `/storage/...` ainda responde pelo PHP. Currículo fica em `storage/app/private` e não entra em URL.

Não rode `config:cache`: o seed e o fallback de SMTP chamam `env()` fora de `config/`. `view:cache` pode.

## Troca de domínio

Ao publicar em um domínio novo, ou ao subir um banco vindo de outro ambiente, migre os links absolutos gravados no CMS. Rode antes com `--dry-run`, que só lista campo, registros e links que mudariam:

```bash
php artisan site:migrate-domain https://rubiarochafotografia.com.br --dry-run
php artisan site:migrate-domain https://rubiarochafotografia.com.br
```

Sem `--from`, o comando troca os hosts de desenvolvimento (`localhost`, `127.0.0.1`, `0.0.0.0`, `host.docker.internal` e túneis ngrok, com qualquer porta). Para trocar um domínio anterior, informe-o (pode repetir a opção):

```bash
php artisan site:migrate-domain https://novo.dominio.com.br --from=fotografacuritiba.com.br --revisions
```

Opções:

- `--from=dominio`: domínio antigo. Casa o host exato, então subdomínios externos (ex.: `blog.fotografacuritiba.com.br`) não são alterados.
- `--relative`: converte os links do domínio antigo em caminhos relativos (`/servicos`) em vez de trocar o domínio.
- `--revisions`: inclui o histórico de revisões das páginas.
- `--dry-run`: não grava nada.
- `--skip-prerender`: não regenera o HTML pré-renderizado. Por padrão, uma execução real limpa o cache das configurações e roda `site:prerender` no fim.

Campos cobertos: páginas (conteúdo, canonical, imagem OG), cases (textos, galeria, capa, entregas, resultados, depoimento), menus, certificações e configurações (logo, favicon, OG, tags de header/body/footer, sitemap extra, formulários, traduções, logos de clientes e links de planos). SMTP e a chave da Evolution ficam de fora por serem criptografados. JSON com barras escapadas (`https:\/\/...`) também é tratado.

O comando não altera o `.env`: ajuste `APP_URL` e as URLs do site em `OPENSEO_*` no ambiente novo.

## OG images e tradução em lote

OG image é um card 1200x630 (logo, fundo do hero da página ou capa do case, título com destaque e endereço) gerado pelo Chrome do servidor. Cards gerados são refeitos sozinhos, pela fila, quando o título ou o conteúdo da página muda; imagens enviadas à mão não são tocadas nesse caso.

- CMS: SEO > Padrão das páginas > "Gerar todas as OG images" (sobrescreve todas, inclusive as enviadas à mão). No editor de página e de case, "Gerar card de OG" usa o conteúdo ainda não salvo.
- Terminal: `php artisan site:og-images` (opções `--pages`, `--cases`, `--locale=en`, `--slug=agentes-de-ia`, `--only-missing`).
- `OG_CAPTURE_URL`: endereço interno da aplicação visto pelo Chrome do worker. Nos compose já vem configurado (`http://rubiarocha:8000` em dev, `http://rubiarocha-app:8000` em prod). Não use o host `app`: o Chrome força HTTPS no TLD `.app`.
- `OG_DOMAIN`: domínio exibido no card (padrão: host do `APP_URL`, sem www).

Tradução: Páginas > "Traduzir site" enfileira páginas e cases publicados, menus e textos do site em todos os idiomas e publica o resultado. Sem marcar "Retraduzir", pula o que já está traduzido e em dia. Pelo terminal continua valendo `php artisan site:translate --publish`.

Os dois lotes rodam no worker da fila (`queue:work`); sem worker, nada anda.

## Checklist

- `GET /` não envia `Set-Cookie`.
- `GET /api`, `/admin`, `/login`, `/up`, `/livewire` devolvem o 404 do site (HTML).
- Login só em `/{ADMIN_PATH}/login`. JSON do CMS só em `/{ADMIN_PATH}/api`.
- Contato e carreiras gravam e disparam e-mail.
- Currículo não fica em URL pública.
- Editor não vê Usuários, Tags nem settings de site.
