import { createContext, createElement, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { api } from './api';
import type { CmsUser } from './lib';

type AuthContextValue = {
  user: CmsUser | null;
  loading: boolean;
  refresh: () => Promise<void>;
  setUser: (user: CmsUser | null) => void;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<CmsUser | null>(null);
  const [loading, setLoading] = useState(true);

  const refresh = async () => {
    try {
      const payload = await api<{ user: CmsUser }>('/me');
      setUser(payload.user);
    } catch {
      setUser(null);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    void refresh();
  }, []);

  const value = useMemo(() => ({ user, loading, refresh, setUser }), [user, loading]);

  return createElement(AuthContext.Provider, { value }, children);
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth precisa do AuthProvider');
  }

  return context;
}
