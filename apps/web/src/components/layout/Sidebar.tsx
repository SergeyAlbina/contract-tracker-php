'use client';
import Drawer from '@mui/material/Drawer';
import List from '@mui/material/List';
import ListItemButton from '@mui/material/ListItemButton';
import ListItemIcon from '@mui/material/ListItemIcon';
import ListItemText from '@mui/material/ListItemText';
import Divider from '@mui/material/Divider';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import IconButton from '@mui/material/IconButton';
import ArticleIcon from '@mui/icons-material/Article';
import ShoppingCartIcon from '@mui/icons-material/ShoppingCart';
import PeopleIcon from '@mui/icons-material/People';
import DashboardIcon from '@mui/icons-material/Dashboard';
import ChevronLeftIcon from '@mui/icons-material/ChevronLeft';
import { usePathname, useRouter } from 'next/navigation';
import { useAuth } from '@/components/providers/AuthProvider';
import { UserRole } from '@/types/api';
import { SIDEBAR_WIDTH } from './constants';

const NAV = [
  { label: 'Контракты', icon: <ArticleIcon />, href: '/contracts' },
  { label: 'Закупки', icon: <ShoppingCartIcon />, href: '/procurements' },
];

interface SidebarProps {
  mobileOpen: boolean;
  onMobileClose: () => void;
}

export default function Sidebar({ mobileOpen, onMobileClose }: SidebarProps) {
  const pathname = usePathname();
  const router = useRouter();
  const { user } = useAuth();

  const navigate = (href: string) => {
    router.push(href);
    onMobileClose();
  };

  const content = (
    <Box sx={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
      <Box sx={{ px: 2.5, pt: 2.5, pb: 2, borderBottom: '1px solid', borderColor: 'divider' }}>
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.25 }}>
          <Box
            sx={{
              width: 34,
              height: 34,
              borderRadius: 1.5,
              display: 'grid',
              placeItems: 'center',
              bgcolor: 'primary.main',
              color: 'primary.contrastText',
              boxShadow: '0 8px 20px rgba(15, 76, 129, 0.28)',
            }}
          >
            <DashboardIcon fontSize="small" />
          </Box>
          <Box sx={{ minWidth: 0, flexGrow: 1 }}>
            <Typography variant="subtitle1" sx={{ fontWeight: 700, lineHeight: 1.1 }}>
              Трекер контрактов
            </Typography>
            <Typography variant="caption" color="text.secondary">
              Рабочая панель
            </Typography>
          </Box>
          <IconButton
            size="small"
            onClick={onMobileClose}
            sx={{ display: { xs: 'inline-flex', md: 'none' } }}
            aria-label="Закрыть меню"
          >
            <ChevronLeftIcon fontSize="small" />
          </IconButton>
        </Box>
      </Box>
      <Divider />
      <List dense sx={{ px: 1.25, py: 1.5, flexGrow: 1 }}>
        {NAV.map(({ label, icon, href }) => (
          <ListItemButton
            key={href}
            selected={pathname.startsWith(href)}
            onClick={() => navigate(href)}
            sx={{
              borderRadius: 2,
              mb: 0.5,
              minHeight: 42,
              transition: 'all 160ms ease',
              '& .MuiListItemText-primary': { fontWeight: 600, fontSize: '0.92rem' },
              '&:hover': { bgcolor: 'rgba(15, 76, 129, 0.08)' },
              '&.Mui-selected': {
                bgcolor: 'primary.main',
                color: 'primary.contrastText',
                boxShadow: '0 8px 18px rgba(15, 76, 129, 0.28)',
                '& .MuiListItemIcon-root': { color: 'inherit' },
                '&:hover': { bgcolor: 'primary.dark' },
              },
            }}
          >
            <ListItemIcon sx={{ minWidth: 36 }}>{icon}</ListItemIcon>
            <ListItemText primary={label} />
          </ListItemButton>
        ))}
        {user?.role === UserRole.ADMIN && (
          <ListItemButton
            selected={pathname.startsWith('/users')}
            onClick={() => navigate('/users')}
            sx={{
              borderRadius: 2,
              mb: 0.5,
              minHeight: 42,
              transition: 'all 160ms ease',
              '& .MuiListItemText-primary': { fontWeight: 600, fontSize: '0.92rem' },
              '&:hover': { bgcolor: 'rgba(15, 76, 129, 0.08)' },
              '&.Mui-selected': {
                bgcolor: 'primary.main',
                color: 'primary.contrastText',
                boxShadow: '0 8px 18px rgba(15, 76, 129, 0.28)',
                '& .MuiListItemIcon-root': { color: 'inherit' },
                '&:hover': { bgcolor: 'primary.dark' },
              },
            }}
          >
            <ListItemIcon sx={{ minWidth: 36 }}><PeopleIcon /></ListItemIcon>
            <ListItemText primary="Пользователи" />
          </ListItemButton>
        )}
      </List>
      <Box sx={{ px: 2.5, py: 1.5, borderTop: '1px solid', borderColor: 'divider' }}>
        <Typography variant="caption" color="text.secondary">
          UX shell v2
        </Typography>
      </Box>
    </Box>
  );

  return (
    <>
      <Drawer
        variant="temporary"
        open={mobileOpen}
        onClose={onMobileClose}
        ModalProps={{ keepMounted: true }}
        sx={{
          display: { xs: 'block', md: 'none' },
          '& .MuiDrawer-paper': {
            width: SIDEBAR_WIDTH,
            boxSizing: 'border-box',
            borderRight: '1px solid',
            borderColor: 'divider',
          },
        }}
      >
        {content}
      </Drawer>

      <Drawer
        variant="permanent"
        open
        sx={{
          display: { xs: 'none', md: 'block' },
          width: SIDEBAR_WIDTH,
          flexShrink: 0,
          '& .MuiDrawer-paper': {
            width: SIDEBAR_WIDTH,
            boxSizing: 'border-box',
            borderRight: '1px solid',
            borderColor: 'divider',
          },
        }}
      >
        {content}
      </Drawer>
    </>
  );
}
