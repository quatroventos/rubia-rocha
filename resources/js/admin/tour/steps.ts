import type { Abilities } from '../lib';
import type { TourId, TourStep } from './types';

function step(
  target: string,
  title: string,
  body: string,
  extra: Partial<Omit<TourStep, 'target' | 'title' | 'body'>> = {},
): TourStep {
  return { target, title, body, ...extra };
}

function crudBase(
  noun: string,
  addLabel: string,
  listNoun?: string,
  gender: 'm' | 'f' = 'm',
): TourStep[] {
  const list = listNoun ?? `${noun}s`;
  const article = gender === 'f' ? 'uma nova' : 'um novo';
  const registered = gender === 'f' ? 'cadastradas' : 'cadastrados';

  return [
    step('crud-add', addLabel, `Use este botão para cadastrar ${article} ${noun}.`),
    step('crud-search', 'Busca', 'Digite para encontrar o que procura na lista.'),
    step('crud-table', 'Lista', `Aqui ficam ${list} já ${registered}. O ícone de lápis abre a edição.`),
    step('crud-edit', 'Editar', 'Abre os dados para você alterar o que precisar.'),
    step('crud-delete', 'Excluir', 'Move o item para a lixeira.', { optional: true }),
  ];
}

export function stepsFor(tourId: TourId, abilities?: Abilities): TourStep[] {
  if (tourId === 'shell') {
    let configBody = 'Aqui ficam o nome do escritório, as configurações de busca, a montagem do menu e os selos.';
    if (abilities?.form_options) {
      configBody = 'Aqui ficam o nome do escritório, as configurações de busca, a montagem do menu, os selos e os formulários do site.';
    }

    let systemBody = 'Acesso de pessoas ao painel, atualização do site, otimização de imagens, backups, tags de acompanhamento e o seu perfil.';
    if (abilities?.audit) {
      systemBody = 'Acesso de pessoas ao painel, histórico de ações, atualização do site, otimização de imagens, backups, tags de acompanhamento e o seu perfil.';
    }

    return [
      step('shell-sidebar', 'Menu', 'Este menu leva você a todas as áreas do painel.', { eyebrow: 'Painel', placement: 'right' }),
      step('shell-nav-operacao', 'Operação', 'Acompanhe as visitas ao site e as mensagens recebidas pelo contato.', { placement: 'right' }),
      step('shell-nav-conteudo', 'Conteúdo', 'Monte páginas com seções e edite os cases: textos, imagens e informações que o visitante vê.', { placement: 'right' }),
      step('shell-nav-config', 'Configuração', configBody, { placement: 'right' }),
      step('shell-nav-sistema', 'Sistema', systemBody, { placement: 'right' }),
      step('shell-title', 'Título da tela', 'Mostra em qual área você está no momento.'),
      step('shell-help', 'Ajuda', 'Se quiser repetir esta apresentação depois, use este botão.'),
      step('shell-user', 'Sua conta', 'Aqui você acessa o perfil e sai do painel.', {
        prepare: 'shell.openUserMenu',
        cleanup: 'shell.closeUserMenu',
      }),
    ];
  }

  if (tourId === 'dashboard') {
    return [
      step('dash-range', 'Período', 'Escolha o intervalo dos números e do gráfico: hoje, semana, mês e outras opções.'),
      step('dash-live', 'Online agora', 'Quantas pessoas estão no site neste momento.'),
      step('dash-load', 'Tempo de carregamento', 'Indica se as páginas estão abrindo com rapidez.'),
      step('dash-month', 'Visitas do mês', 'Total de visitas neste mês.'),
      step('dash-health', 'Saúde do site', 'Notas de desempenho no celular e no computador. Você pode pedir uma nova medição quando quiser.'),
      step('dash-chart', 'Acessos por dia', 'Mostra os dias com mais e com menos visitas.'),
      step('dash-pages', 'Páginas mais acessadas', 'Quais páginas as pessoas mais abriram no período.'),
      step('dash-duration', 'Tempo médio', 'Quanto tempo, em média, alguém permanece no site.'),
      step('dash-depth', 'Profundidade', 'Se a visita ficou em uma página ou navegou por várias.'),
      step('dash-origins', 'Origens', 'De onde veio a visita: busca, endereço digitado ou outro site.'),
      step('dash-map', 'Locais', 'Cidades de onde as visitas estão chegando.'),
    ];
  }

  if (tourId === 'inbox') {
    const steps: TourStep[] = [
      step('inbox-tabs', 'Abas', 'Filtre as mensagens recebidas pelo Fale Conosco.'),
      step('inbox-list', 'Lista', 'Clique em um nome para ler a mensagem completa ao lado.'),
      step('inbox-detail', 'Detalhe', 'Mostra tudo o que a pessoa preencheu no formulário do site.'),
      step('inbox-download', 'Currículo', 'Quando for um currículo, você baixa o arquivo por aqui.', {
        optional: true,
        prepare: 'inbox.openCareers',
        cleanup: 'inbox.openMessages',
      }),
    ];

    if (abilities?.messages_delete || abilities?.careers_delete) {
      steps.push(step('inbox-delete', 'Excluir', 'Move o registro para a lixeira, com confirmação. Dá para recuperar depois.', { optional: true }));
    }

    return steps;
  }

  if (tourId === 'pages') {
    return [
      step('pages-add', 'Nova página', 'Crie uma página em branco. Depois você monta o conteúdo com as seções da biblioteca.'),
      step('pages-modal', 'Dados da página', 'Informe o título e o endereço (slug). Ao criar, o editor abre na hora.', {
        prepare: 'pages.openCreate',
        cleanup: 'pages.closeCreate',
      }),
      step('pages-table', 'Lista', 'Todas as páginas do site. A home não pode ser excluída.'),
      step('pages-edit', 'Abrir editor', 'O lápis abre a página para montar e editar as seções.'),
      step('pages-delete', 'Excluir', 'Remove a página. Links do menu que apontam para ela precisam ser ajustados à parte.', { optional: true }),
    ];
  }

  if (tourId === 'page-editor') {
    return [
      step('editor-outline', 'Seções', 'A página é uma lista de blocos. Clique para editar, arraste pelo ícone de pontos para reordenar e use a lixeira para remover.', { placement: 'right' }),
      step('editor-add-section', 'Adicionar seção', 'Permite adicionar novas seções à página.', { placement: 'right' }),
      step('editor-library', 'Biblioteca', 'Busque pelo nome ou escolha por tipo: abertura, cards, texto e imagem, listas e outros. Clique para adicionar à página.', {
        prepare: 'page-editor.openLibrary',
        cleanup: 'page-editor.closeLibrary',
        placement: 'right',
      }),
      step('editor-canvas', 'Conteúdo', 'Aqui você escreve, troca fotos e organiza listas da seção selecionada. As mudanças só vão ao site quando você salvar.'),
      step('editor-save', 'Atualizar', 'Quando houver alteração, use este botão para gravar e publicar no site.'),
      step('editor-seo', 'Como aparece na busca', 'Título e texto curto que o Google e as redes podem mostrar. Se deixar em branco, o site usa o padrão geral.'),
      step('editor-versions', 'Versões', 'Se algo não ficar como você queria, dá para voltar a uma versão anterior.', {
        prepare: 'page-editor.openVersions',
        cleanup: 'page-editor.closeVersions',
        placement: 'left',
      }),
    ];
  }

  if (tourId === 'menu') {
    return [
      step('menu-tabs', 'Abas do menu', 'Menu do topo e os dois grupos de links do rodapé. O número mostra quantos itens cada um tem.'),
      step('menu-pages', 'Páginas do site', 'Marque as páginas e use Adicionar ao menu. Páginas já usadas nesta aba aparecem como "No menu".'),
      step('menu-custom', 'Link personalizado', 'Para URLs externas ou endereços que não estão na lista de páginas.'),
      step('menu-structure', 'Estrutura', 'Arraste pela alça para reordenar. Puxe um item um pouco para a direita para virar submenu (um nível). O olho oculta ou mostra o link. Nada disso vai ao site até você salvar.'),
      step('menu-edit', 'Abrir item', 'Expande o item para editar texto, destino, visibilidade e mover com botões. Use Aplicar para gravar no rascunho.'),
      step('menu-modal', 'Ficha do item', 'Ajuste o texto, o destino (/slug para página interna) e use Mover para subir, descer ou virar submenu.', {
        prepare: 'menu.openEdit',
        cleanup: 'menu.close',
        optional: true,
      }),
      step('menu-save', 'Salvar menu', 'Publica no site todas as mudanças desta aba. Sem este passo, o visitante continua vendo o menu antigo.', {
        optional: true,
      }),
    ];
  }

  if (tourId === 'certifications') {
    return [
      ...crudBase('selo', 'Adicionar selo', 'os selos'),
      step('crud-modal', 'Selo do rodapé', 'Imagem do selo, link (se houver) ou um espaço reservado quando ainda não houver imagem.', {
        prepare: 'crud.openCreate',
        cleanup: 'crud.close',
      }),
      step('crud-field-type', 'Tipo', 'Selo com imagem ou apenas o espaço reservado.', {
        prepare: 'crud.openCreate',
        cleanup: 'crud.close',
        optional: true,
      }),
      step('crud-modal-save', 'Salvar', 'Salve para o selo aparecer no rodapé do site.', {
        prepare: 'crud.openCreate',
        cleanup: 'crud.close',
        optional: true,
      }),
    ];
  }

  if (tourId === 'users') {
    return [
      ...crudBase('usuário', 'Adicionar usuário', 'os usuários'),
      step('users-impersonate', 'Acessar como', 'Entra no painel com a visão dessa pessoa. No topo aparece um aviso para você voltar quando quiser.', { optional: true }),
      step('crud-modal', 'Ficha de acesso', 'Nome, e-mail, senha e o tipo de acesso desta conta.', {
        prepare: 'crud.openCreate',
        cleanup: 'crud.close',
      }),
      step('crud-field-role', 'Papel', 'Define o tipo de acesso no painel.', {
        prepare: 'crud.openCreate',
        cleanup: 'crud.close',
        optional: true,
      }),
      step('crud-field-password', 'Senha', 'Obrigatória no cadastro. Na edição, deixe em branco se não for alterar.', {
        prepare: 'crud.openCreate',
        cleanup: 'crud.close',
        optional: true,
      }),
      step('crud-modal-save', 'Salvar', 'Salve a conta. Você não pode excluir a si mesmo.', {
        prepare: 'crud.openCreate',
        cleanup: 'crud.close',
        optional: true,
      }),
    ];
  }

  if (tourId === 'site') {
    return [
      step('site-identity', 'Identidade', 'Nome do escritório e a frase curta da marca.'),
      step('site-logo', 'Logo e favicon', 'Logo do topo e o ícone da aba do navegador. Envie a imagem; remova se quiser trocar depois.'),
      step('site-contacts', 'Contatos', 'Telefone, e-mail, WhatsApp, endereço e mapa. O que estiver vazio não aparece no site.'),
      step('site-social', 'Redes', 'Links do Instagram, LinkedIn e Facebook. Sem link, o ícone não é exibido.'),
      step('site-smtp', 'SMTP', 'Servidor de e-mail do site. Ative e preencha host, porta e remetente para os formulários enviarem por aqui.'),
      step('site-save', 'Salvar', 'Salve para aplicar as alterações no site.'),
    ];
  }

  if (tourId === 'seo') {
    const settings = { prepare: 'seo.showSettings' };
    return [
      step('seo-tabs', 'Desempenho, configuração e integrações', 'Desempenho mostra os dados do OpenSEO. Configuração guarda o sitemap e o padrão de títulos. Integrações reúne chaves, endereços e prompts da IA.', { prepare: 'seo.showPerformance' }),
      step('seo-openseo-nav', 'Blocos de desempenho', 'Auditoria, Search Console, palavras-chave, indexação e IA. Consultas pagas só aparecem se liberadas em Integrações e sempre pedem confirmação com o custo.', { prepare: 'seo.showPerformance', optional: true }),
      step('seo-sitemap', 'Mapa do site', 'Lista das páginas publicadas no sitemap.', settings),
      step('seo-copy', 'Copiar link', 'Copia o endereço para você colar no Search Console ou onde precisar.', settings),
      step('seo-generate', 'Atualizar lista', 'Atualiza a lista com as páginas publicadas e os endereços extras.', settings),
      step('seo-extra', 'URLs extras', 'Um endereço por linha, se alguma página importante não estiver na lista automática.', settings),
      step('seo-defaults', 'Padrão das páginas', 'Título e descrição usados quando a página ainda não tem os seus próprios.', settings),
      step('seo-fill', 'Preencher vazios', 'Se marcar ao salvar, completa as páginas que ainda estão sem título ou descrição.', settings),
      step('seo-save', 'Salvar', 'Salve o padrão. Páginas que já têm texto próprio continuam com o que você escreveu nelas.', settings),
      step('seo-preview', 'Prévia no Google', 'Uma ideia de como o resultado pode aparecer na busca. Troque a página no seletor para conferir.', settings),
    ];
  }

  if (tourId === 'forms') {
    return [
      step('forms-search', 'Busca', 'Encontre o formulário de contato.'),
      step('forms-table', 'Formulários', 'Abra um formulário para montar os campos que o visitante preenche no site.'),
    ];
  }

  if (tourId === 'form-editor') {
    return [
      step('form-submit', 'Texto do botão', 'O texto do botão de envio no final do formulário.'),
      step('form-lgpd', 'Texto LGPD', 'O aceite de privacidade. A pessoa precisa marcar para conseguir enviar.'),
      step('form-section', 'Seção', 'Agrupa campos para o formulário ficar mais fácil de ler.'),
      step('form-field', 'Campo', 'Clique para ver na prévia. As setas mudam a ordem.'),
      step('form-type', 'Tipo', 'Escolha o formato da pergunta: texto, e-mail, telefone, mensagem longa, lista de opções e outros.'),
      step('form-required', 'Obrigatório', 'Se estiver marcado, o envio só acontece com o campo preenchido. Alguns já vêm assim.'),
      step('form-add-field', 'Adicionar campo', 'Adiciona novas opções ao campo do formulário.'),
      step('form-preview', 'Prévia', 'Mostra como o formulário fica no site. Clique em um campo para localizá-lo à esquerda. Esta prévia não envia dados.'),
      step('form-save', 'Salvar', 'Salve quando estiver pronto. Cancelar volta à lista sem gravar.'),
    ];
  }

  if (tourId === 'cache') {
    return [
      step('cache-stats', 'Situação', 'Resumo da memória rápida do site e de quando ela foi limpa pela última vez.'),
      step('cache-ttl', 'Tempo de validade', 'Por quanto tempo o site guarda algumas informações para abrir mais rápido.'),
      step('cache-flush', 'Limpar', 'Se uma alteração ainda não apareceu no site, limpar aqui costuma resolver.'),
      step('cache-warm', 'Recarregar', 'Limpa e já prepara de novo, para a próxima visita encontrar tudo pronto.'),
      step('cache-keys', 'O que está pronto', 'O que já está na memória e o que ainda será montado na próxima visita.'),
    ];
  }

  if (tourId === 'images') {
    return [
      step('images-stats', 'Resumo', 'Quantidade de imagens, quantas já foram otimizadas, erros e quanto espaço foi economizado.'),
      step('images-options', 'Opções', 'Ajuste a qualidade do WebP e se as imagens muito largas devem ser reduzidas.', {
        prepare: 'images.openSettings',
        cleanup: 'images.closeSettings',
      }),
      step('images-optimize', 'Otimizar', 'Gera cópias WebP. Os originais ficam guardados. Com itens marcados, otimiza só a seleção.'),
      step('images-revert', 'Reverter tudo', 'Remove as cópias WebP e o site volta a usar os originais.', { optional: true }),
      step('images-errors', 'Log de erros', 'Se algo falhar, o root vê o motivo aqui. A falha também entra no Log de ações.', { optional: true }),
      step('images-table', 'Biblioteca', 'Lista cada arquivo: peso original, WebP e economia. Dá para otimizar ou reverter um por um.'),
    ];
  }

  if (tourId === 'backup') {
    return [
      step('backup-manual', 'Backup agora', 'Gera uma cópia de segurança agora, além da cópia automática do dia.'),
      step('backup-config', 'Agendamento', 'Defina a hora do backup diário e por quantos dias as cópias ficam guardadas.', {
        prepare: 'backup.openSettings',
        cleanup: 'backup.closeSettings',
      }),
      step('backup-table', 'Histórico', 'As cópias já feitas: data, tamanho e se concluíram com sucesso.'),
      step('backup-download', 'Download', 'Baixa a cópia para o seu computador.', { optional: true }),
      step('backup-restore', 'Restaurar', 'Volta o site para o estado daquela cópia. É uma ação importante: o painel pede confirmação escrita.', { optional: true }),
    ];
  }

  if (tourId === 'audit') {
    return [
      step('audit-search', 'Busca', 'Filtre por pessoa, ação ou origem do acesso.'),
      step('audit-table', 'Registro', 'Histórico do que foi feito no painel: quem fez e o quê.'),
      step('audit-pager', 'Páginas', 'Se a lista for longa, avance pelas páginas aqui.', { optional: true }),
    ];
  }

  if (tourId === 'tags') {
    return [
      step('tags-header', 'Cabeçalho', 'Códigos de acompanhamento no topo de todas as páginas (pixel, Search Console e similares).'),
      step('tags-body', 'Início da página', 'Scripts que entram no começo da página.'),
      step('tags-footer', 'Final da página', 'Scripts do final da página, como analytics.'),
      step('tags-save', 'Salvar', 'Salve os códigos. Vale conferir com atenção: um trecho errado afeta o site inteiro.'),
    ];
  }

  return [
    step('profile-name', 'Nome', 'Como o seu nome aparece no painel.'),
    step('profile-password', 'Nova senha', 'Preencha só se quiser trocar. Se esquecer a senha, peça ajuda em Usuários.'),
    step('profile-save', 'Salvar', 'Salve as alterações. O e-mail desta conta não muda nesta tela.'),
  ];
}
