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
        <Box
          sx={{
            minHeight: '100vh',
            bgcolor: 'background.default',
            backgroundImage:
              'radial-gradient(circle at 20% -10%, rgba(16, 76, 129, 0.14), transparent 35%), radial-gradient(circle at 90% 10%, rgba(0, 137, 123, 0.12), transparent 30%)',
          }}
        >
          <TopBar onMenuClick={() => setMobileOpen(true)} />
          <Sidebar mobileOpen={mobileOpen} onMobileClose={() => setMobileOpen(false)} />
          <Box
            component="main"
            sx={{
              ml: { md: `${SIDEBAR_WIDTH}px` },
              px: { xs: 1.25, sm: 2, md: 3 },
              pb: { xs: 2, md: 3 },
              pt: {
                xs: `calc(${TOPBAR_HEIGHT}px + 12px)`,
                md: `calc(${TOPBAR_HEIGHT}px + 24px)`,
              },
              minHeight: '100vh',
              overflowX: 'clip',
            }}
          >
            <Box
              className="page-shell"
              sx={{
                maxWidth: 1600,
                mx: 'auto',
                display: 'flex',
                flexDirection: 'column',
                gap: 2,
              }}
            >
              {children}
            </Box>
          </Box>
        </Box>
      </Guard>
    </AuthProvider>
  );
}
