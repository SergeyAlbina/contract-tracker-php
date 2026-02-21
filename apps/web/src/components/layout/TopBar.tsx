'use client';
import AppBar from '@mui/material/AppBar';
import Toolbar from '@mui/material/Toolbar';
import Typography from '@mui/material/Typography';
import IconButton from '@mui/material/IconButton';
import Avatar from '@mui/material/Avatar';
import Menu from '@mui/material/Menu';
import MenuItem from '@mui/material/MenuItem';
import Tooltip from '@mui/material/Tooltip';
import Box from '@mui/material/Box';
import MenuIcon from '@mui/icons-material/Menu';
import { useState } from 'react';
import { useAuth } from '@/components/providers/AuthProvider';
import { TOPBAR_HEIGHT } from './constants';

const ROLE_LABEL: Record<string, string> = {
  ADMIN: 'Администратор',
  HEAD_CS: 'Руководитель КС',
  SPECIALIST_CS: 'Специалист КС',
};

interface TopBarProps {
  onMenuClick: () => void;
}

export default function TopBar({ onMenuClick }: TopBarProps) {
  const { user, logout } = useAuth();
  const [anchor, setAnchor] = useState<HTMLElement | null>(null);

  const initials = user?.fullName
    .split(' ')
    .slice(0, 2)
    .map((w) => w[0])
    .join('')
    .toUpperCase() ?? '?';

  return (
    <AppBar
      position="fixed"
      elevation={0}
      sx={{
        zIndex: (t) => t.zIndex.drawer + 1,
        borderBottom: '1px solid #e0e0e0',
        bgcolor: 'white',
        color: 'text.primary',
        height: `${TOPBAR_HEIGHT}px`,
        justifyContent: 'center',
      }}
    >
      <Toolbar variant="dense" sx={{ minHeight: `${TOPBAR_HEIGHT}px` }}>
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, flexGrow: 1 }}>
          <IconButton
            edge="start"
            onClick={onMenuClick}
            sx={{ display: { xs: 'inline-flex', md: 'none' } }}
            aria-label="Открыть меню"
          >
            <MenuIcon />
          </IconButton>
          <Typography variant="subtitle1">Система контроля контрактов</Typography>
        </Box>
        {user && (
          <>
            <Typography
              variant="body2"
              color="text.secondary"
              sx={{ mr: 1, display: { xs: 'none', sm: 'block' } }}
            >
              {user.fullName} · {ROLE_LABEL[user.role] ?? 'Неизвестная роль'}
            </Typography>
            <Tooltip title="Аккаунт">
              <IconButton size="small" onClick={(e) => setAnchor(e.currentTarget)}>
                <Avatar sx={{ width: 32, height: 32, bgcolor: 'primary.main', fontSize: '0.8rem' }}>
                  {initials}
                </Avatar>
              </IconButton>
            </Tooltip>
            <Menu
              anchorEl={anchor}
              open={Boolean(anchor)}
              onClose={() => setAnchor(null)}
              anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
              transformOrigin={{ vertical: 'top', horizontal: 'right' }}
            >
              <MenuItem onClick={() => { setAnchor(null); logout(); }}>Выйти</MenuItem>
            </Menu>
          </>
        )}
      </Toolbar>
    </AppBar>
  );
}
