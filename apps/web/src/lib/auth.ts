const ACCESS_KEY = 'ct_access';
const REFRESH_KEY = 'ct_refresh';

export const authStorage = {
  getAccess: (): string | null =>
    typeof window !== 'undefined' ? localStorage.getItem(ACCESS_KEY) : null,
  getRefresh: (): string | null =>
    typeof window !== 'undefined' ? localStorage.getItem(REFRESH_KEY) : null,
  set: (access: string, refresh: string) => {
    localStorage.setItem(ACCESS_KEY, access);
    localStorage.setItem(REFRESH_KEY, refresh);
  },
  clear: () => {
    localStorage.removeItem(ACCESS_KEY);
    localStorage.removeItem(REFRESH_KEY);
  },
};
