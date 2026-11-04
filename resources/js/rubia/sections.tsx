import { Fragment, type CSSProperties } from 'react';
import type { FormField, FormSchema, Section, SectionData } from './types';
import { Button, Buttons, Icon, Img, Kicker, Rich, flag, list, slugId, strings, text, useWhatsapp } from './ui';

const SHOT_SIZES = '(max-width: 560px) calc(100vw - 32px), (max-width: 800px) 46vw, 330px';

const LEAF = <img className="leaf" src="/rubia/img/folha.png" alt="" width="327" height="165" />;

type Photo = { image?: string; alt?: string };

type Card = { title?: string; href?: string; image?: string; image_alt?: string };

function Hero({ data }: { data: SectionData }) {
  const photos = list<Photo>(data, 'photos').filter((photo) => photo.image);
  if (photos.length === 0) {
    return null;
  }
  const label = text(data, 'label') || 'Trabalhos recentes';

  return (
    <section className="hero" aria-roledescription="carrossel" aria-label={label} data-carousel>
      <div className="hero-stage">
        <div className="hero-sizer" />
        <div className="hero-sizer" />
        <div className="hero-sizer" />
        {photos.map((photo, index) => {
          const offstage = index > 2;

          return (
            <Img
              key={`${photo.image}-${index}`}
              className="hero-photo"
              data-slot={offstage ? 3 : index}
              src={photo.image ?? ''}
              sizes="(max-width: 560px) calc(100vw - 32px), 560px"
              alt={photo.alt ?? ''}
              draggable={false}
              aria-hidden={offstage || undefined}
              loading={offstage ? 'lazy' : undefined}
              fetchPriority={index === 1 ? 'high' : undefined}
            />
          );
        })}
      </div>
      {photos.length > 3 ? (
        <div className="dots" role="group" aria-label="Escolher foto">
          {photos.map((photo, index) => (
            <button
              key={`${photo.image}-${index}`}
              type="button"
              data-dot={index}
              aria-label={`Foto ${index + 1}${photo.alt ? `: ${photo.alt}` : ''}`}
              aria-current={index === 0 || undefined}
            />
          ))}
          <button type="button" className="dots-toggle" aria-pressed="false" aria-label="Pausar a troca automática" data-toggle>
            <svg className="is-pause" viewBox="0 0 24 24" aria-hidden="true"><path d="M7.5 5.5h3v13h-3zM13.5 5.5h3v13h-3z" /></svg>
            <svg className="is-play" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13l10.5-6.5z" /></svg>
          </button>
        </div>
      ) : null}
    </section>
  );
}

function Hello({ title }: { title: string }) {
  const words = title.split(/\s+/).filter(Boolean);

  return (
    <h1 className="hello" aria-label={title}>
      {words.map((word, index) => (
        <Fragment key={`${word}-${index}`}>
          {index > 0 ? ' ' : null}
          <span className="hello-word" aria-hidden="true" style={{ '--i': index } as CSSProperties}><span>{word}</span></span>
        </Fragment>
      ))}
    </h1>
  );
}

function Intro({ data }: { data: SectionData }) {
  const image = text(data, 'image');

  return (
    <section className="intro intro-side">
      {image ? <Img className="intro-photo" src={image} sizes="(max-width: 800px) calc(100vw - 32px), 460px" alt={text(data, 'image_alt')} /> : null}
      <div className="intro-copy">
        <Kicker>{text(data, 'kicker')}</Kicker>
        {text(data, 'title') ? <Hello title={text(data, 'title')} /> : null}
        <Rich html={text(data, 'body')} className="rich" />
        <Buttons data={data} />
      </div>
    </section>
  );
}

function Shots({ data }: { data: SectionData }) {
  const cards = list<Card>(data, 'items').filter((card) => card.image);
  const label = text(data, 'item_label') || 'Ver galeria';
  if (cards.length === 0) {
    return null;
  }

  return (
    <div className="shots">
      {cards.map((card, index) => (
        <a className="shot" href={card.href || undefined} key={`${card.href}-${index}`}>
          <Img src={card.image ?? ''} sizes={SHOT_SIZES} alt={card.image_alt ?? ''} loading="lazy" />
          <span>
            <strong>{card.title}</strong>
            <em>{label}</em>
          </span>
        </a>
      ))}
    </div>
  );
}

function Sheet({ data }: { data: SectionData }) {
  return (
    <section className="sheet">
      {flag(data, 'leaf') ? LEAF : null}
      <Kicker>{text(data, 'kicker')}</Kicker>
      {text(data, 'title') ? <h2>{text(data, 'title')}</h2> : null}
      <Rich html={text(data, 'body')} className="rich" />
      <Shots data={data} />
    </section>
  );
}

function Quotes({ data, id }: { data: SectionData; id: string }) {
  const items = list<{ text?: string; name?: string; photo?: string }>(data, 'items').filter((item) => item.text);
  const titleId = `depoimentos-${id}`;

  return (
    <section className="quotes" aria-labelledby={text(data, 'title') ? titleId : undefined}>
      <Kicker>{text(data, 'kicker')}</Kicker>
      {text(data, 'title') ? <h2 id={titleId}>{text(data, 'title')}</h2> : null}
      <div className="quotes-wall">
        {items.map((item, index) => (
          <figure key={`${item.name}-${index}`} className="quote-card">
            {item.photo ? <Img src={item.photo} sizes="72px" alt="" loading="lazy" /> : null}
            <blockquote>
              <p>{item.text}</p>
            </blockquote>
            {item.name ? <figcaption>{item.name}</figcaption> : null}
          </figure>
        ))}
      </div>
    </section>
  );
}

function Closing({ data, id }: { data: SectionData; id: string }) {
  const titleId = `chamada-${id}`;

  return (
    <section className="closing" aria-labelledby={text(data, 'title') ? titleId : undefined}>
      <div className="closing-copy">
        <Kicker>{text(data, 'kicker')}</Kicker>
        {text(data, 'title') ? <h2 id={titleId}>{text(data, 'title')}</h2> : null}
        <Rich html={text(data, 'body')} className="rich" />
      </div>
      <Buttons data={data} />
    </section>
  );
}

function Trio({ data }: { data: SectionData }) {
  const photos = list<Photo>(data, 'photos').filter((photo) => photo.image);

  return (
    <section className="trio" aria-label={text(data, 'label') || undefined}>
      {photos.map((photo, index) => (
        <Img key={`${photo.image}-${index}`} src={photo.image ?? ''} sizes="(max-width: 560px) calc(100vw - 32px), 344px" alt={photo.alt ?? ''} />
      ))}
    </section>
  );
}

function About({ data }: { data: SectionData }) {
  const items = list<{ label?: string; href?: string }>(data, 'items').filter((item) => item.label);
  const cta = useWhatsapp(text(data, 'primary_href'));

  return (
    <section className="intro intro-about">
      <div className="intro-main">
        <Kicker>{text(data, 'kicker')}</Kicker>
        {text(data, 'title') ? <h1>{text(data, 'title')}</h1> : null}
        <Rich html={text(data, 'body')} className="rich" />
      </div>
      {items.length > 0 || text(data, 'primary_label') ? (
        <div className="intro-card">
          {text(data, 'list_title') ? <h2 className="intro-list-title">{text(data, 'list_title')}</h2> : null}
          <ul className="intro-list">
            {items.map((item, index) => (
              <li key={`${item.label}-${index}`}>{item.href ? <a href={item.href}>{item.label}</a> : item.label}</li>
            ))}
          </ul>
          <Button href={cta} tone="fill">{text(data, 'primary_label')}</Button>
        </div>
      ) : null}
    </section>
  );
}

function Facts({ data, id }: { data: SectionData; id: string }) {
  const facts = list<{ title?: string; body?: string }>(data, 'items').filter((fact) => fact.title || fact.body);
  const image = text(data, 'image');
  const titleId = `fatos-${id}`;
  const many = facts.length > 1;

  return (
    <section className="fact" aria-labelledby={text(data, 'title') ? titleId : undefined} data-facts>
      {image ? <Img src={image} sizes="(max-width: 560px) calc(100vw - 64px), 380px" alt={text(data, 'image_alt')} loading="lazy" decoding="async" /> : null}
      <div className="fact-copy">
        {text(data, 'title') ? <h2 id={titleId}>{text(data, 'title')}</h2> : null}
        <div className="fact-stack">
          {facts.map((fact, index) => (
            <article
              key={`${fact.title}-${index}`}
              className={index === 0 ? 'fact-card is-current' : 'fact-card'}
              aria-hidden={index !== 0 || undefined}
              inert={index !== 0 || undefined}
            >
              {fact.title ? <h3>{many ? `#${index + 1}: ` : null}{fact.title}</h3> : null}
              <Rich html={fact.body ?? ''} className="rich" />
            </article>
          ))}
        </div>
        {many ? (
          <div className="dots fact-dots" role="group" aria-label="Escolher fato">
            {facts.map((fact, index) => (
              <button key={`${fact.title}-${index}`} type="button" data-dot={index} aria-label={`Fato ${index + 1}`} aria-current={index === 0 || undefined} />
            ))}
            <button type="button" className="dots-next" aria-label="Próximo fato" data-next>
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h13M13 6.5 18.5 12 13 17.5" /></svg>
            </button>
          </div>
        ) : null}
        <Buttons data={data} />
      </div>
    </section>
  );
}

function FolioHero({ data }: { data: SectionData }) {
  const image = text(data, 'image');

  return (
    <header className="folio-top">
      {image ? <Img className="folio-photo" src={image} sizes="(max-width: 800px) calc(100vw - 32px), 600px" alt={text(data, 'image_alt')} fetchPriority="high" /> : null}
      <div className="folio-lead">
        <Kicker>{text(data, 'kicker')}</Kicker>
        {text(data, 'title') ? <h1>{text(data, 'title')}</h1> : null}
        {text(data, 'lede') ? <p className="folio-lede">{text(data, 'lede')}</p> : null}
        <Button href={text(data, 'cta_href')} tone="line">{text(data, 'cta_label')}</Button>
      </div>
    </header>
  );
}

function escapeHtml(value: string): string {
  return value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

type Block = { heading?: string; level?: string; body?: string; image?: string; image_alt?: string };

function Folio({ data, id }: { data: SectionData; id: string }) {
  const blocks = list<Block>(data, 'blocks').filter((block) => block.heading || block.body);
  const album = strings(data, 'album');
  const albumTitle = text(data, 'album_title');
  const cta = useWhatsapp(text(data, 'cta_href'));

  return (
    <article className="folio">
      {blocks.length > 0 || text(data, 'cta_label') ? (
        <div className="folio-body">
          {blocks.map((block, index) => {
            const tag = block.level === '3' ? 'h3' : 'h2';
            const heading = block.heading ? `<${tag}>${escapeHtml(block.heading)}</${tag}>` : '';

            return (
              <div className={block.image ? 'prose-pair' : 'prose-pair is-solo'} key={index}>
                <section className="prose-section" dangerouslySetInnerHTML={{ __html: heading + (block.body ?? '') }} />
                {block.image ? (
                  <Img className="prose-photo" src={block.image} sizes="(max-width: 800px) calc(100vw - 64px), 380px" alt={block.image_alt ?? ''} loading="lazy" />
                ) : null}
              </div>
            );
          })}
          <Button href={cta} tone="fill">{text(data, 'cta_label')}</Button>
        </div>
      ) : null}
      {album.length > 0 ? <Album id={albumTitle ? slugId(albumTitle, `album-${id}`) : `album-${id}`} title={albumTitle} photos={album} /> : null}
    </article>
  );
}

function Album({ id, title, photos }: { id: string; title: string; photos: string[] }) {
  const name = title || 'Galeria';

  return (
    <div className="albums">
      <section className="album" id={id} aria-labelledby={`${id}-titulo`} data-album>
        <h2 id={`${id}-titulo`}>{name}</h2>
        <p className="album-count">{photos.length} fotos</p>
        <div className="album-grid">
          {photos.map((photo, index) => (
            <button type="button" key={`${photo}-${index}`} data-full={photo} data-index={index}>
              <Img src={photo} sizes="(max-width: 560px) 50vw, 240px" alt={`${name}, foto ${index + 1}`} loading="lazy" />
            </button>
          ))}
        </div>
        <dialog className="lightbox" aria-label={name}>
          <div className="lightbox-bar">
            <button type="button" data-step="-1">Anterior</button>
            <button type="button" data-close>Fechar</button>
            <button type="button" data-step="1">Próxima</button>
          </div>
        </dialog>
      </section>
    </div>
  );
}

function Categories({ data, id }: { data: SectionData; id: string }) {
  const titleId = `categorias-${id}`;

  return (
    <section className="categories" aria-labelledby={text(data, 'title') ? titleId : undefined}>
      {text(data, 'title') ? <h2 id={titleId}>{text(data, 'title')}</h2> : null}
      <Shots data={data} />
    </section>
  );
}

function Faq({ data, id }: { data: SectionData; id: string }) {
  const items = list<{ question?: string; answer?: string }>(data, 'items').filter((item) => item.question && item.answer);
  const titleId = `perguntas-${id}`;
  if (items.length === 0) {
    return null;
  }

  return (
    <section className="faq faq-band" aria-labelledby={text(data, 'title') ? titleId : undefined}>
      {flag(data, 'leaf') ? LEAF : null}
      {text(data, 'title') ? <h2 id={titleId}>{text(data, 'title')}</h2> : null}
      {items.map((item, index) => (
        <details key={`${item.question}-${index}`} open={index === 0}>
          <summary>{item.question}</summary>
          {(item.answer ?? '').split(/\n{2,}/).map((paragraph, paragraphIndex) => <p key={paragraphIndex}>{paragraph}</p>)}
        </details>
      ))}
    </section>
  );
}

function Talk({ data }: { data: SectionData }) {
  const image = text(data, 'image');

  return (
    <section className="talk">
      {image ? <Img className="talk-photo" src={image} sizes="(max-width: 800px) calc(100vw - 32px), 640px" alt={text(data, 'image_alt')} fetchPriority="high" /> : null}
      <div className="talk-copy">
        {flag(data, 'leaf') ? LEAF : null}
        <Kicker>{text(data, 'kicker')}</Kicker>
        {text(data, 'title') ? <h1>{text(data, 'title')}</h1> : null}
        <Rich html={text(data, 'body')} className="rich" />
        <Button href={text(data, 'cta_href')} tone="line">{text(data, 'cta_label')}</Button>
      </div>
    </section>
  );
}

const FIELD_ICONS: Record<string, 'person' | 'mail' | 'chat' | 'phone'> = {
  nome: 'person',
  email: 'mail',
  mensagem: 'chat',
  telefone: 'phone',
};

function inputType(type: string): string {
  return ['email', 'tel', 'url'].includes(type) ? type : 'text';
}

function autoComplete(field: FormField): string | undefined {
  return ({ nome: 'name', email: 'email', telefone: 'tel' } as Record<string, string>)[field.name];
}

function FieldControl({ field }: { field: FormField }) {
  const icon = FIELD_ICONS[field.name];
  const wide = field.type === 'textarea' || field.type === 'checkbox';
  const common = {
    name: field.name,
    required: field.required || undefined,
    'data-label': field.label,
  };

  if (field.type === 'checkbox') {
    return (
      <label className="is-wide is-check">
        <input {...common} type="checkbox" value="1" />
        <span className="field-name">{field.label}</span>
      </label>
    );
  }

  return (
    <label className={wide ? 'is-wide' : undefined}>
      <span className="field-name">{icon ? <Icon name={icon} /> : null}{field.label}</span>
      {field.type === 'textarea' ? <textarea {...common} rows={2} maxLength={4000} /> : null}
      {field.type === 'select' ? (
        <select {...common} defaultValue="">
          <option value="" disabled>Escolha uma opção</option>
          {(field.options ?? []).map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
        </select>
      ) : null}
      {field.type !== 'textarea' && field.type !== 'select' ? (
        <input {...common} type={inputType(field.type)} autoComplete={autoComplete(field)} maxLength={180} />
      ) : null}
      {field.hint ? <span className="field-hint">{field.hint}</span> : null}
    </label>
  );
}

function Contact({ data, form, whatsapp }: { data: SectionData; form: FormSchema | null; whatsapp: string }) {
  const anchor = text(data, 'anchor') || 'orcamento';
  const fields = (form?.sections ?? []).flatMap((group) => group.fields).filter((field) => field.type !== 'file');

  return (
    <section className="form-block" id={anchor} aria-labelledby={`${anchor}-titulo`}>
      <div className="form-intro">
        <Kicker>{text(data, 'kicker')}</Kicker>
        <h2 id={`${anchor}-titulo`}>{text(data, 'title') || 'Fale comigo'}</h2>
        <Rich html={text(data, 'intro')} className="rich" />
        {whatsapp && text(data, 'whatsapp_label') ? (
          <a className="form-whats" href={whatsapp} target="_blank" rel="noopener noreferrer">{text(data, 'whatsapp_label')}</a>
        ) : null}
      </div>
      <form
        method="post"
        action="/api/contato"
        noValidate
        data-contact
        data-sent={text(data, 'sent') || 'Recebi sua mensagem. Te respondo em breve.'}
        data-invalid={text(data, 'invalid') || 'Falta pouco: confere os campos marcados.'}
        data-failed={text(data, 'failed') || 'Não consegui enviar agora. Tenta de novo em instantes.'}
      >
        <p className="hp"><label>Empresa <input name="empresa_site" tabIndex={-1} autoComplete="off" /></label></p>
        <div className="form-live" aria-live="polite" />
        <div className="fields">
          {fields.map((field) => <FieldControl key={field.name} field={field} />)}
        </div>
        <button className="btn is-line" type="submit">
          <span data-label>{form?.submit_label || 'Enviar'}</span> <span aria-hidden="true">→</span>
        </button>
      </form>
    </section>
  );
}

function Prose({ data, id }: { data: SectionData; id: string }) {
  const titleId = `texto-${id}`;
  const Heading = flag(data, 'heading') ? 'h1' : 'h2';

  return (
    <section className="prose-block" aria-labelledby={text(data, 'title') ? titleId : undefined}>
      <Kicker>{text(data, 'kicker')}</Kicker>
      {text(data, 'title') ? <Heading id={titleId}>{text(data, 'title')}</Heading> : null}
      <Rich html={text(data, 'body')} className="rich" />
      <Buttons data={data} />
    </section>
  );
}

export function Sections({ sections, form, whatsapp }: { sections: Section[]; form: FormSchema | null; whatsapp: string }) {
  return (
    <>
      {sections.map((section) => {
        const props = { data: section.data ?? {}, id: section.id };

        switch (section.type) {
          case 'site.hero':
            return <Hero key={section.id} {...props} />;
          case 'site.intro':
            return <Intro key={section.id} {...props} />;
          case 'site.sheet':
            return <Sheet key={section.id} {...props} />;
          case 'site.quotes':
            return <Quotes key={section.id} {...props} />;
          case 'site.closing':
            return <Closing key={section.id} {...props} />;
          case 'site.trio':
            return <Trio key={section.id} {...props} />;
          case 'site.about':
            return <About key={section.id} {...props} />;
          case 'site.facts':
            return <Facts key={section.id} {...props} />;
          case 'site.folio-hero':
            return <FolioHero key={section.id} {...props} />;
          case 'site.folio':
            return <Folio key={section.id} {...props} />;
          case 'site.categories':
            return <Categories key={section.id} {...props} />;
          case 'site.faq':
            return <Faq key={section.id} {...props} />;
          case 'site.talk':
            return <Talk key={section.id} {...props} />;
          case 'site.contact':
            return <Contact key={section.id} data={props.data} form={form} whatsapp={whatsapp} />;
          case 'site.prose':
            return <Prose key={section.id} {...props} />;
          default:
            return null;
        }
      })}
    </>
  );
}
