import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { api } from '../lib/api';
import type { AuthState, User } from '../lib/types';

const AuthContext = createContext<AuthState | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [userType, setUserType] = useState<'admin' | 'applicant' | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.me().then((res) => {
      if (res.success && res.user) {
        setUser(res.user);
        setUserType(res.user_type as 'admin' | 'applicant');
      }
    }).catch(() => {}).finally(() => setLoading(false));
  }, []);

  const login = useCallback(async (email: string, password: string) => {
    try {
      const res = await api.login(email, password);
      if (res.success && res.user) {
        setUser(res.user);
        setUserType(res.user_type as 'admin' | 'applicant');
        return { success: true };
      }
      return { success: false, message: res.message || 'Login failed' };
    } catch {
      return { success: false, message: 'Connection error' };
    }
  }, []);

  const register = useCallback(async (data: { first_name: string; last_name: string; email: string; password: string }) => {
    try {
      const res = await api.register(data);
      if (res.success && res.user) {
        setUser(res.user);
        setUserType(res.user_type as 'admin' | 'applicant');
        return { success: true };
      }
      return { success: false, message: res.message || 'Registration failed' };
    } catch {
      return { success: false, message: 'Connection error' };
    }
  }, []);

  const logout = useCallback(async () => {
    try { await api.logout(); } catch {}
    setUser(null);
    setUserType(null);
  }, []);

  return (
    <AuthContext.Provider value={{ user, userType, loading, login, register, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}
