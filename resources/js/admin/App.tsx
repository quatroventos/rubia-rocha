import { Navigate, Route, Routes } from 'react-router-dom';
import type { ReactNode } from 'react';
import { useAuth } from './auth';
import { LoginScreen } from './screens/LoginScreen';
import { DashboardScreen } from './screens/DashboardScreen';
import { InboxScreen } from './screens/InboxScreen';
import { AuditScreen } from './screens/AuditScreen';
import { PagesScreen } from './screens/PagesScreen';
import { PageEditorScreen } from './screens/PageEditorScreen';
import { CaseEditorScreen, CasesScreen } from './screens/CasesScreens';
import { CacheScreen, SiteScreen, TagsScreen } from './screens/SettingsScreens';
import { MediaOptimizationScreen } from './screens/MediaOptimizationScreen';
import { BackupScreen } from './screens/BackupScreen';
import { SeoScreen } from './screens/SeoScreen';
import {
  CertificationsScreen,
  MenuScreen,
  UsersScreen,
} from './screens/ResourceScreens';
import { ProfileScreen } from './screens/ProfileScreen';
import { FormsScreen } from './screens/FormsScreen';
import { FormEditorScreen } from './screens/FormEditorScreen';

function Guard({ children, ability }: { children: ReactNode; ability?: keyof NonNullable<ReturnType<typeof useAuth>['user']>['abilities'] }) {
  const { user, loading } = useAuth();

  if (loading) {
    return <div className="auth-loading"><div className="spinner" /></div>;
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  if (ability && !user.abilities[ability]) {
    return <Navigate to="/" replace />;
  }

  return <>{children}</>;
}

export function App() {
  const { user, loading } = useAuth();

  return (
    <Routes>
      <Route
        path="/login"
        element={loading ? <div className="auth-loading"><div className="spinner" /></div> : user ? <Navigate to="/" replace /> : <LoginScreen />}
      />
      <Route path="/" element={<Guard><DashboardScreen /></Guard>} />
      <Route path="/mensagens" element={<Guard ability="messages"><InboxScreen /></Guard>} />
      <Route path="/curriculos" element={<Navigate to="/mensagens?tipo=curriculos" replace />} />
      <Route path="/paginas" element={<Guard ability="pages"><PagesScreen /></Guard>} />
      <Route path="/paginas/:slug" element={<Guard ability="pages"><PageEditorScreen /></Guard>} />
      <Route path="/cases" element={<Guard ability="cases"><CasesScreen /></Guard>} />
      <Route path="/cases/:id" element={<Guard ability="cases"><CaseEditorScreen /></Guard>} />
      <Route path="/site" element={<Guard ability="settings"><SiteScreen /></Guard>} />
      <Route path="/cache" element={<Guard ability="cache"><CacheScreen /></Guard>} />
      <Route path="/imagens" element={<Guard ability="cache"><MediaOptimizationScreen /></Guard>} />
      <Route path="/backup" element={<Guard ability="backup"><BackupScreen /></Guard>} />
      <Route path="/menu" element={<Guard ability="menu"><MenuScreen /></Guard>} />
      <Route path="/certificacoes" element={<Guard ability="certifications"><CertificationsScreen /></Guard>} />
      <Route path="/formularios" element={<Guard ability="form_options"><FormsScreen /></Guard>} />
      <Route path="/formularios/:form" element={<Guard ability="form_options"><FormEditorScreen /></Guard>} />
      <Route path="/seo" element={<Guard ability="seo"><SeoScreen /></Guard>} />
      <Route path="/sitemap" element={<Navigate to="/seo" replace />} />
      <Route path="/usuarios" element={<Guard ability="users"><UsersScreen /></Guard>} />
      <Route path="/log" element={<Guard ability="audit"><AuditScreen /></Guard>} />
      <Route path="/tags" element={<Guard ability="tags"><TagsScreen /></Guard>} />
      <Route path="/perfil" element={<Guard><ProfileScreen /></Guard>} />
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
