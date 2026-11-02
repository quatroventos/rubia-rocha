import logo from '../assets/logo-quatroventos.svg';

export function DevCredit({ className }: { className?: string }) {
  return (
    <a
      className={className ? `dev-credit ${className}` : 'dev-credit'}
      href="https://quatroventos.com.br"
      target="_blank"
      rel="noreferrer"
    >
      <span>Desenvolvido por:</span>
      <img src={logo} alt="Quatro Ventos" />
    </a>
  );
}
