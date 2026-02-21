import { alpha, createTheme } from '@mui/material/styles';
import { ruRU as materialRuRU } from '@mui/material/locale';
import { ruRU as dataGridRuRU } from '@mui/x-data-grid/locales';
import { ruRU as datePickersRuRU } from '@mui/x-date-pickers/locales';
import type {} from '@mui/x-data-grid/themeAugmentation';

const theme = createTheme(
  {
    palette: {
      mode: 'light',
      primary: {
        main: '#0f4c81',
        light: '#3f7db2',
        dark: '#0a3458',
        contrastText: '#ffffff',
      },
      secondary: {
        main: '#00897b',
        light: '#34b6aa',
        dark: '#00675d',
        contrastText: '#ffffff',
      },
      background: {
        default: '#eef3f8',
        paper: '#ffffff',
      },
      text: {
        primary: '#0f172a',
        secondary: '#475569',
      },
      divider: 'rgba(15, 23, 42, 0.12)',
    },
    shape: { borderRadius: 14 },
    typography: {
      fontFamily: 'var(--font-manrope), "Segoe UI", "Tahoma", sans-serif',
      h5: { fontWeight: 750, letterSpacing: '-0.015em' },
      h6: { fontWeight: 700, letterSpacing: '-0.01em' },
      subtitle1: { fontWeight: 700 },
      body1: { fontSize: '0.97rem', lineHeight: 1.45 },
      body2: { fontSize: '0.92rem', lineHeight: 1.45 },
      button: { fontWeight: 700, letterSpacing: '0.01em' },
    },
    components: {
      MuiCssBaseline: {
        styleOverrides: {
          ':root': {
            colorScheme: 'light',
          },
          '*, *::before, *::after': {
            boxSizing: 'border-box',
          },
          body: {
            minHeight: '100vh',
            margin: 0,
            backgroundColor: '#eef3f8',
            backgroundAttachment: 'fixed',
            fontFeatureSettings: '"cv03", "cv04", "cv11"',
            textRendering: 'optimizeLegibility',
          },
          '#__next': {
            minHeight: '100vh',
          },
          '@keyframes shellSlideIn': {
            from: { opacity: 0, transform: 'translateY(-8px)' },
            to: { opacity: 1, transform: 'translateY(0)' },
          },
          '@keyframes fadeUp': {
            from: { opacity: 0, transform: 'translateY(10px)' },
            to: { opacity: 1, transform: 'translateY(0)' },
          },
          '.page-shell': {
            animation: 'fadeUp 280ms ease-out both',
          },
        },
      },
      MuiPaper: {
        styleOverrides: {
          root: {
            border: '1px solid rgba(15, 23, 42, 0.08)',
            boxShadow: '0 12px 28px rgba(15, 23, 42, 0.08)',
            backgroundImage:
              'linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(255, 255, 255, 0.92) 100%)',
          },
        },
      },
      MuiCard: {
        styleOverrides: {
          root: {
            border: '1px solid rgba(15, 23, 42, 0.08)',
            boxShadow: '0 14px 30px rgba(15, 23, 42, 0.12)',
            backdropFilter: 'blur(6px)',
          },
        },
      },
      MuiButton: {
        styleOverrides: {
          root: {
            textTransform: 'none',
            borderRadius: 10,
            transition: 'transform 160ms ease, box-shadow 160ms ease, background-color 160ms ease',
            '&:hover': {
              transform: 'translateY(-1px)',
            },
          },
          contained: {
            boxShadow: '0 10px 22px rgba(15, 76, 129, 0.28)',
            '&:hover': {
              boxShadow: '0 12px 24px rgba(15, 76, 129, 0.34)',
            },
          },
        },
      },
      MuiChip: {
        styleOverrides: {
          root: { fontWeight: 600 },
        },
      },
      MuiDrawer: {
        styleOverrides: {
          paper: {
            backgroundImage:
              'linear-gradient(165deg, rgba(255, 255, 255, 0.96) 0%, rgba(247, 251, 255, 0.94) 100%)',
            backdropFilter: 'blur(10px)',
          },
        },
      },
      MuiDataGrid: {
        styleOverrides: {
          root: {
            border: 0,
            borderRadius: 18,
            overflow: 'hidden',
            boxShadow: '0 16px 30px rgba(15, 23, 42, 0.1)',
            backgroundColor: '#ffffff',
            fontFamily: 'var(--font-manrope), "Segoe UI", "Tahoma", sans-serif',
            fontSize: '0.93rem',
            '& .MuiDataGrid-main': {
              borderRadius: 'inherit',
            },
            '& .MuiDataGrid-columnHeaders': {
              backgroundColor: alpha('#0f4c81', 0.08),
              borderBottom: `1px solid ${alpha('#0f172a', 0.12)}`,
            },
            '& .MuiDataGrid-columnHeader': {
              minHeight: '54px !important',
              maxHeight: '54px !important',
            },
            '& .MuiDataGrid-columnHeaderTitle': {
              fontWeight: 750,
              letterSpacing: '-0.01em',
            },
            '& .MuiDataGrid-row': {
              maxHeight: '52px !important',
              minHeight: '52px !important',
              transition: 'background-color 160ms ease',
            },
            '& .MuiDataGrid-cell': {
              maxHeight: '52px !important',
              minHeight: '52px !important',
              alignItems: 'center',
              borderColor: alpha('#0f172a', 0.08),
              fontWeight: 560,
            },
            '& .MuiDataGrid-row:hover': {
              backgroundColor: alpha('#0f4c81', 0.06),
            },
            '& .MuiDataGrid-columnSeparator': {
              color: alpha('#0f172a', 0.14),
            },
            '& .MuiDataGrid-footerContainer': {
              borderTop: `1px solid ${alpha('#0f172a', 0.08)}`,
              minHeight: 50,
            },
          },
        },
      },
      MuiAppBar: {
        styleOverrides: {
          root: {
            boxShadow: '0 10px 24px rgba(15, 23, 42, 0.06)',
          },
        },
      },
    },
  },
  materialRuRU,
  dataGridRuRU,
  datePickersRuRU,
);

export default theme;
