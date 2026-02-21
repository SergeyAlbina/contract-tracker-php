'use client';
import { useState } from 'react';
import useSWR from 'swr';
import { useRouter } from 'next/navigation';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import Dialog from '@mui/material/Dialog';
import DialogTitle from '@mui/material/DialogTitle';
import DialogContent from '@mui/material/DialogContent';
import DialogActions from '@mui/material/DialogActions';
import Select from '@mui/material/Select';
import MenuItem from '@mui/material/MenuItem';
import FormControl from '@mui/material/FormControl';
import InputLabel from '@mui/material/InputLabel';
import Alert from '@mui/material/Alert';
import AddIcon from '@mui/icons-material/Add';
import { DataGrid, GridColDef, GridRenderCellParams } from '@mui/x-data-grid';
import dayjs from 'dayjs';
import { useForm, Controller } from 'react-hook-form';
import { procurementsApi } from '@/lib/api';
import StatusChip from '@/components/common/StatusChip';
import type { ProcurementResponse, ProcurementStatus } from '@/types/api';

const LAW_LABEL: Record<string, string> = { LAW_223: '223-ФЗ', LAW_44: '44-ФЗ' };

interface ProcForm {
  number: string;
  title: string;
  lawType: string;
  description: string;
}

export default function ProcurementsPage() {
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const [error, setError] = useState('');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(0);
  const [pageSize] = useState(20);

  const key = ['procurements', page, search];
  const { data, isLoading, mutate } = useSWR(
    key,
    () => procurementsApi.list({ page: page + 1, limit: pageSize, ...(search && { search }) }).then((r) => r.data),
  );

  const rows: ProcurementResponse[] = data?.data ?? [];
  const total: number = data?.total ?? 0;

  const { register, handleSubmit, control, reset, formState: { errors, isSubmitting } } = useForm<ProcForm>({
    defaultValues: { number: '', title: '', lawType: 'LAW_223', description: '' },
  });

  const onAdd = async (values: ProcForm) => {
    setError('');
    try {
      const { data: created } = await procurementsApi.create({
        number: values.number,
        title: values.title,
        lawType: values.lawType,
        ...(values.description && { description: values.description }),
      });
      await mutate();
      reset();
      setOpen(false);
      router.push(`/procurements/${created.id}`);
    } catch (e: unknown) {
      const msg = (e as { response?: { data?: { message?: unknown } } })?.response?.data?.message;
      setError(Array.isArray(msg) ? msg.join(', ') : String(msg ?? 'Ошибка создания'));
    }
  };

  const columns: GridColDef<ProcurementResponse>[] = [
    { field: 'number', headerName: 'Номер', width: 130 },
    { field: 'title', headerName: 'Название', flex: 1, minWidth: 200 },
    {
      field: 'lawType',
      headerName: 'Закон',
      width: 90,
      renderCell: (p) => LAW_LABEL[p.value as string] ?? p.value,
    },
    {
      field: 'status',
      headerName: 'Статус',
      width: 160,
      renderCell: (p: GridRenderCellParams<ProcurementResponse, ProcurementStatus>) =>
        p.value ? <StatusChip status={p.value} /> : null,
    },
    {
      field: 'plannedDate',
      headerName: 'Плановая дата',
      width: 140,
      renderCell: (p) => p.value ? dayjs(p.value as string).format('DD.MM.YYYY') : '—',
    },
    {
      field: 'createdAt',
      headerName: 'Создан',
      width: 130,
      renderCell: (p) => dayjs(p.value as string).format('DD.MM.YYYY'),
    },
  ];

  return (
    <Box>
      <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', mb: 2 }}>
        <Typography variant="h5">Закупки</Typography>
        <Button variant="contained" startIcon={<AddIcon />} onClick={() => setOpen(true)}>
          Новая закупка
        </Button>
      </Box>

      <TextField
        size="small"
        placeholder="Поиск по номеру или названию"
        value={search}
        onChange={(e) => { setSearch(e.target.value); setPage(0); }}
        sx={{ mb: 2, width: 320 }}
      />

      <DataGrid
        rows={rows}
        columns={columns}
        loading={isLoading}
        rowCount={total}
        paginationMode="server"
        paginationModel={{ page, pageSize }}
        onPaginationModelChange={(m) => setPage(m.page)}
        pageSizeOptions={[20]}
        onRowClick={(p) => router.push(`/procurements/${p.id}`)}
        sx={{ bgcolor: 'white', cursor: 'pointer', height: 550 }}
        disableRowSelectionOnClick
      />

      <Dialog open={open} onClose={() => setOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Новая закупка</DialogTitle>
        <form onSubmit={handleSubmit(onAdd)}>
          <DialogContent sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
            {error && <Alert severity="error">{error}</Alert>}
            <TextField
              {...register('number', { required: 'Обязательное поле' })}
              label="Номер закупки"
              size="small"
              fullWidth
              placeholder="ЗП-2024-001"
              error={!!errors.number}
              helperText={errors.number?.message}
            />
            <TextField
              {...register('title', { required: 'Обязательное поле' })}
              label="Название"
              size="small"
              fullWidth
              error={!!errors.title}
              helperText={errors.title?.message}
            />
            <Controller
              name="lawType"
              control={control}
              render={({ field }) => (
                <FormControl size="small" fullWidth>
                  <InputLabel>Закон</InputLabel>
                  <Select {...field} label="Закон">
                    <MenuItem value="LAW_223">223-ФЗ</MenuItem>
                    <MenuItem value="LAW_44">44-ФЗ</MenuItem>
                  </Select>
                </FormControl>
              )}
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
            <Button type="submit" variant="contained" disabled={isSubmitting}>Создать</Button>
          </DialogActions>
        </form>
      </Dialog>
    </Box>
  );
}
