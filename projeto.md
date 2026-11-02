Desenvolvimento de um site institucional moderno e seguro para a Quatro Ventos, utilizando Laravel como único runtime.

O projeto prioriza segurança, performance e escalabilidade, com proteções avançadas via Cloudflare. O visitante vê um site estático (HTML/CSS/JS). O CMS é um SPA React no mesmo Laravel, em path secreto. Não há API pública em `/api`.

O site é otimizado para SEO (indexação no Google) e responsivo (smartphones, tablets e desktops).

Páginas e funcionalidades (site público)

Arquivos HTML convertidos para Blade. Conteúdo editável pelo CMS.

- Home: apresentação do escritório e serviços principais.
- Quem somos: história, valores e liderança.
- Expertise: áreas de atuação.
- Estrutura: unidades, tecnologia e segurança.
- IA: propósito, princípios e objetivos.
- ESG: pilares, ética e canal de denúncias.
- Carreiras: processo seletivo e envio de currículo.
- Fale conosco: formulário de contato e mapa.

Formulários de contato e carreiras são POST Blade (CSRF + rate limit). Currículos ficam em disco privado, sem URL pública.

Fora de escopo neste ciclo: blog/notícias, aplicativo mobile.

Painel administrativo (SPA)

CMS no mesmo Laravel. React + Vite, JSON só sob `/{ADMIN_PATH}/api`. Sessão + CSRF. Sem token no browser. Sem `/api` público.

- Path do painel definido por variável de ambiente (não `/admin`). Login em `/{ADMIN_PATH}/login`.
- Sem cadastro público e sem reset de senha público.
- Papéis: admin e editor (ACL).
- Edição de páginas (textos, imagens, SEO, publicar/rascunho). Sem criar nem apagar páginas.
- Identidade do site, equipe, filiais, menu, certificações e opções de formulário.
- Inbox de mensagens e currículos (download autenticado).
- Tags de marketing (header/body/footer) restritas a admin.
- Sitemap extra e SEO por página.
- Logs de auditoria (quem alterou página, settings e usuários).

O painel não aparece no HTML público, no sitemap nem no robots.txt. Rotas óbvias (`/admin`, `/login`, `/filament`, `/api`) devolvem o mesmo 404 do site.

Arquitetura

- Um app, um domínio, um deploy.
- Site público: Blade + CSS/JS estáticos. GET institucional sem cookie de sessão.
- Sessão só nos formulários (contato/carreiras) e no painel.
- SPA e JSON do CMS apenas sob o `ADMIN_PATH`.
- Nenhuma rota `/api` pública. Sanctum fora. CORS de SPA fora.
- Healthcheck interno no nginx (`/healthz`), sem endpoint Laravel `/up` público.

Segurança e proteção avançada

Aplicação

- CSRF nos formulários.
- Sanitização de HTML publicado (allowlist) contra XSS.
- ACL no painel (policies admin/editor).
- Rate limit em login e nos POST públicos.
- Headers de segurança (frame, nosniff, referrer, permissions-policy). CSP no painel.
- Cookie de sessão com nome genérico, HttpOnly, Secure, SameSite=lax.
- Sem fingerprint Laravel no público (sem `laravel-session`, sem X-Powered-By, sem Livewire, páginas de erro no visual do site).
- PHP sem `expose_php`. Nginx sem `server_tokens`, sem listagem de diretório, bloqueio de `.env`/`.git`.
- Health e probes internos não vazam o stack.

Borda (Cloudflare)

- WAF, proteção contra bots e DDoS.
- CDN.
- SSL/TLS Full (Strict).
- Cloudflare Access no path do painel (camada extra, infra).

Objetivos do projeto

- Site institucional responsivo, SEO e com aparência de site estático.
- CMS Laravel (SPA) para a equipe atualizar conteúdo sem deploy.
- Superfície de ataque mínima: sem API pública, admin oculto.
- Cloudflare para segurança, performance e disponibilidade.

Tecnologias utilizadas

- Backend: Laravel 13 (PHP 8.3+), um único app.
- Frontend público: Blade + CSS/JS estáticos (não Bootstrap).
- CMS: SPA React (sessão + CSRF). JSON em `/{ADMIN_PATH}/api`.
- Banco: MySQL.
- Cache/fila: Redis.
- Hospedagem: EasyPanel (container da API + MySQL + Redis).
- Segurança: Cloudflare + HTTPS + ACL. Sem Sanctum.
- Mídia: upload em disco público com path `/storage/...` (não Spatie Media Library nos models).
- Permissões: Spatie Permission (roles admin/editor).

Infraestrutura de segurança

- Firewall Cloudflare (DDoS e WAF).
- Rate limiting (brute force e spam de formulário).
- Proteção SQL Injection / XSS no Laravel.
- Backup automático: snapshots do banco e do volume de storage.
- Formulários: CSRF + throttle. Captcha (Turnstile/reCAPTCHA) fica para ciclo seguinte.

Entregáveis

- Setup de homologação (EasyPanel, um container Laravel).
- Repositório GIT.
- CMS SPA no Laravel (sem API pública; JSON só no path secreto).
- Conteúdo inicial das 8 páginas, equipe, filiais e SEO.
- Hardening: stealth do stack, admin oculto, API fechada.
- Documentação do sistema:
  - Tutorial de uso do CMS (path secreto, edição de páginas).
  - Documentação técnica: arquitetura monolito Laravel, módulos do painel, deploy, variáveis (`ADMIN_PATH`, sessão, banco).
- Treinamento da equipe.
- Plano de testes (site público, ACL, 404 de probes, formulários).
- Otimização SEO e performance.
- Instalação e publicação.
- Analytics e marketing via tags no CMS (admin).
