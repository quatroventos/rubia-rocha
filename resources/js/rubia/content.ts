import contentFile from './content.json';

export type ContentBlock =
  | { type: 'p'; text: string; label?: string }
  | { type: 'list'; items: string[] };

export type ContentSection = { heading: string | null; level: number; blocks: ContentBlock[] };

export type FaqItem = { q: string; a: string };

export type ContentPage = {
  group: 'ensaios' | 'eventos';
  slug: string | null;
  album: string | null;
  title: string;
  lede: string | null;
  sections: ContentSection[];
  faq: FaqItem[];
};

export const CONTENT = contentFile as ContentPage[];

export function contentPage(group: string, slug: string | null): ContentPage | undefined {
  return CONTENT.find((page) => page.group === group && page.slug === slug);
}

export const SUBPAGES: Record<'ensaios' | 'eventos', { slug: string; label: string }[]> = {
  ensaios: [
    { slug: 'gestante', label: 'Ensaio de gestante' },
    { slug: 'bebes', label: 'Acompanhamento de bebês' },
    { slug: 'familia', label: 'Ensaio de família' },
    { slug: 'casal', label: 'Ensaio de casal' },
    { slug: 'feminino', label: 'Ensaio feminino' },
    { slug: 'formatura', label: 'Ensaio de formatura' },
  ],
  eventos: [
    { slug: 'festa-infantil', label: 'Festa infantil e aniversário' },
    { slug: 'mini-wedding', label: 'Mini wedding e bodas' },
    { slug: 'corporativo', label: 'Corporativo e branding' },
  ],
};

export const TESTIMONIALS: { name: string; text: string; photo?: boolean }[] = [
  {
    name: 'Nathalia Pratt',
    text: 'A Ru é uma fotógrafa incrível, registrou um dos momentos mais especiais da minha vida. Todas as fotos ficaram lindas, fora que ela é muito querida, atenciosa em todos os detalhes. Recomendo tanto, que vou fazer mais fotos com ela.',
    photo: true,
  },
  {
    name: 'Fernanda (mãe do Chico)',
    text: 'Rubia é uma profissional sensacional. Deixa a criança e nós muito à vontade. Suas fotos registram momentos realistas, com olhar do natural e simples, mas que encantam a todos. Super recomendo!',
  },
  {
    name: 'Ana & Marquinho',
    text: 'A Rubia é uma excelente profissional. Fotografa todos os momentos da nossa família e é sempre muito divertido. Meus filhos amam fazer ensaios com ela. Mais do que recomendo.',
  },
  {
    name: 'Elisângela',
    text: 'Contratamos a Rúbia para um ensaio fotográfico familiar e amamos o resultado! Profissional e atenta, ela capturou momentos espontâneos, especialmente das crianças, em um clima descontraído. As fotos ficaram incríveis, com luz perfeita e um olhar artístico único. Recomendamos seu talento e mal podemos esperar por novas sessões. Obrigado, Rúbia, por eternizar nossa família com tanta sensibilidade!',
  },
  {
    name: 'Lela Baesso',
    text: 'A Ru é uma fofa! Adorei o trabalho que ela fez para mim e minha marca, super profissional e muito querida! Atenciosa em cada detalhe!',
  },
];
