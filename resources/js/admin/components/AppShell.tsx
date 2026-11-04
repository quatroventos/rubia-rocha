import { NavLink, useLocation, useNavigate } from 'react-router-dom';
import {
  ArrowRightLeft,
  CircleHelp,
  FileText,
  Image,
  Inbox,
  LayoutDashboard,
  ListTree,
  ClipboardList,
  Database,
  HardDrive,
  ScrollText,
  Menu,
  Search,
  Settings2,
  Tag,
  UserRound,
  Users,
} from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { useAuth } from '../auth';
import { initials } from '../lib';
import { api } from '../api';
import { tourIdForPath } from '../tour/registry';
import { useTour } from '../tour/TourProvider';
import { DevCredit } from './DevCredit';
import { BrandMark } from './BrandMark';

const groups = [
  {
    label: 'Operação',
    tour: 'shell-nav-operacao',
    items: [
      { to: '/', label: 'Dashboard', icon: LayoutDashboard, ability: 'pages' },
      { to: '/mensagens', label: 'Mensagens recebidas', icon: Inbox, ability: 'messages' },
    ],
  },
  {
    label: 'Conteúdo',
    tour: 'shell-nav-conteudo',
    items: [
      { to: '/paginas', label: 'Páginas', icon: FileText, ability: 'pages' },
    ],
  },
  {
    label: 'Configuração',
    tour: 'shell-nav-config',
    items: [
      { to: '/site', label: 'Site', icon: Settings2, ability: 'settings' },
      { to: '/seo', label: 'SEO', icon: Search, ability: 'seo' },
      { to: '/menu', label: 'Menu', icon: ListTree, ability: 'menu' },
      { to: '/redirecionamentos', label: 'Redirecionamentos', icon: ArrowRightLeft, ability: 'redirects' },
      { to: '/formularios', label: 'Formulários', icon: ClipboardList, ability: 'form_options' },
    ],
  },
  {
    label: 'Sistema',
    tour: 'shell-nav-sistema',
    items: [
      { to: '/usuarios', label: 'Usuários', icon: Users, ability: 'users' },
      { to: '/log', label: 'Log de ações', icon: ScrollText, ability: 'audit' },
      { to: '/cache', label: 'Cache', icon: Database, ability: 'cache' },
      { to: '/imagens', label: 'Otimizar imagens', icon: Image, ability: 'cache' },
      { to: '/backup', label: 'Backup', icon: HardDrive, ability: 'backup' },
      { to: '/tags', label: 'Tags', icon: Tag, ability: 'tags' },
      { to: '/perfil', label: 'Perfil', icon: UserRound, ability: 'pages' },
    ],
  },
];

export function AppShell({
  title,
  eyebrow,
  subtitle,
  actions,
  titleAction,
  children,
  inbox = false,
  full = false,
}: {
  title: string;
  eyebrow?: string;
  subtitle?: string;
  actions?: ReactNode;
  titleAction?: ReactNode;
  children: ReactNode;
  inbox?: boolean;
  full?: boolean;
}) {
  const { user, setUser } = useAuth();
  const { startTour, registerActions } = useTour();
  const [open, setOpen] = useState(false);
  const [userMenu, setUserMenu] = useState(false);
  const [leaving, setLeaving] = useState(false);
  const userRef = useRef<HTMLDivElement>(null);
  const location = useLocation();
  const navigate = useNavigate();
  const abilities = user?.abilities;
  const screenTour = tourIdForPath(location.pathname, abilities);
  const compact = inbox || full;

  useEffect(() => {
    function close(event: MouseEvent) {
      if (!userRef.current?.contains(event.target as Node)) {
        setUserMenu(false);
      }
    }

    document.addEventListener('mousedown', close);
    return () => document.removeEventListener('mousedown', close);
  }, []);

  useEffect(() => registerActions({
    'shell.openUserMenu': () => setUserMenu(true),
    'shell.closeUserMenu': () => setUserMenu(false),
  }), [registerActions]);

  async function logout() {
    await api('/logout', { method: 'POST' });
    setUser(null);
    navigate('/login');
  }

  async function leaveImpersonation() {
    setLeaving(true);
    try {
      const payload = await api<{ user: NonNullable<typeof user> }>('/impersonation/leave', { method: 'POST' });
      setUser(payload.user);
      navigate('/usuarios');
    } finally {
      setLeaving(false);
    }
  }

  const pageHeader = !compact ? (
    <div className="page-header" data-tour="shell-title">
      {eyebrow ? <span className="eyebrow">{eyebrow}</span> : null}
      <div className="section-header">
        <div>
          {titleAction ? (
            <div className="page-title-row">
              <h2>{title}</h2>
              {titleAction}
            </div>
          ) : <h2>{title}</h2>}
          {subtitle ? <p className="desc">{subtitle}</p> : null}
        </div>
        {actions ?? null}
      </div>
    </div>
  ) : null;

  return (
    <div className="app-frame">
      {user?.impersonating ? (
        <div className="impersonation-banner">
          <span>
            Acessando como <strong>{user.name}</strong>
            {user.impersonator ? ` · sessão de ${user.impersonator.name}` : ''}
          </span>
          <button type="button" disabled={leaving} onClick={() => void leaveImpersonation()}>
            Voltar à minha conta
          </button>
        </div>
      ) : null}
      <div className="app-shell">
        {open ? <div className="scrim" onClick={() => setOpen(false)} /> : null}
        <aside className={`sidebar${open ? ' open' : ''}`} data-tour="shell-sidebar">
          <BrandMark />
          {groups.map((group) => {
            const items = group.items.filter((item) => abilities?.[item.ability as keyof typeof abilities]);
            if (items.length === 0) {
              return null;
            }

            return (
              <div className="nav-group" key={group.label} data-tour={group.tour}>
                <span className="nav-label">{group.label}</span>
                {items.map((item) => {
                  const Icon = item.icon;
                  const active = item.to === '/'
                    ? location.pathname === '/'
                    : location.pathname.startsWith(item.to);

                  return (
                    <NavLink
                      key={item.to}
                      to={item.to}
                      className={`nav-item${active ? ' active' : ''}`}
                      onClick={() => setOpen(false)}
                    >
                      <Icon size={18} />
                      {item.label}
                    </NavLink>
                  );
                })}
              </div>
            );
          })}
          <div className="sidebar-foot">
            <DevCredit />
          </div>
        </aside>
        <div className={`main-shell${compact ? ' is-flush' : ''}`}>
          <header className={`topbar${compact ? '' : ' is-compact'}`}>
            <div className="topbar-title" data-tour={compact ? 'shell-title' : undefined}>
              <button className="icon-btn menu-btn" type="button" onClick={() => setOpen(true)} aria-label="Menu">
                <Menu size={16} />
              </button>
              {compact ? (
                <div>
                  <h1>{title}</h1>
                  {subtitle ? <p>{subtitle}</p> : null}
                </div>
              ) : null}
            </div>
            <div className="topbar-actions">
              {screenTour ? (
                <button
                  className="icon-btn"
                  type="button"
                  data-tour="shell-help"
                  aria-label="Ajuda"
                  title="Ajuda"
                  onClick={() => startTour(screenTour, { force: true })}
                >
                  <CircleHelp size={16} />
                </button>
              ) : null}
              <div className="topbar-user" ref={userRef} data-tour="shell-user">
                <button className="user-chip" type="button" onClick={() => setUserMenu((current) => !current)}>
                  <span className="avatar">{initials(user?.name ?? 'R')}</span>
                  <div>
                    <strong>{user?.name}</strong>
                    <span>{user?.role}</span>
                  </div>
                </button>
                {userMenu ? (
                  <div className="user-menu">
                    <NavLink to="/perfil" onClick={() => setUserMenu(false)}>Perfil</NavLink>
                    <button type="button" onClick={() => void logout()}>Sair</button>
                  </div>
                ) : null}
              </div>
            </div>
          </header>
          {compact ? children : (
            <div className="content-scroll">
              {pageHeader}
              {children}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
