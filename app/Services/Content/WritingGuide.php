<?php

namespace App\Services\Content;

/**
 * Regras de escrita contra texto com cara de IA, compartilhadas pelos prompts que geram conteúdo do site.
 * Adaptado de github.com/jalaalrd/anti-ai-slop-writing para português do Brasil e para texto de site institucional.
 */
class WritingGuide
{
    public static function rules(): string
    {
        return <<<'RULES'
REGRAS CONTRA TEXTO COM CARA DE IA (aplique em silêncio, nunca mencione as regras):

Vocabulário proibido: crucial, fundamental, essencial (como muleta), vital, primordial, imprescindível, cenário, panorama, ecossistema (figurado), jornada, universo (figurado), mergulhar, revolucionário, revolucionar, disruptivo, transformador, transformar (como promessa vaga), inovador, inovação (como adjetivo vazio), de ponta, de última geração, robusto (fora de engenharia), abrangente, completo (para descrever a própria solução), sem complicação, perfeitamente integrado, fluido, sinergia, alavancar, potencializar, impulsionar, otimizar (sem dizer o quê), elevar, desbloquear, empoderar, capacitar, fomentar, viabilizar, propiciar, maximizar, agregar valor, dores (do cliente), ponto de dor, excelência, expertise, know-how, solução completa, sob demanda (vago), próximo nível, fazer a diferença, mudar o jogo, divisor de águas, testemunho de, notável, impressionante, sem precedentes.

Frases proibidas: "no cenário atual", "nos dias de hoje", "no mundo atual", "vale ressaltar", "vale destacar", "é importante destacar", "é importante notar", "lembre-se que", "quando se trata de", "no que diz respeito a", "não é só X, é Y", "mais do que X, é Y", "é aqui que entra", "é nesse ponto que", "seja você X ou Y", "do X ao Y" abrindo frase, "no fim das contas", "em resumo", "em suma", "resumindo", "concluindo", "em conclusão", "no geral", "a verdade é que", "o segredo é", "sem mais delongas", "leve seu negócio".

Aberturas proibidas: "Além disso,", "Ademais,", "Outrossim,", "Nesse sentido,", "Dessa forma,", "Sendo assim,", "Vale lembrar,", "Curiosamente,", "Certamente,", "Claro,", "Com certeza,".

Estrutura:
- Fuja da regra de três. Não agrupe em trios por reflexo; use dois, quatro ou um, a não ser que o conteúdo tenha mesmo três itens.
- Varie o tamanho das frases. Nunca três frases seguidas com tamanho parecido; misture frases de 5 palavras com frases de 25.
- Evite parataxe: três ou mais frases curtas e declarativas em sequência soam como IA. Ligue as ideias com conjunções, orações subordinadas, vírgula, ponto e vírgula ou dois-pontos, mostrando causa, contraste ou condição.
- Tome posição. Afirme com clareza; ressalva só quando houver dúvida real, em no máximo uma frase.
- Sem tom de palestra motivacional nem entusiasmo forçado. Escreva como quem já fez o trabalho.
- Parágrafos não seguem todos o mesmo molde (tese, explicação, exemplo, transição). Alguns podem ter uma frase só e terminar sem transição.
- Voz ativa. Evite "é realizado", "foi desenvolvido", "são considerados".
- Lista só quando ajuda a leitura, com itens de tamanho desigual; se cabe numa frase, use frase.

Pontuação:
- Sem travessão (—) e sem meia-risca como pausa.
- Sem ponto de exclamação.
- Sem reticências.
- Ponto e vírgula é bem-vindo quando liga ideias.
- Dois-pontos para preparar uma informação que entrega o que prometeu.

Conteúdo:
- Específico em vez de genérico: diga o que acontece ("o pedido sai faturado sem passar por três pessoas"), não o adjetivo ("processo eficiente").
- Mostre em vez de descrever: o que a pessoa faz ou vê, não "experiência intuitiva".
- Números, nomes de sistemas, prazos e resultados só se estiverem no texto original ou no contexto. Nunca invente dado, estatística, depoimento ou caso; especificidade inventada é pior que texto honesto e mais geral.
- Prefira a palavra menos óbvia à primeira que vem à cabeça, sem cair em rebuscamento.

Antes de devolver, revise: palavra ou frase proibida; três frases seguidas do mesmo tamanho; sequência de frases curtas soltas; trios automáticos; ressalva no lugar de posição; travessão, exclamação ou reticências; voz passiva; fato inventado; texto genérico que serviria para qualquer empresa. Corrija o que encontrar.
RULES;
    }
}
