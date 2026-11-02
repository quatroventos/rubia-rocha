import { useEffect, useRef, useState, type FormEvent, type PointerEvent, type ReactNode } from 'react';
import albumsFile from './albums.json';
import { BASE, Button, Kicker } from './chrome';

const VENUE = 'Escolher o local do mini wedding é uma das decisões mais importantes do planejamento. O espaço não define apenas o cenário da cerimônia: ele interfere no conforto dos convidados, na circulação dos fornecedores, no cronograma, na iluminação e até na forma como a história do casamento será registrada.';

const REGION = 'Em Curitiba e região, existem espaços lindos para casamentos pequenos, desde restaurantes e casas históricas até chácaras, vinícolas e locais cercados pela natureza. Mas um lugar bonito nas fotos nem sempre é o lugar mais adequado para o seu casamento.';

const SHOT_SIZES = '(max-width: 560px) calc(100vw - 32px), (max-width: 800px) 46vw, 330px';

type AlbumPhoto = { file: string; width: number; height: number };
type Album = { slug: string; title: string; group: 'ensaios' | 'eventos'; photos: AlbumPhoto[] };

const ALBUMS = albumsFile as Album[];

function albumSrc(slug: string, file: string): string {
  return `/rubia/img/albuns/${slug}/${file}`;
}

function albumSrcSet(slug: string, photo: AlbumPhoto): string | undefined {
  if (photo.width <= 960) {
    return undefined;
  }

  const thumb = photo.file.replace(/\.webp$/, '-960.webp');

  return `${albumSrc(slug, thumb)} 960w, ${albumSrc(slug, photo.file)} ${photo.width}w`;
}

function heroPhoto(slug: string, file: string, alt: string, width: number, height: number) {
  const photo = { file, width, height };

  return {
    src: albumSrc(slug, file),
    srcSet: albumSrcSet(slug, photo),
    alt,
    width,
    height,
  };
}

const HERO = [
  heroPhoto('festa-infantil', '54-fotografa-festa-infantil-curitiba1.webp', 'Mãe e filha em festa infantil', 1920, 1280),
  heroPhoto('casal', '05-ensaio-de-casal-curitiba2.webp', 'Casal em ensaio no jardim', 1783, 2560),
  heroPhoto('mini-wedding', '27-fotografia-de-casamento-curitiba-por-do-sol8.webp', 'Noiva em mini wedding', 1707, 2560),
  heroPhoto('gestante', '13-fotografia-curitiba-ensaio-de-gestante-ata-felipe25.webp', 'Ensaio de gestante', 1200, 1900),
  heroPhoto('mini-wedding', '05-mini-wedding-curitiba-capela-nossa-senhora-salette5.webp', 'Abraço na cerimônia', 1707, 2560),
];

function coverPhoto(album: Album): AlbumPhoto {
  return album.photos.find((photo) => photo.width >= photo.height) ?? album.photos[0];
}

type BlogPost = { src: string; cat: string; wide: boolean; width: number; height: number };

const POST_COLUMNS: BlogPost[][] = [
  [
    { src: '/rubia/img/blog-1.jpg', cat: 'Ensaios', wide: false, width: 981, height: 1400 },
    { src: '/rubia/img/blog-4.jpg', cat: 'Festa infantil', wide: true, width: 1400, height: 795 },
  ],
  [
    { src: '/rubia/img/blog-2.jpg', cat: 'Casamentos', wide: true, width: 1400, height: 798 },
    { src: '/rubia/img/blog-3.jpg', cat: 'Casamentos', wide: false, width: 980, height: 1400 },
  ],
];

const POST_HREF = `${BASE}/blog/checklist-mini-wedding-curitiba`;
const POST_TITLE = 'O casamento civil de Shante e Denise no cartório do Portão.';

export function pageBody(path: string): ReactNode | null {
  if (path === '') {
    return <Home />;
  }
  if (path === 'quem-sou') {
    return <About />;
  }
  if (path === 'ensaios') {
    return <Ensaios />;
  }
  if (path === 'eventos') {
    return <Eventos />;
  }
  if (path === 'blog') {
    return <Blog />;
  }
  if (path === 'blog/checklist-mini-wedding-curitiba') {
    return <Post />;
  }
  if (path === 'contato') {
    return <Contact />;
  }

  return null;
}

function Home() {
  return (
    <>
      <HeroCarousel />
      <section className="intro">
        <img
          className="portrait"
          src="/rubia/img/retrato-320.webp"
          srcSet="/rubia/img/retrato-320.webp 320w, /rubia/img/retrato-448.webp 448w"
          sizes="148px"
          alt="Rubia Rocha com a câmera"
          width="1400"
          height="1400"
        />
        <h1>Oi! Eu sou a Rubia.</h1>
        <p>
          Sou fotógrafa em Curitiba especializada em famílias, gestantes, bebês, casais e eventos com
          afeto. Meu olhar busca o que importa: risadas espontâneas, abraços sinceros, momentos reais.
          Se você procura um ensaio fotográfico em Curitiba leve, natural e cheio de emoção, vou adorar
          registrar sua história com carinho e sensibilidade.
        </p>
        <Button href={`${BASE}/contato`} tone="line">Quero um orçamento</Button>
      </section>
      <section className="sheet">
        <img className="leaf" src="/rubia/img/folha.png" alt="" width="327" height="165" />
        <Kicker>Fotografia da vida real</Kicker>
        <h2>O que eu fotografo?</h2>
        <p>
          Registro histórias reais: a doçura dos mini casamentos, a beleza transformadora da gestante,
          os laços da família e a cumplicidade dos casais. Capturo a alegria das festas infantis e
          aniversários. Minha lente busca não só momentos, mas a verdade por trás deles — autêntica,
          espontânea e cheia de vida.
        </p>
        <div className="shots">
          {ALBUMS.slice(0, 8).map((album) => {
            const photo = coverPhoto(album);

            return (
              <a className="shot" href={`${BASE}/${album.group}#${album.slug}`} key={album.slug}>
                <img
                  src={albumSrc(album.slug, photo.file)}
                  srcSet={albumSrcSet(album.slug, photo)}
                  sizes={SHOT_SIZES}
                  alt=""
                  width={photo.width}
                  height={photo.height}
                />
                <span>
                  <strong>{album.title}</strong>
                  <em>Ver galeria</em>
                </span>
              </a>
            );
          })}
        </div>
        <Button href={`${BASE}/contato`} tone="fill">Fale comigo no Whats</Button>
      </section>
      <section className="quotes" aria-labelledby="depoimentos">
        <Kicker>Depoimentos</Kicker>
        <figure>
          <img
            src="/rubia/img/depoimento-216.webp"
            srcSet="/rubia/img/depoimento-144.webp 144w, /rubia/img/depoimento-216.webp 216w"
            sizes="72px"
            alt=""
            width="1395"
            height="1400"
            loading="lazy"
          />
          <blockquote id="depoimentos">
            <p>
              A Rúbia é uma profissional excelente, conseguiu captar a essência da festa
              da minha pequena e as fotos ficaram lindas!! Será sempre a fotógrafa
              das festas da minha família!
            </p>
          </blockquote>
          <figcaption>Nathalia Pratt</figcaption>
        </figure>
      </section>
    </>
  );
}

const SLIDE_MS = 700;
const SLIDE_STAGGER = 80;

function heroFrame(start: number) {
  return [0, 1, 2].map((slot) => HERO[(start + slot) % HERO.length]);
}

function slidePlacements(index: number, from: number | null, forward: boolean, entering: boolean) {
  const next = heroFrame(index);
  if (from === null) {
    return next.map((photo, slot) => ({ key: photo.src, photo, slot, delay: 0, offstage: false, enter: false }));
  }

  const previous = heroFrame(from);
  const steps = (index - from + HERO.length) % HERO.length;
  const adjacent = steps === 1 || steps === HERO.length - 1;
  const items: { key: string; photo: (typeof HERO)[number]; slot: number; delay: number; offstage: boolean; enter: boolean }[] = [];

  previous.forEach((photo, oldSlot) => {
    const newSlot = next.findIndex((item) => item.src === photo.src);
    const order = (forward ? oldSlot : 2 - oldSlot) * SLIDE_STAGGER;
    const wraps = newSlot !== -1 && !adjacent && (forward ? newSlot > oldSlot : newSlot < oldSlot);
    if (wraps) {
      items.push({ key: `${photo.src}-out`, photo, slot: forward ? -1 : 3, delay: order, offstage: true, enter: false });
      return;
    }
    if (newSlot === -1) {
      items.push({ key: photo.src, photo, slot: forward ? -1 : 3, delay: order, offstage: true, enter: false });
      return;
    }
    items.push({ key: photo.src, photo, slot: newSlot, delay: order, offstage: false, enter: false });
  });

  next.forEach((photo, slot) => {
    if (items.some((item) => item.key === photo.src)) {
      return;
    }
    const order = (forward ? slot : 2 - slot) * SLIDE_STAGGER;
    items.push({
      key: photo.src,
      photo,
      slot: entering ? (forward ? 3 : -1) : slot,
      delay: order,
      offstage: entering,
      enter: entering,
    });
  });

  return items;
}

function prefersReducedMotion(): boolean {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function HeroCarousel() {
  const [index, setIndex] = useState(0);
  const [from, setFrom] = useState<number | null>(null);
  const [forward, setForward] = useState(true);
  const [entering, setEntering] = useState(false);
  const [paused, setPaused] = useState(false);
  const dragX = useRef<number | null>(null);
  const photos = slidePlacements(index, from, forward, entering);

  useEffect(() => {
    if (paused || from !== null || prefersReducedMotion()) {
      return;
    }
    const id = window.setInterval(() => {
      setForward(true);
      setEntering(true);
      setFrom(index);
      setIndex((currentIndex) => (currentIndex + 1) % HERO.length);
    }, 5200);

    return () => window.clearInterval(id);
  }, [paused, from, index]);

  useEffect(() => {
    if (!entering) {
      return;
    }
    const frame = window.requestAnimationFrame(() => setEntering(false));

    return () => window.cancelAnimationFrame(frame);
  }, [entering, index]);

  useEffect(() => {
    if (from === null) {
      return;
    }
    const id = window.setTimeout(() => setFrom(null), SLIDE_MS + SLIDE_STAGGER * 2 + 40);

    return () => window.clearTimeout(id);
  }, [from, index]);

  function show(next: number) {
    const normalized = (next + HERO.length) % HERO.length;
    if (normalized === index) {
      return;
    }
    if (!prefersReducedMotion()) {
      const steps = (normalized - index + HERO.length) % HERO.length;
      setForward(steps <= HERO.length / 2);
      setEntering(true);
      setFrom(index);
    } else {
      setEntering(false);
      setFrom(null);
    }
    setIndex(normalized);
  }

  function onPointerDown(event: PointerEvent<HTMLDivElement>) {
    dragX.current = event.clientX;
    event.currentTarget.setPointerCapture(event.pointerId);
  }

  function onPointerUp(event: PointerEvent<HTMLDivElement>) {
    if (dragX.current === null) {
      return;
    }
    const delta = event.clientX - dragX.current;
    dragX.current = null;
    if (Math.abs(delta) < 48) {
      return;
    }
    show(index + (delta < 0 ? 1 : -1));
  }

  return (
    <section
      className="hero"
      aria-roledescription="carrossel"
      aria-label="Trabalhos recentes"
      onMouseEnter={() => setPaused(true)}
      onMouseLeave={() => setPaused(false)}
      onFocus={() => setPaused(true)}
      onBlur={() => setPaused(false)}
    >
      <div
        className="hero-stage"
        onPointerDown={onPointerDown}
        onPointerUp={onPointerUp}
        onPointerCancel={() => { dragX.current = null; }}
      >
        <div className="hero-sizer" />
        <div className="hero-sizer" />
        <div className="hero-sizer" />
        {photos.map((item) => (
          <img
            key={item.key}
            className={item.enter ? 'hero-photo is-enter' : 'hero-photo'}
            data-slot={item.slot}
            src={item.photo.src}
            srcSet={item.photo.srcSet}
            sizes={item.slot === 1 ? '(max-width: 560px) calc(100vw - 32px), 560px' : '(max-width: 560px) calc(100vw - 32px), 250px'}
            alt={item.offstage ? '' : item.photo.alt}
            width={item.photo.width}
            height={item.photo.height}
            draggable={false}
            aria-hidden={item.offstage || undefined}
            style={{ transitionDelay: `${item.delay}ms` }}
          />
        ))}
      </div>
      <div className="dots" role="tablist" aria-label="Fotos do carrossel">
        {HERO.map((photo, dot) => (
          <button
            key={photo.src}
            type="button"
            role="tab"
            aria-label={photo.alt}
            aria-selected={dot === index}
            onClick={() => show(dot)}
          />
        ))}
      </div>
    </section>
  );
}

function About() {
  return (
    <>
      <section className="trio" aria-label="Famílias">
        <img src="/rubia/img/sobre-1.jpg" alt="Família abraçada" />
        <img src="/rubia/img/sobre-2.jpg" alt="Rubia sorrindo" />
        <img src="/rubia/img/sobre-3.jpg" alt="Crianças em festa" />
      </section>
      <section className="intro">
        <Kicker>Fotógrafa de momentos reais em Curitiba</Kicker>
        <h1>Rubia quem?</h1>
        <p>
          Prazer, sou a Rubia. Fotógrafa em Curitiba e defensora oficial de que a vida real é
          muito mais interessante do que a perfeita. Desde 2019, minha missão é bem simples:
          registrar a conexão entre as pessoas sem que ninguém precise ficar travado dizendo
          “xis”.
        </p>
        <p>
          Esqueça aquela pose durinha. O que eu gosto mesmo é de caçar a baguncinha boa
          dos filhos, a risada solta do casal, a expectativa gigante da espera do bebê na gestação
          e a energia das celebrações. Trabalho com fotografia estilo lifestyle e documental, de
          um jeito leve que faz você olhar para a foto daqui a vinte anos e sentir exatamente o
          que estava acontecendo naquele dia.
        </p>
        <Button href={`${BASE}/contato`} tone="fill">Fale comigo no Whats</Button>
      </section>
      <section className="fact">
        <img src="/rubia/img/sobre-fatos.jpg" alt="Rubia com os filhos" />
        <div>
          <Kicker>Fatos sobre mim</Kicker>
          <h2>#1: Sou mãe de gêmeos</h2>
          <p>
            Eu já trabalhava com fotografia de produto, mas quando meus gêmeos completaram 7 meses,
            minha chavinha virou. Mudei de rumo para fotografar famílias e nunca mais olhei para trás.
          </p>
          <p>
            Hoje, vivo cercada por carrinhos no chão, migalhas de bolacha pela casa e muito caos.
            Foi essa maternidade real, bem longe dos comerciais de margarina, que me ensinou o valor
            de guardar os pequenos instantes antes que eles passem rápido demais. Antes de ser
            fotógrafa, sou mãe, e isso mudou completamente o meu olhar na hora de registrar as outras famílias.
          </p>
        </div>
      </section>
    </>
  );
}

function Folio({ tag, title, lede, photo, photoAlt, group }: { tag: string; title: string; lede: string; photo: string; photoAlt: string; group: Album['group'] }) {
  return (
    <article className="folio">
      <header className="folio-top">
        <div className="folio-lead">
          <p className="folio-tag">{tag}</p>
          <h1>{title}</h1>
          <p className="folio-lede">{lede}</p>
          <Button href={`${BASE}/contato`} tone="fill">Quero um orçamento</Button>
        </div>
        <img className="folio-photo" src={photo} alt={photoAlt} />
      </header>
      <div className="folio-body">
        <p>{VENUE}</p>
        <p>{REGION}</p>
        <Specimen />
      </div>
      <AlbumList group={group} />
    </article>
  );
}

function Ensaios() {
  return (
    <Folio
      tag="Ensaios"
      title="Ensaios fotográficos em Curitiba"
      lede="Momentos que viram memórias do jeitinho que sua família é."
      photo={albumSrc('gestante', '13-fotografia-curitiba-ensaio-de-gestante-ata-felipe25.webp')}
      photoAlt="Ensaio de gestante"
      group="ensaios"
    />
  );
}

function Eventos() {
  return (
    <>
      <Folio
        tag="Mini weddings e bodas"
        title="Fotógrafa Mini Wedding Curitiba"
        lede="Um olhar editorial e afetivo para bodas e casamentos intimistas."
        photo={albumSrc('mini-wedding', '27-fotografia-de-casamento-curitiba-por-do-sol8.webp')}
        photoAlt="Casal em mini wedding"
        group="eventos"
      />
      <section className="faq faq-band" aria-labelledby="perguntas">
        <img className="leaf" src="/rubia/img/folha.png" alt="" width="327" height="165" />
        <Kicker>Perguntas frequentes</Kicker>
        <h2 id="perguntas" className="visually-hidden">Perguntas frequentes</h2>
        <details>
          <summary>Que tipo de evento você fotografa?</summary>
        </details>
        <details>
          <summary>Que tipo de evento você fotografa?</summary>
        </details>
        <details>
          <summary>Que tipo de evento você fotografa?</summary>
        </details>
        <details open>
          <summary>Que tipo de evento você fotografa?</summary>
          <p>
            Gosto de registrar momentos que tenham significado — desde as festinhas cheias de risadas
            das crianças até mini weddings intimistas, bodas e eventos corporativos. Atendo aniversários,
            batizados, encontros familiares, eventos de marcas, lançamentos, workshops e tudo que envolva
            pessoas, conexão e emoção. Se o seu evento for diferente, me chama. Se tiver a ver com o meu
            estilo, vai ser um prazer fotografar.
          </p>
        </details>
      </section>
    </>
  );
}

function Blog() {
  return (
    <section className="blog">
      <Kicker>Meus registros de histórias reais</Kicker>
      <h1>Blog</h1>
      <p>
        Minha maior ambição é criar imagens poderosas que contem, através das gerações, as
        histórias que realmente importam e os dias felizes que tivemos. Histórias reais,
        fotografia com afeto e um olhar atento para o que acontece nos bastidores
        da vida em Curitiba. Criatividade, experiência e zero poses engessadas.
      </p>
      <Button href={`${BASE}/contato`} tone="fill">Fale comigo no Whats</Button>
      <div className="cards">
        {POST_COLUMNS.map((column, columnIndex) => (
          <div className="cards-column" key={columnIndex}>
            {column.map((post) => (
              <a className={post.wide ? 'card is-wide' : 'card'} href={POST_HREF} key={post.src}>
                <img src={post.src} alt="" width={post.width} height={post.height} />
                <span className="card-meta">
                  <span className="card-date"><b>16</b> Mai, 2026</span>
                  <span className="card-copy">
                    <em>{post.cat}</em>
                    <strong>{POST_TITLE}</strong>
                  </span>
                </span>
              </a>
            ))}
          </div>
        ))}
      </div>
    </section>
  );
}

function Post() {
  return (
    <article className="post">
      <Kicker>Casamentos</Kicker>
      <h1>Checklist: 10 perguntas para fazer antes de fechar o local do mini wedding em Curitiba</h1>
      <p className="by">Rubia Rocha · 14 de maio de 2026</p>
      <img src="/rubia/img/post-1.jpg" alt="Casal em um campo ao fim da tarde" />
      <p>{VENUE}</p>
      <p>{REGION}</p>
      <Specimen cta={false} />
      <img src="/rubia/img/post-2.jpg" alt="Casal caminhando entre as árvores" />
      <Button href={`${BASE}/contato`} tone="fill">Quero um orçamento para fotografia</Button>
    </article>
  );
}

function FieldIcon({ name }: { name: 'person' | 'mail' | 'chat' }) {
  if (name === 'person') {
    return <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4" /><path d="M4.5 20.5c1.2-3.8 4-5.5 7.5-5.5s6.3 1.7 7.5 5.5" /></svg>;
  }
  if (name === 'mail') {
    return <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5.5" width="17" height="13" rx="2" /><path d="m4 7 8 6 8-6" /></svg>;
  }

  return <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4.5c4.7 0 8 3 8 6.8s-3.3 6.7-8 6.7c-1 0-2-.1-2.9-.4L5 19.5l1.2-3.6C4.8 14.7 4 13.1 4 11.3 4 7.5 7.3 4.5 12 4.5z" /></svg>;
}

function Contact() {
  const [status, setStatus] = useState<'idle' | 'sent' | 'invalid'>('idle');

  function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const data = new FormData(event.currentTarget);
    if (String(data.get('empresa_site') ?? '').trim() !== '') {
      setStatus('sent');
      return;
    }
    const nome = String(data.get('nome') ?? '').trim();
    const email = String(data.get('email') ?? '').trim();
    const mensagem = String(data.get('mensagem') ?? '').trim();
    if (nome === '' || email === '' || mensagem === '' || !email.includes('@')) {
      setStatus('invalid');
      return;
    }
    setStatus('sent');
    event.currentTarget.reset();
  }

  return (
    <>
      <section className="talk">
        <img className="talk-photo" src="/rubia/img/contato.jpg" alt="Rubia Rocha sorrindo com a câmera" />
        <div className="talk-copy">
          <img className="leaf" src="/rubia/img/folha.png" alt="" width="327" height="165" />
          <Kicker>Contato</Kicker>
          <h1>Let&apos;s talk!</h1>
          <p>
            Se você está procurando uma fotógrafa profissional em Curitiba e região para registrar
            um ensaio ou evento especial, este é o primeiro passo. Tire suas dúvidas, peça um
            orçamento e me conte um pouco sobre o momento que deseja registrar.
          </p>
          <Button href="#orcamento" tone="fill">Quero um orçamento</Button>
        </div>
      </section>
      <section className="form-block" id="orcamento">
        <h2><span>Entre em</span> contato!</h2>
        <form method="post" action={`${BASE}/contato`} noValidate onSubmit={onSubmit}>
          <p className="hp"><label>Empresa <input name="empresa_site" tabIndex={-1} autoComplete="off" /></label></p>
          {status === 'sent' ? <p className="form-status" role="status">Mensagem enviada. Vou retornar em breve.</p> : null}
          {status === 'invalid' ? <p className="form-status is-error" role="alert">Preencha nome, email e mensagem.</p> : null}
          <div className="fields">
            <label>
              <span className="field-name"><FieldIcon name="person" />Nome</span>
              <input name="nome" type="text" required autoComplete="name" />
            </label>
            <label>
              <span className="field-name"><FieldIcon name="mail" />Email</span>
              <input name="email" type="email" required autoComplete="email" />
            </label>
            <label className="is-wide">
              <span className="field-name"><FieldIcon name="chat" />Mensagem</span>
              <textarea name="mensagem" required rows={2} />
            </label>
          </div>
          <button className="btn is-line" type="submit">Enviar <span aria-hidden="true">→</span></button>
        </form>
      </section>
    </>
  );
}

function AlbumList({ group }: { group: Album['group'] }) {
  useEffect(() => {
    const id = window.location.hash.replace('#', '');
    if (id === '') {
      return;
    }
    document.getElementById(id)?.scrollIntoView();
  }, []);

  return (
    <div className="albums">
      {ALBUMS.filter((album) => album.group === group).map((album) => (
        <AlbumBlock album={album} key={album.slug} />
      ))}
    </div>
  );
}

function AlbumBlock({ album }: { album: Album }) {
  const dialog = useRef<HTMLDialogElement>(null);
  const [index, setIndex] = useState<number | null>(null);
  const photo = index === null ? null : album.photos[index];

  useEffect(() => {
    const node = dialog.current;
    if (!node) {
      return;
    }
    if (index === null) {
      if (node.open) {
        node.close();
      }
      return;
    }
    if (!node.open) {
      node.showModal();
    }
  }, [index]);

  function step(direction: number) {
    setIndex((current) => {
      if (current === null) {
        return current;
      }

      return (current + direction + album.photos.length) % album.photos.length;
    });
  }

  return (
    <section className="album" id={album.slug} aria-labelledby={`${album.slug}-titulo`}>
      <h2 id={`${album.slug}-titulo`}>{album.title}</h2>
      <p className="album-count">{album.photos.length} fotos</p>
      <div className="album-grid">
        {album.photos.map((item, photoIndex) => (
          <button type="button" key={item.file} onClick={() => setIndex(photoIndex)}>
            <img
              src={albumSrc(album.slug, item.width > 960 ? item.file.replace(/\.webp$/, '-960.webp') : item.file)}
              alt={`${album.title}, foto ${photoIndex + 1}`}
              width={item.width}
              height={item.height}
              loading="lazy"
            />
          </button>
        ))}
      </div>
      <dialog
        className="lightbox"
        ref={dialog}
        aria-label={album.title}
        onClose={() => setIndex(null)}
        onClick={(event) => {
          if (event.target === dialog.current) {
            setIndex(null);
          }
        }}
        onKeyDown={(event) => {
          if (event.key === 'ArrowRight') {
            step(1);
          }
          if (event.key === 'ArrowLeft') {
            step(-1);
          }
        }}
      >
        {photo ? (
          <>
            <img src={albumSrc(album.slug, photo.file)} alt={album.title} width={photo.width} height={photo.height} />
            <div className="lightbox-bar">
              <button type="button" onClick={() => step(-1)}>Anterior</button>
              <button type="button" onClick={() => setIndex(null)}>Fechar</button>
              <button type="button" onClick={() => step(1)}>Próxima</button>
            </div>
          </>
        ) : null}
      </dialog>
    </section>
  );
}

function Specimen({ cta = true }: { cta?: boolean }) {
  return (
    <div className="specimen">
      <h2>Título H2: O que nonononono ?</h2>
      <p>{REGION}</p>
      <h3>Título H3: Lorem Ipsum nono ?</h3>
      <h4>Título H4: Lorem Ipsum nono ?</h4>
      <h5>Título H5: Lorem Ipsum nono ?</h5>
      <p className="h6">Título H6: Momentos que viram memórias do jeitinho que sua família é.</p>
      {cta ? <Button href={`${BASE}/contato`} tone="fill">Fale comigo no Whats</Button> : null}
    </div>
  );
}
