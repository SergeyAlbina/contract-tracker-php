'use client';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useRouter } from 'next/navigation';
import Box from '@mui/material/Box';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import TextField from '@mui/material/TextField';
import Button from '@mui/material/Button';
import Typography from '@mui/material/Typography';
import Alert from '@mui/material/Alert';
import DashboardIcon from '@mui/icons-material/Dashboard';
import { authApi } from '@/lib/api';
import { authStorage } from '@/lib/auth';
import { getUiErrorMessage } from '@/lib/error-message';
import type { TokenResponse } from '@/types/api';

interface FormData {
  email: string;
  password: string;
}

export default function LoginPage() {
  const router = useRouter();
  const [error, setError] = useState('');
  const { register, handleSubmit, formState: { isSubmitting } } = useForm<FormData>();

  const onSubmit = async ({ email, password }: FormData) => {
    setError('');
    try {
      const { data } = await authApi.login(email, password) as { data: TokenResponse };
      authStorage.set(data.accessToken, data.refreshToken);
      router.replace('/contracts');
    } catch (e: unknown) {
      setError(getUiErrorMessage(e, 'Неверный логин или пароль'));
    }
  };

  return (
    <Box
      sx={{
        minHeight: '100vh',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        px: 2,
        py: 4,
        position: 'relative',
        overflow: 'hidden',
        bgcolor: 'background.default',
        backgroundImage:
          'radial-gradient(circle at 10% 10%, rgba(15, 76, 129, 0.24), transparent 30%), radial-gradient(circle at 90% 20%, rgba(0, 137, 123, 0.2), transparent 35%), linear-gradient(145deg, #eef3f8 0%, #e8eff8 45%, #f7fbff 100%)',
      }}
    >
      <Card
        sx={{
          width: '100%',
          maxWidth: 430,
          borderRadius: 3,
          animation: 'fadeUp 320ms ease-out',
        }}
      >
        <CardContent sx={{ p: { xs: 3, sm: 4 } }}>
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.25, mb: 3 }}>
            <Box
              sx={{
                width: 40,
                height: 40,
                borderRadius: 1.75,
                display: 'grid',
                placeItems: 'center',
                bgcolor: 'primary.main',
                color: 'primary.contrastText',
                boxShadow: '0 10px 20px rgba(15, 76, 129, 0.28)',
              }}
            >
              <DashboardIcon fontSize="small" />
            </Box>
            <Box>
              <Typography variant="h5" color="primary" sx={{ lineHeight: 1.1 }}>
                Трекер контрактов
              </Typography>
              <Typography variant="caption" color="text.secondary">
                Управление закупками и контрактами
              </Typography>
            </Box>
          </Box>
          <Typography variant="body2" color="text.secondary" sx={{ mb: 3 }}>
            Войдите в систему управления контрактами
          </Typography>

          {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}

          <form onSubmit={handleSubmit(onSubmit)}>
            <TextField
              {...register('email', { required: true })}
              label="Эл. почта"
              type="email"
              fullWidth
              size="small"
              sx={{ mb: 2 }}
              autoComplete="email"
              autoFocus
            />
            <TextField
              {...register('password', { required: true })}
              label="Пароль"
              type="password"
              fullWidth
              size="small"
              sx={{ mb: 3 }}
              autoComplete="current-password"
            />
            <Button
              type="submit"
              variant="contained"
              fullWidth
              size="large"
              disabled={isSubmitting}
            >
              {isSubmitting ? 'Входим…' : 'Войти'}
            </Button>
          </form>
        </CardContent>
      </Card>
    </Box>
  );
}
