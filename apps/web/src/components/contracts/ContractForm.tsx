'use client';
import { useEffect } from 'react';
import { useForm, Controller } from 'react-hook-form';
import Grid from '@mui/material/Grid';
import TextField from '@mui/material/TextField';
import Select from '@mui/material/Select';
import MenuItem from '@mui/material/MenuItem';
import FormControl from '@mui/material/FormControl';
import InputLabel from '@mui/material/InputLabel';
import FormHelperText from '@mui/material/FormHelperText';
import Button from '@mui/material/Button';
import Box from '@mui/material/Box';
import { DatePicker } from '@mui/x-date-pickers/DatePicker';
import { LocalizationProvider } from '@mui/x-date-pickers/LocalizationProvider';
import { AdapterDayjs } from '@mui/x-date-pickers/AdapterDayjs';
import dayjs, { Dayjs } from 'dayjs';
import 'dayjs/locale/ru';
import type { ContractResponse } from '@/types/api';

dayjs.locale('ru');

const STATUS_OPTIONS = [
  { value: 'DRAFT', label: 'Черновик' },
  { value: 'ACTIVE', label: 'Активен' },
  { value: 'SUSPENDED', label: 'Приостановлен' },
  { value: 'COMPLETED', label: 'Завершён' },
  { value: 'TERMINATED', label: 'Расторгнут' },
  { value: 'ARCHIVED', label: 'В архиве' },
];

interface FormValues {
  number: string;
  title: string;
  lawType: string;
  status: string;
  supplierName: string;
  supplierInn: string;
  totalAmount: string;
  nmckAmount: string;
  signedAt: Dayjs | null;
  startDate: Dayjs | null;
  endDate: Dayjs | null;
  description: string;
}

interface Props {
  initial?: ContractResponse;
  onSubmit: (data: Record<string, unknown>) => Promise<void>;
  submitLabel?: string;
}

export default function ContractForm({ initial, onSubmit, submitLabel = 'Сохранить' }: Props) {
  const { register, handleSubmit, control, watch, formState: { errors, isSubmitting }, reset } = useForm<FormValues>({
    defaultValues: {
      number: '',
      title: '',
      lawType: 'LAW_223',
      status: 'DRAFT',
      supplierName: '',
      supplierInn: '',
      totalAmount: '',
      nmckAmount: '',
      signedAt: null,
      startDate: null,
      endDate: null,
      description: '',
    },
  });

  useEffect(() => {
    if (initial) {
      reset({
        number: initial.number,
        title: initial.title,
        lawType: initial.lawType,
        status: initial.status,
        supplierName: initial.supplierName,
        supplierInn: initial.supplierInn ?? '',
        totalAmount: initial.totalAmount,
        nmckAmount: initial.nmckAmount ?? '',
        signedAt: initial.signedAt ? dayjs(initial.signedAt) : null,
        startDate: initial.startDate ? dayjs(initial.startDate) : null,
        endDate: initial.endDate ? dayjs(initial.endDate) : null,
        description: initial.description ?? '',
      });
    }
  }, [initial, reset]);

  const lawType = watch('lawType');

  const handleFormSubmit = (values: FormValues) => {
    const payload: Record<string, unknown> = {
      number: values.number,
      title: values.title,
      lawType: values.lawType,
      supplierName: values.supplierName,
      totalAmount: values.totalAmount,
    };
    if (initial) payload.status = values.status;
    if (values.supplierInn) payload.supplierInn = values.supplierInn;
    if (values.nmckAmount) payload.nmckAmount = values.nmckAmount;
    if (values.signedAt) payload.signedAt = values.signedAt.toISOString();
    if (values.startDate) payload.startDate = values.startDate.toISOString();
    if (values.endDate) payload.endDate = values.endDate.toISOString();
    if (values.description) payload.description = values.description;
    return onSubmit(payload);
  };

  return (
    <LocalizationProvider dateAdapter={AdapterDayjs} adapterLocale="ru">
      <form onSubmit={handleSubmit(handleFormSubmit)}>
        <Grid container spacing={2}>
          {/* Строка 1 */}
          <Grid item xs={12} sm={4}>
            <TextField
              {...register('number', { required: 'Обязательное поле' })}
              label="Номер контракта"
              fullWidth
              size="small"
              error={!!errors.number}
              helperText={errors.number?.message}
              placeholder="КД-2024-001"
            />
          </Grid>
          <Grid item xs={12} sm={8}>
            <TextField
              {...register('title', { required: 'Обязательное поле', minLength: { value: 3, message: 'Минимум 3 символа' } })}
              label="Название"
              fullWidth
              size="small"
              error={!!errors.title}
              helperText={errors.title?.message}
            />
          </Grid>

          {/* Строка 2 */}
          <Grid item xs={12} sm={3}>
            <Controller
              name="lawType"
              control={control}
              rules={{ required: true }}
              render={({ field }) => (
                <FormControl fullWidth size="small">
                  <InputLabel>Закон</InputLabel>
                  <Select {...field} label="Закон">
                    <MenuItem value="LAW_223">223-ФЗ</MenuItem>
                    <MenuItem value="LAW_44">44-ФЗ</MenuItem>
                  </Select>
                </FormControl>
              )}
            />
          </Grid>
          {initial && (
            <Grid item xs={12} sm={3}>
              <Controller
                name="status"
                control={control}
                render={({ field }) => (
                  <FormControl fullWidth size="small">
                    <InputLabel>Статус</InputLabel>
                    <Select {...field} label="Статус">
                      {STATUS_OPTIONS.map((o) => (
                        <MenuItem key={o.value} value={o.value}>{o.label}</MenuItem>
                      ))}
                    </Select>
                  </FormControl>
                )}
              />
            </Grid>
          )}

          {/* Поставщик */}
          <Grid item xs={12} sm={6}>
            <TextField
              {...register('supplierName', { required: 'Обязательное поле', minLength: { value: 2, message: 'Минимум 2 символа' } })}
              label="Поставщик"
              fullWidth
              size="small"
              error={!!errors.supplierName}
              helperText={errors.supplierName?.message}
            />
          </Grid>
          <Grid item xs={12} sm={3}>
            <TextField
              {...register('supplierInn')}
              label="ИНН поставщика"
              fullWidth
              size="small"
            />
          </Grid>

          {/* Суммы */}
          <Grid item xs={12} sm={4}>
            <TextField
              {...register('totalAmount', {
                required: 'Обязательное поле',
                pattern: { value: /^\d+(\.\d{0,2})?$/, message: 'Введите число' },
              })}
              label="Сумма контракта, ₽"
              fullWidth
              size="small"
              error={!!errors.totalAmount}
              helperText={errors.totalAmount?.message}
              placeholder="5000000.00"
            />
          </Grid>
          {lawType === 'LAW_44' && (
            <Grid item xs={12} sm={4}>
              <TextField
                {...register('nmckAmount', {
                  required: 'Обязательно для 44-ФЗ',
                  pattern: { value: /^\d+(\.\d{0,2})?$/, message: 'Введите число' },
                })}
                label="НМЦК, ₽"
                fullWidth
                size="small"
                error={!!errors.nmckAmount}
                helperText={errors.nmckAmount?.message ?? 'Начальная максимальная цена (44-ФЗ)'}
              />
            </Grid>
          )}

          {/* Даты */}
          <Grid item xs={12} sm={4}>
            <Controller
              name="signedAt"
              control={control}
              render={({ field }) => (
                <DatePicker
                  label="Дата подписания"
                  value={field.value}
                  onChange={field.onChange}
                  slotProps={{ textField: { size: 'small', fullWidth: true } }}
                />
              )}
            />
          </Grid>
          <Grid item xs={12} sm={4}>
            <Controller
              name="startDate"
              control={control}
              render={({ field }) => (
                <DatePicker
                  label="Дата начала"
                  value={field.value}
                  onChange={field.onChange}
                  slotProps={{ textField: { size: 'small', fullWidth: true } }}
                />
              )}
            />
          </Grid>
          <Grid item xs={12} sm={4}>
            <Controller
              name="endDate"
              control={control}
              render={({ field }) => (
                <DatePicker
                  label="Дата окончания"
                  value={field.value}
                  onChange={field.onChange}
                  slotProps={{ textField: { size: 'small', fullWidth: true } }}
                />
              )}
            />
          </Grid>

          {/* Описание */}
          <Grid item xs={12}>
            <TextField
              {...register('description')}
              label="Описание"
              fullWidth
              size="small"
              multiline
              rows={3}
            />
          </Grid>

          <Grid item xs={12}>
            <Box sx={{ display: 'flex', gap: 2 }}>
              <Button type="submit" variant="contained" disabled={isSubmitting}>
                {isSubmitting ? 'Сохраняем…' : submitLabel}
              </Button>
            </Box>
            {errors.root && <FormHelperText error>{errors.root.message}</FormHelperText>}
          </Grid>
        </Grid>
      </form>
    </LocalizationProvider>
  );
}
