'use client';
import { useState } from 'react';
import useSWR from 'swr';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Typography from '@mui/material/Typography';
import Dialog from '@mui/material/Dialog';
import DialogTitle from '@mui/material/DialogTitle';
import DialogContent from '@mui/material/DialogContent';
import DialogActions from '@mui/material/DialogActions';
import TextField from '@mui/material/TextField';
import Select from '@mui/material/Select';
import MenuItem from '@mui/material/MenuItem';
import FormControl from '@mui/material/FormControl';
import InputLabel from '@mui/material/InputLabel';
import Alert from '@mui/material/Alert';
import Chip from '@mui/material/Chip';
import IconButton from '@mui/material/IconButton';
import Tooltip from '@mui/material/Tooltip';
import AddIcon from '@mui/icons-material/Add';
import PersonOffIcon from '@mui/icons-material/PersonOff';
import { DataGrid, GridColDef, GridRenderCellParams } from '@mui/x-data-grid';
import { useForm, Controller } from 'react-hook-form';
import { usersApi } from '@/lib/api';
import { useAuth } from '@/components/providers/AuthProvider';
import { getUiErrorMessage } from '@/lib/error-message';
import type { UserResponse, UserRole } from '@/types/api';
import dayjs from 'dayjs';

const ROLE_LABEL: Record<string, string> = {
  ADMIN: 'Администратор',
  HEAD_CS: 'Руководитель КС',
  SPECIALIST_CS: 'Специалист КС',
};

interface UserForm {
  email: string;
  password: string;
  fullName: string;
  role: string;
}

export default function UsersPage() {
  const { user: me } = useAuth();
  const [open, setOpen] = useState(false);
  const [error, setError] = useState('');

  const key = 'users';
  const { data: users = [], isLoading, mutate } = useSWR<UserResponse[]>(
    key,
    () => usersApi.list().then((r) => r.data),
  );

  const { register, handleSubmit, control, reset, formState: { errors, isSubmitting } } = useForm<UserForm>({
    defaultValues: { email: '', password: '', fullName: '', role: 'SPECIALIST_CS' },
  });

  const onCreate = async (values: UserForm) => {
    setError('');
    try {
      await usersApi.create({
        email: values.email,
        password: values.password,
        fullName: values.fullName,
        role: values.role,
      });
      await mutate();
      reset();
      setOpen(false);
    } catch (e: unknown) {
      setError(getUiErrorMessage(e, 'Ошибка создания'));
    }
  };

  const onDeactivate = async (id: string) => {
    setError('');
    try {
      await usersApi.remove(id);
      await mutate();
    } catch {
      setError('Ошибка деактивации пользователя');
    }
  };

  if (me?.role !== 'ADMIN') {
    return <Alert severity="warning">Доступ только для администраторов</Alert>;
  }

  const columns: GridColDef<UserResponse>[] = [
    { field: 'fullName', headerName: 'Имя', flex: 1, minWidth: 180 },
    { field: 'email', headerName: 'Эл. почта', flex: 1, minWidth: 200 },
    {
      field: 'role',
      headerName: 'Роль',
      width: 180,
      renderCell: (p: GridRenderCellParams<UserResponse, UserRole>) =>
        <Chip label={ROLE_LABEL[p.value ?? ''] ?? 'Неизвестная роль'} size="small" />,
    },
    {
      field: 'isActive',
      headerName: 'Статус',
      width: 100,
      renderCell: (p) => (
        <Chip
          label={p.value ? 'Активен' : 'Заблокирован'}
          color={p.value ? 'success' : 'default'}
          size="small"
        />
      ),
    },
    {
      field: 'createdAt',
      headerName: 'Создан',
      width: 120,
      renderCell: (p) => dayjs(p.value as string).format('DD.MM.YYYY'),
    },
    {
      field: 'actions',
      headerName: '',
      width: 60,
      sortable: false,
      renderCell: (p: GridRenderCellParams<UserResponse>) =>
        p.row.id !== me?.id && p.row.isActive ? (
          <Tooltip title="Деактивировать">
            <IconButton size="small" color="error" onClick={(e) => { e.stopPropagation(); onDeactivate(p.row.id); }}>
              <PersonOffIcon fontSize="small" />
            </IconButton>
          </Tooltip>
        ) : null,
    },
  ];

  return (
    <Box>
      <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', mb: 2 }}>
        <Typography variant="h5">Пользователи</Typography>
        <Button variant="contained" startIcon={<AddIcon />} onClick={() => setOpen(true)}>
          Новый пользователь
        </Button>
      </Box>

      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}

      <DataGrid
        rows={users}
        columns={columns}
        loading={isLoading}
        sx={{ bgcolor: 'white', height: 500 }}
        disableRowSelectionOnClick
      />

      <Dialog open={open} onClose={() => setOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Новый пользователь</DialogTitle>
        <form onSubmit={handleSubmit(onCreate)}>
          <DialogContent sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
            {error && <Alert severity="error">{error}</Alert>}
            <TextField
              {...register('fullName', { required: 'Обязательное поле' })}
              label="Полное имя"
              size="small"
              fullWidth
              error={!!errors.fullName}
              helperText={errors.fullName?.message}
              placeholder="Иванов Иван Иванович"
            />
            <TextField
              {...register('email', { required: 'Обязательное поле' })}
              label="Эл. почта"
              type="email"
              size="small"
              fullWidth
              error={!!errors.email}
              helperText={errors.email?.message}
            />
            <TextField
              {...register('password', { required: 'Обязательное поле', minLength: { value: 8, message: 'Минимум 8 символов' } })}
              label="Пароль"
              type="password"
              size="small"
              fullWidth
              error={!!errors.password}
              helperText={errors.password?.message}
            />
            <Controller
              name="role"
              control={control}
              render={({ field }) => (
                <FormControl size="small" fullWidth>
                  <InputLabel>Роль</InputLabel>
                  <Select {...field} label="Роль">
                    <MenuItem value="ADMIN">Администратор</MenuItem>
                    <MenuItem value="HEAD_CS">Руководитель КС</MenuItem>
                    <MenuItem value="SPECIALIST_CS">Специалист КС</MenuItem>
                  </Select>
                </FormControl>
              )}
            />
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setOpen(false)}>Отмена</Button>
            <Button type="submit" variant="contained" disabled={isSubmitting}>Создать</Button>
          </DialogActions>
        </form>
      </Dialog>
    </Box>
  );
}
