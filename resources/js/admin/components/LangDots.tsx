import { Link } from 'react-router-dom';
import { translationLabel, translationTone, useTranslationConfig, type TranslationState } from './TranslationBar';

export function LangDots({ translations, linkTo }: { translations?: Record<string, TranslationState>; linkTo?: (id: number) => string }) {
  const config = useTranslationConfig();
  if (!config || !translations) {
    return null;
  }

  return (
    <span className="lang-dots">
      {config.locales.filter((locale) => locale.code !== config.default).map((locale) => {
        const state = translations[locale.code];
        const title = `${locale.name}: ${translationLabel(state)}`;
        const className = `lang-dot ${translationTone(state)}`;

        return state?.id && linkTo ? (
          <Link key={locale.code} className={className} to={linkTo(state.id)} title={title}>{locale.short}</Link>
        ) : (
          <span key={locale.code} className={className} title={title}>{locale.short}</span>
        );
      })}
    </span>
  );
}
