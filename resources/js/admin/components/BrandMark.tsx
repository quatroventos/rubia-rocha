import { branding } from '../branding';

export function BrandMark({
  variant = 'sidebar',
}: {
  variant?: 'sidebar' | 'login';
}) {
  const { name, logo } = branding();

  if (logo) {
    return (
      <div className={`brand brand--${variant}`}>
        <img className="brand-logo" src={logo} alt={name} />
      </div>
    );
  }

  return (
    <div className={`brand brand--${variant}`}>
      <span className="brand-word">{name}</span>
    </div>
  );
}
