'use client';
import { createContext, useContext, useEffect, useState } from 'react';
import { authApi } from '@/lib/api';
import { authStorage } from '@/lib/auth';
import type { MeResponse } from '@/types/api';

interface AuthCtx {
  user: MeResponse | null;
  loading: boolean;
  logout: () => Promise<void>;
}

const Ctx = createContext<AuthCtx>({ user: null, loading: true, logout: async () => {} });

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<MeResponse | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!authStorage.getAccess()) { setLoading(false); return; }
    authApi.me()
      .then((r) => setUser(r.data))
      .catch(() => authStorage.clear())
      .finally(() => setLoading(false));
  }, []);

  const logout = async () => {
    const refresh = authStorage.getRefresh();
    if (refresh) await authApi.logout(refresh).catch(() => {});
    authStorage.clear();
    setUser(null);
    window.location.href = '/login';
  };

  return <Ctx.Provider value={{ user, loading, logout }}>{children}</Ctx.Provider>;
}

export const useAuth = () => useContext(Ctx);
