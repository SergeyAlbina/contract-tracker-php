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
import { SIDEBAR_WIDTH, TOPBAR_HEIGHT } from './constants';

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
        zIndex: (t) => t.zIndex.drawer + 2,
        borderBottom: '1px solid',
        borderColor: 'divider',
        bgcolor: 'rgba(248, 251, 255, 0.84)',
        color: 'text.primary',
        backdropFilter: 'blur(14px)',
        height: `${TOPBAR_HEIGHT}px`,
        left: { md: `${SIDEBAR_WIDTH}px` },
        width: { md: `calc(100% - ${SIDEBAR_WIDTH}px)` },
        justifyContent: 'center',
        animation: 'shellSlideIn 260ms ease-out',
      }}
    >
      <Toolbar
        sx={{
          minHeight: `${TOPBAR_HEIGHT}px !important`,
          px: { xs: 1, sm: 2, md: 3 },
          gap: 1,
        }}
      >
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, flexGrow: 1, minWidth: 0 }}>
          <IconButton
            edge="start"
            onClick={onMenuClick}
            sx={{ display: { xs: 'inline-flex', md: 'none' } }}
            aria-label="Открыть меню"
          >
            <MenuIcon />
          </IconButton>
          <Box sx={{ minWidth: 0 }}>
            <Typography variant="subtitle1" sx={{ fontWeight: 700, lineHeight: 1.1 }}>
              Система контроля контрактов
            </Typography>
            <Typography
              variant="caption"
              color="text.secondary"
              sx={{ display: { xs: 'none', lg: 'block' } }}
            >
              Контроль этапов, рисков и оплат
            </Typography>
          </Box>
        </Box>
        {user && (
          <>
            <Typography
              variant="body2"
              color="text.secondary"
              sx={{
                mr: 1.5,
                display: { xs: 'none', sm: 'block' },
                maxWidth: 320,
                overflow: 'hidden',
                textOverflow: 'ellipsis',
                whiteSpace: 'nowrap',
              }}
            >
              {user.fullName} · {ROLE_LABEL[user.role] ?? 'Неизвестная роль'}
            </Typography>
            <Tooltip title="Аккаунт">
              <IconButton size="small" onClick={(e) => setAnchor(e.currentTarget)}>
                <Avatar
                  sx={{
                    width: 36,
                    height: 36,
                    bgcolor: 'primary.main',
                    fontSize: '0.82rem',
                    fontWeight: 700,
                    boxShadow: '0 8px 18px rgba(15, 76, 129, 0.25)',
                  }}
                >
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
