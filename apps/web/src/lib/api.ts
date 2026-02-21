import axios from 'axios';
import { authStorage } from './auth';
import type { TokenResponse } from '@/types/api';

const BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:4000';

export const api = axios.create({
  baseURL: `${BASE_URL}/api/v1`,
});

api.interceptors.request.use((config) => {
  const token = authStorage.getAccess();
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

let isRefreshing = false;
let queue: Array<(token: string) => void> = [];

api.interceptors.response.use(
  (r) => r,
  async (error) => {
    const original = error.config;
    if (error.response?.status !== 401 || original._retry) {
      return Promise.reject(error);
    }
    original._retry = true;

    if (isRefreshing) {
      return new Promise((resolve) => {
        queue.push((token) => {
          original.headers.Authorization = `Bearer ${token}`;
          resolve(api(original));
        });
      });
    }

    isRefreshing = true;
    try {
      const refresh = authStorage.getRefresh();
      if (!refresh) throw new Error('no refresh token');
      const { data } = await axios.post<TokenResponse>(`${BASE_URL}/api/v1/auth/refresh`, {
        refreshToken: refresh,
      });
      authStorage.set(data.accessToken, data.refreshToken);
      queue.forEach((cb) => cb(data.accessToken));
      queue = [];
      original.headers.Authorization = `Bearer ${data.accessToken}`;
      return api(original);
    } catch {
      authStorage.clear();
      if (typeof window !== 'undefined') window.location.href = '/login';
      return Promise.reject(error);
    } finally {
      isRefreshing = false;
    }
  },
);

// ─── Auth ─────────────────────────────────────────────────────────────────────
export const authApi = {
  login: (email: string, password: string) =>
    api.post<TokenResponse>('/auth/login', { email, password }),
  me: () => api.get('/auth/me'),
  logout: (refreshToken: string) => api.post('/auth/logout', { refreshToken }),
};

// ─── Contracts ────────────────────────────────────────────────────────────────
export const contractsApi = {
  list: (params?: Record<string, unknown>) => api.get('/contracts', { params }),
  get: (id: string) => api.get(`/contracts/${id}`),
  create: (data: Record<string, unknown>) => api.post('/contracts', data),
  update: (id: string, data: Record<string, unknown>) => api.patch(`/contracts/${id}`, data),
};

// ─── Stages ───────────────────────────────────────────────────────────────────
export const stagesApi = {
  list: (contractId: string) => api.get(`/contracts/${contractId}/stages`),
  create: (contractId: string, data: Record<string, unknown>) =>
    api.post(`/contracts/${contractId}/stages`, data),
  update: (contractId: string, id: string, data: Record<string, unknown>) =>
    api.patch(`/contracts/${contractId}/stages/${id}`, data),
  remove: (contractId: string, id: string) =>
    api.delete(`/contracts/${contractId}/stages/${id}`),
};

// ─── Payments ─────────────────────────────────────────────────────────────────
export const paymentsApi = {
  listInvoices: (contractId: string) => api.get(`/contracts/${contractId}/invoices`),
  createInvoice: (contractId: string, data: Record<string, unknown>) =>
    api.post(`/contracts/${contractId}/invoices`, data),
  listPayments: (contractId: string) => api.get(`/contracts/${contractId}/payments`),
  createPayment: (contractId: string, data: Record<string, unknown>) =>
    api.post(`/contracts/${contractId}/payments`, data),
  updatePayment: (contractId: string, id: string, data: Record<string, unknown>) =>
    api.patch(`/contracts/${contractId}/payments/${id}`, data),
  removePayment: (contractId: string, id: string) =>
    api.delete(`/contracts/${contractId}/payments/${id}`),
};

// ─── Procurements ─────────────────────────────────────────────────────────────
export const procurementsApi = {
  list: (params?: Record<string, unknown>) => api.get('/procurements', { params }),
  get: (id: string) => api.get(`/procurements/${id}`),
  create: (data: Record<string, unknown>) => api.post('/procurements', data),
  update: (id: string, data: Record<string, unknown>) => api.patch(`/procurements/${id}`, data),
  listProposals: (id: string) => api.get(`/procurements/${id}/proposals`),
  createProposal: (id: string, data: Record<string, unknown>) =>
    api.post(`/procurements/${id}/proposals`, data),
  decideProposal: (procId: string, propId: string, data: Record<string, unknown>) =>
    api.patch(`/procurements/${procId}/proposals/${propId}/decide`, data),
};

// ─── Users ────────────────────────────────────────────────────────────────────
export const usersApi = {
  list: () => api.get('/users'),
  get: (id: string) => api.get(`/users/${id}`),
  create: (data: Record<string, unknown>) => api.post('/users', data),
  update: (id: string, data: Record<string, unknown>) => api.patch(`/users/${id}`, data),
  remove: (id: string) => api.delete(`/users/${id}`),
};
