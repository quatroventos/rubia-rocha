export type LogLine = Array<[string, string?]>;

const PT: LogLine[] = [
  [['trigger', 'k'], ['  pedido.recebido          '], ['"PV-20931"', 's'], ['   cliente: '], ['"B2B-0417"', 's']],
  [['agent', 'k'], ['    conferencia.itens        '], ['llm.extract', 'p'], ['(nota_fiscal.pdf) → 42 itens']],
  [['check', 'k'], ['    estoque.erp              '], ['ok', 's'], ['   divergência: 0']],
  [['agent', 'k'], ['    aprovacao.credito        '], ['policy.eval', 'p'], ['(limite, histórico) → '], ['aprovado', 's']],
  [['action', 'k'], ['   emissao.nfe              '], ['"NF 118.402"', 's'], ['  enviada ao cliente']],
  [['notify', 'k'], ['   whatsapp.cliente         '], ['"Pedido confirmado. Entrega prevista."', 's']],
  [['trigger', 'k'], ['  chamado.aberto           '], ['"#4471"', 's'], ['   canal: whatsapp']],
  [['agent', 'k'], ['    triagem.atendimento      '], ['rag.search', 'p'], ['(base_conhecimento) → 3 fontes']],
  [['action', 'k'], ['   resposta.enviada         confiança 0.93   humano: não necessário']],
  [['trigger', 'k'], ['  boleto.vencido           '], ['"C-8812"', 's']],
  [['agent', 'k'], ['    cobranca.negociacao      '], ['llm.draft', 'p'], ['(histórico, régua) → proposta']],
  [['action', 'k'], ['   crm.atualizado           etapa: negociação']],
  [['trigger', 'k'], ['  pedido.recebido          '], ['"PV-20932"', 's'], ['   cliente: '], ['"B2B-0932"', 's']],
  [['agent', 'k'], ['    conferencia.itens        '], ['llm.extract', 'p'], ['(pedido.xlsx) → 18 itens']],
  [['check', 'k'], ['    estoque.erp              '], ['ok', 's'], ['   divergência: 1  → revisão humana']],
  [['trigger', 'k'], ['  relatorio.mensal         '], ['sql.query', 'p'], ['(vendas, margem) → resumo']],
  [['agent', 'k'], ['    analise.resultados       '], ['llm.summarize', 'p'], [' → diretoria']],
];

const EN: LogLine[] = [
  [['trigger', 'k'], ['  order.received           '], ['"PO-20931"', 's'], ['   customer: '], ['"B2B-0417"', 's']],
  [['agent', 'k'], ['    items.check              '], ['llm.extract', 'p'], ['(invoice.pdf) → 42 items']],
  [['check', 'k'], ['    inventory.erp            '], ['ok', 's'], ['   mismatch: 0']],
  [['agent', 'k'], ['    credit.approval          '], ['policy.eval', 'p'], ['(limit, history) → '], ['approved', 's']],
  [['action', 'k'], ['   invoice.issue            '], ['"INV 118.402"', 's'], ['  sent to customer']],
  [['notify', 'k'], ['   whatsapp.customer        '], ['"Order confirmed. Delivery scheduled."', 's']],
  [['trigger', 'k'], ['  ticket.opened            '], ['"#4471"', 's'], ['   channel: whatsapp']],
  [['agent', 'k'], ['    support.triage           '], ['rag.search', 'p'], ['(knowledge_base) → 3 sources']],
  [['action', 'k'], ['   reply.sent               confidence 0.93   human: not needed']],
  [['trigger', 'k'], ['  invoice.overdue          '], ['"C-8812"', 's']],
  [['agent', 'k'], ['    collections.negotiation  '], ['llm.draft', 'p'], ['(history, policy) → offer']],
  [['action', 'k'], ['   crm.updated              stage: negotiation']],
  [['trigger', 'k'], ['  order.received           '], ['"PO-20932"', 's'], ['   customer: '], ['"B2B-0932"', 's']],
  [['agent', 'k'], ['    items.check              '], ['llm.extract', 'p'], ['(order.xlsx) → 18 items']],
  [['check', 'k'], ['    inventory.erp            '], ['ok', 's'], ['   mismatch: 1  → human review']],
  [['trigger', 'k'], ['  report.monthly           '], ['sql.query', 'p'], ['(sales, margin) → summary']],
  [['agent', 'k'], ['    results.analysis         '], ['llm.summarize', 'p'], [' → board']],
];

const ES: LogLine[] = [
  [['trigger', 'k'], ['  pedido.recibido          '], ['"PV-20931"', 's'], ['   cliente: '], ['"B2B-0417"', 's']],
  [['agent', 'k'], ['    control.items            '], ['llm.extract', 'p'], ['(factura.pdf) → 42 ítems']],
  [['check', 'k'], ['    inventario.erp           '], ['ok', 's'], ['   diferencia: 0']],
  [['agent', 'k'], ['    aprobacion.credito       '], ['policy.eval', 'p'], ['(límite, historial) → '], ['aprobado', 's']],
  [['action', 'k'], ['   emision.factura          '], ['"FC 118.402"', 's'], ['  enviada al cliente']],
  [['notify', 'k'], ['   whatsapp.cliente         '], ['"Pedido confirmado. Entrega programada."', 's']],
  [['trigger', 'k'], ['  ticket.abierto           '], ['"#4471"', 's'], ['   canal: whatsapp']],
  [['agent', 'k'], ['    triaje.atencion          '], ['rag.search', 'p'], ['(base_conocimiento) → 3 fuentes']],
  [['action', 'k'], ['   respuesta.enviada        confianza 0.93   humano: no necesario']],
  [['trigger', 'k'], ['  factura.vencida          '], ['"C-8812"', 's']],
  [['agent', 'k'], ['    cobranza.negociacion     '], ['llm.draft', 'p'], ['(historial, política) → propuesta']],
  [['action', 'k'], ['   crm.actualizado          etapa: negociación']],
  [['trigger', 'k'], ['  pedido.recibido          '], ['"PV-20932"', 's'], ['   cliente: '], ['"B2B-0932"', 's']],
  [['agent', 'k'], ['    control.items            '], ['llm.extract', 'p'], ['(pedido.xlsx) → 18 ítems']],
  [['check', 'k'], ['    inventario.erp           '], ['ok', 's'], ['   diferencia: 1  → revisión humana']],
  [['trigger', 'k'], ['  informe.mensual          '], ['sql.query', 'p'], ['(ventas, margen) → resumen']],
  [['agent', 'k'], ['    analisis.resultados      '], ['llm.summarize', 'p'], [' → dirección']],
];

export const LOGS: Record<string, LogLine[]> = { 'pt-BR': PT, en: EN, es: ES };
