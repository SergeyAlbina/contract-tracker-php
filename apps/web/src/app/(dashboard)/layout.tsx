'use client';
import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import Box from '@mui/material/Box';
import CircularProgress from '@mui/material/CircularProgress';
import { AuthProvider, useAuth } from '@/components/providers/AuthProvider';
import Sidebar from '@/components/layout/Sidebar';
import TopBar from '@/components/layout/TopBar';
import { SIDEBAR_WIDTH, TOPBAR_HEIGHT } from '@/components/layout/constants';

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
  const [mobileOpen, setMobileOpen] = useState(false);

  return (
    <AuthProvider>
      <Guard>
        <Box sx={{ display: 'flex' }}>
          <TopBar onMenuClick={() => setMobileOpen(true)} />
          <Sidebar mobileOpen={mobileOpen} onMobileClose={() => setMobileOpen(false)} />
          <Box
            component="main"
            sx={{
              flexGrow: 1,
              width: { xs: '100%', md: `calc(100% - ${SIDEBAR_WIDTH}px)` },
              px: { xs: 1.5, sm: 2, md: 3 },
              py: { xs: 2, md: 3 },
              pt: `calc(${TOPBAR_HEIGHT}px + 16px)`,
              bgcolor: 'background.default',
              minHeight: '100vh',
              overflowX: 'auto',
            }}
          >
            {children}
          </Box>
        </Box>
      </Guard>
    </AuthProvider>
  );
}
