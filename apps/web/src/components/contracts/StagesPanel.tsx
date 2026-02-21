'use client';
import { useState } from 'react';
import useSWR from 'swr';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Typography from '@mui/material/Typography';
import Table from '@mui/material/Table';
import TableHead from '@mui/material/TableHead';
import TableBody from '@mui/material/TableBody';
import TableRow from '@mui/material/TableRow';
import TableCell from '@mui/material/TableCell';
import Dialog from '@mui/material/Dialog';
import DialogTitle from '@mui/material/DialogTitle';
import DialogContent from '@mui/material/DialogContent';
import DialogActions from '@mui/material/DialogActions';
import TextField from '@mui/material/TextField';
import Alert from '@mui/material/Alert';
import IconButton from '@mui/material/IconButton';
import AddIcon from '@mui/icons-material/Add';
import DeleteIcon from '@mui/icons-material/Delete';
import { useForm, Controller } from 'react-hook-form';
import { DatePicker } from '@mui/x-date-pickers/DatePicker';
import { LocalizationProvider } from '@mui/x-date-pickers/LocalizationProvider';
import { AdapterDayjs } from '@mui/x-date-pickers/AdapterDayjs';
import dayjs, { Dayjs } from 'dayjs';
import 'dayjs/locale/ru';
import { stagesApi } from '@/lib/api';
import StatusChip from '@/components/common/StatusChip';
import type { StageResponse } from '@/types/api';

dayjs.locale('ru');

const fmt = (val?: string) =>
  val == null ? '—' : Number(val).toLocaleString('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 0 });

interface StageForm {
  title: string;
  plannedStart: Dayjs | null;
  plannedEnd: Dayjs | null;
  amount: string;
  description: string;
}

interface Props { contractId: string }

export default function StagesPanel({ contractId }: Props) {
  const [open, setOpen] = useState(false);
  const [deleteError, setDeleteError] = useState('');

  const key = ['stages', contractId];
  const { data: stages = [], mutate } = useSWR<StageResponse[]>(
    key,
    () => stagesApi.list(contractId).then((r) => r.data),
  );

  const { register, handleSubmit, control, reset, formState: { errors, isSubmitting } } = useForm<StageForm>({
    defaultValues: { title: '', plannedStart: null, plannedEnd: null, amount: '', description: '' },
  });

  const onAdd = async (values: StageForm) => {
    const payload: Record<string, unknown> = {
      title: values.title,
      plannedStart: values.plannedStart?.toISOString(),
      plannedEnd: values.plannedEnd?.toISOString(),
    };
    if (values.amount) payload.amount = values.amount;
    if (values.description) payload.description = values.description;
    await stagesApi.create(contractId, payload);
    await mutate();
    reset();
    setOpen(false);
  };

  const onDelete = async (id: string) => {
    setDeleteError('');
    try {
      await stagesApi.remove(contractId, id);
      await mutate();
    } catch {
      setDeleteError('Не удалось удалить этап');
    }
  };

  return (
    <LocalizationProvider dateAdapter={AdapterDayjs} adapterLocale="ru">
      <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 1 }}>
        <Typography variant="subtitle2">Этапы исполнения</Typography>
        <Button size="small" startIcon={<AddIcon />} onClick={() => setOpen(true)}>Добавить</Button>
      </Box>

      {deleteError && <Alert severity="error" sx={{ mb: 1 }}>{deleteError}</Alert>}

      <Table size="small">
        <TableHead>
          <TableRow>
            <TableCell>Название</TableCell>
            <TableCell>Статус</TableCell>
            <TableCell>Плановое начало</TableCell>
            <TableCell>Плановое окончание</TableCell>
            <TableCell align="right">Сумма</TableCell>
            <TableCell />
          </TableRow>
        </TableHead>
        <TableBody>
          {stages.length === 0 && (
            <TableRow>
              <TableCell colSpan={6} align="center">
                <Typography variant="body2" color="text.secondary">Этапы не добавлены</Typography>
              </TableCell>
            </TableRow>
          )}
          {stages.map((s) => (
            <TableRow key={s.id}>
              <TableCell>{s.title}</TableCell>
              <TableCell><StatusChip status={s.status} /></TableCell>
              <TableCell>{dayjs(s.plannedStart).format('DD.MM.YYYY')}</TableCell>
              <TableCell>{dayjs(s.plannedEnd).format('DD.MM.YYYY')}</TableCell>
              <TableCell align="right">{fmt(s.amount)}</TableCell>
              <TableCell>
                <IconButton size="small" color="error" onClick={() => onDelete(s.id)}>
                  <DeleteIcon fontSize="small" />
                </IconButton>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>

      <Dialog open={open} onClose={() => setOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Добавить этап</DialogTitle>
        <form onSubmit={handleSubmit(onAdd)}>
          <DialogContent sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
            <TextField
              {...register('title', { required: 'Обязательное поле' })}
              label="Название этапа"
              size="small"
              fullWidth
              error={!!errors.title}
              helperText={errors.title?.message}
            />
            <Controller
              name="plannedStart"
              control={control}
              rules={{ required: 'Обязательное поле' }}
              render={({ field, fieldState }) => (
                <DatePicker
                  label="Плановое начало"
                  value={field.value}
                  onChange={field.onChange}
                  slotProps={{ textField: { size: 'small', fullWidth: true, error: !!fieldState.error, helperText: fieldState.error?.message } }}
                />
              )}
            />
            <Controller
              name="plannedEnd"
              control={control}
              rules={{ required: 'Обязательное поле' }}
              render={({ field, fieldState }) => (
                <DatePicker
                  label="Плановое окончание"
                  value={field.value}
                  onChange={field.onChange}
                  slotProps={{ textField: { size: 'small', fullWidth: true, error: !!fieldState.error, helperText: fieldState.error?.message } }}
                />
              )}
            />
            <TextField
              {...register('amount', { pattern: { value: /^\d+(\.\d{0,2})?$/, message: 'Введите число' } })}
              label="Сумма (необязательно)"
              size="small"
              fullWidth
              placeholder="1000000.00"
              error={!!errors.amount}
              helperText={errors.amount?.message}
            />
            <TextField
              {...register('description')}
              label="Описание"
              size="small"
              fullWidth
              multiline
              rows={2}
            />
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setOpen(false)}>Отмена</Button>
            <Button type="submit" variant="contained" disabled={isSubmitting}>Добавить</Button>
          </DialogActions>
        </form>
      </Dialog>
    </LocalizationProvider>
  );
}
