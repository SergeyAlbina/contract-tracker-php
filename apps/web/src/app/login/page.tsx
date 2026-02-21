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
      const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message;
      setError(Array.isArray(msg) ? msg.join(', ') : (msg ?? 'Неверный логин или пароль'));
    }
  };

  return (
    <Box
      sx={{
        minHeight: '100vh',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        bgcolor: 'background.default',
      }}
    >
      <Card sx={{ width: 400, boxShadow: 3 }}>
        <CardContent sx={{ p: 4 }}>
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 3 }}>
            <DashboardIcon color="primary" fontSize="large" />
            <Typography variant="h5" color="primary">Contract Tracker</Typography>
          </Box>
          <Typography variant="body2" color="text.secondary" sx={{ mb: 3 }}>
            Войдите в систему управления контрактами
          </Typography>

          {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}

          <form onSubmit={handleSubmit(onSubmit)}>
            <TextField
              {...register('email', { required: true })}
              label="Email"
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
