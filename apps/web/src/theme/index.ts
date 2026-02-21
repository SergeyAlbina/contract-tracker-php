import { createTheme } from '@mui/material/styles';
import { ruRU } from '@mui/material/locale';

const theme = createTheme(
  {
    palette: {
      primary: { main: '#1565c0' },
      secondary: { main: '#0288d1' },
      background: { default: '#f4f6f8' },
    },
    shape: { borderRadius: 8 },
    typography: {
      fontFamily: '"Inter", "Roboto", "Helvetica", sans-serif',
      h5: { fontWeight: 600 },
      h6: { fontWeight: 600 },
    },
    components: {
      MuiButton: {
        styleOverrides: {
          root: { textTransform: 'none', fontWeight: 600 },
        },
      },
      MuiChip: {
        styleOverrides: {
          root: { fontWeight: 500 },
        },
      },
    },
  },
  ruRU,
);

export default theme;
