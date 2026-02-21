'use client';
import Drawer from '@mui/material/Drawer';
import List from '@mui/material/List';
import ListItemButton from '@mui/material/ListItemButton';
import ListItemIcon from '@mui/material/ListItemIcon';
import ListItemText from '@mui/material/ListItemText';
import Divider from '@mui/material/Divider';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import ArticleIcon from '@mui/icons-material/Article';
import ShoppingCartIcon from '@mui/icons-material/ShoppingCart';
import PeopleIcon from '@mui/icons-material/People';
import DashboardIcon from '@mui/icons-material/Dashboard';
import { usePathname, useRouter } from 'next/navigation';
import { useAuth } from '@/components/providers/AuthProvider';
import { UserRole } from '@/types/api';

const WIDTH = 240;

const NAV = [
  { label: 'Контракты', icon: <ArticleIcon />, href: '/contracts' },
  { label: 'Закупки', icon: <ShoppingCartIcon />, href: '/procurements' },
];

export default function Sidebar() {
  const pathname = usePathname();
  const router = useRouter();
  const { user } = useAuth();

  return (
    <Drawer
      variant="permanent"
      sx={{
        width: WIDTH,
        flexShrink: 0,
        '& .MuiDrawer-paper': { width: WIDTH, boxSizing: 'border-box', borderRight: '1px solid #e0e0e0' },
      }}
    >
      <Box sx={{ px: 2, py: 2.5 }}>
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
          <DashboardIcon color="primary" />
          <Typography variant="h6" color="primary" sx={{ lineHeight: 1 }}>
            Contract Tracker
          </Typography>
        </Box>
      </Box>
      <Divider />
      <List dense sx={{ px: 1, pt: 1 }}>
        {NAV.map(({ label, icon, href }) => (
          <ListItemButton
            key={href}
            selected={pathname.startsWith(href)}
            onClick={() => router.push(href)}
            sx={{ borderRadius: 1, mb: 0.5 }}
          >
            <ListItemIcon sx={{ minWidth: 36 }}>{icon}</ListItemIcon>
            <ListItemText primary={label} />
          </ListItemButton>
        ))}
        {user?.role === UserRole.ADMIN && (
          <ListItemButton
            selected={pathname.startsWith('/users')}
            onClick={() => router.push('/users')}
            sx={{ borderRadius: 1, mb: 0.5 }}
          >
            <ListItemIcon sx={{ minWidth: 36 }}><PeopleIcon /></ListItemIcon>
            <ListItemText primary="Пользователи" />
          </ListItemButton>
        )}
      </List>
    </Drawer>
  );
}
