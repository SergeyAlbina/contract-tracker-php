'use client';
import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import Box from '@mui/material/Box';
import CircularProgress from '@mui/material/CircularProgress';
import { AuthProvider, useAuth } from '@/components/providers/AuthProvider';
import Sidebar from '@/components/layout/Sidebar';
import TopBar from '@/components/layout/TopBar';

function Guard({ children }: { children: React.ReactNode }) {
  const { user, loading } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (!loading && !user) router.replace('/login');
  }, [loading, user, router]);

  if (loading) {
    return (
      <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: '100vh' }}>
        <CircularProgress />
      </Box>
    );
  }
  if (!user) return null;
  return <>{children}</>;
}

export default function DashboardLayout({ children }: { children: React.ReactNode }) {
  return (
    <AuthProvider>
      <Guard>
        <Box sx={{ display: 'flex' }}>
          <TopBar />
          <Sidebar />
          <Box
            component="main"
            sx={{ flexGrow: 1, p: 3, pt: 8, bgcolor: 'background.default', minHeight: '100vh' }}
          >
            {children}
          </Box>
        </Box>
      </Guard>
    </AuthProvider>
  );
}
