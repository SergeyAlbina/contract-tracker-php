'use client';
import { useState } from 'react';
import useSWR from 'swr';
import { useParams } from 'next/navigation';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Breadcrumbs from '@mui/material/Breadcrumbs';
import Link from '@mui/material/Link';
import Paper from '@mui/material/Paper';
import Grid from '@mui/material/Grid';
import Chip from '@mui/material/Chip';
import Divider from '@mui/material/Divider';
import Button from '@mui/material/Button';
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
import CircularProgress from '@mui/material/CircularProgress';
import AddIcon from '@mui/icons-material/Add';
import CheckIcon from '@mui/icons-material/Check';
import CloseIcon from '@mui/icons-material/Close';
import Select from '@mui/material/Select';
import MenuItem from '@mui/material/MenuItem';
import FormControl from '@mui/material/FormControl';
import InputLabel from '@mui/material/InputLabel';
import { useForm, Controller } from 'react-hook-form';
import { procurementsApi } from '@/lib/api';
import StatusChip from '@/components/common/StatusChip';
import type { ProcurementResponse, ProposalResponse } from '@/types/api';
import dayjs from 'dayjs';

const LAW_LABEL: Record<string, string> = { LAW_223: '223-ФЗ', LAW_44: '44-ФЗ' };
const fmt = (val?: string) =>
  val == null ? '—' : Number(val).toLocaleString('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 0 });

const STATUS_LABELS: Record<string, string> = {
  DRAFT: 'Черновик',
  PUBLISHED: 'Опубликован',
  EVALUATION: 'Оценка',
  AWARDED: 'Победитель выбран',
  CANCELED: 'Отменён',
};

interface ProposalForm {
  supplierName: string;
  offeredAmount: string;
  notes: string;
}

interface DecideForm {
  status: string;
  rejectionReason: string;
}

export default function ProcurementDetailPage() {
  const { id } = useParams<{ id: string }>();
  const [propOpen, setPropOpen] = useState(false);
  const [decideId, setDecideId] = useState<string | null>(null);
  const [error, setError] = useState('');

  const procKey = ['procurement', id];
  const propKey = ['proposals', id];

  const { data: procurement, isLoading } = useSWR<ProcurementResponse>(
    procKey,
    () => procurementsApi.get(id).then((r) => r.data),
  );
  const { data: proposals = [], mutate: mutateProps } = useSWR<ProposalResponse[]>(
    propKey,
    () => procurementsApi.listProposals(id).then((r) => r.data),
  );

  const propForm = useForm<ProposalForm>({
    defaultValues: { supplierName: '', offeredAmount: '', notes: '' },
  });
  const decideForm = useForm<DecideForm>({
    defaultValues: { status: 'ACCEPTED', rejectionReason: '' },
  });

  const watchStatus = decideForm.watch('status');

  const onAddProposal = async (values: ProposalForm) => {
    setError('');
    try {
      await procurementsApi.createProposal(id, {
        supplierName: values.supplierName,
        offeredAmount: values.offeredAmount,
        ...(values.notes && { notes: values.notes }),
      });
      await mutateProps();
      propForm.reset();
      setPropOpen(false);
    } catch {
      setError('Ошибка добавления КП');
    }
  };

  const onDecide = async (values: DecideForm) => {
    if (!decideId) return;
    setError('');
    try {
      await procurementsApi.decideProposal(id, decideId, {
        status: values.status,
        ...(values.rejectionReason && { rejectionReason: values.rejectionReason }),
      });
      await mutateProps();
      decideForm.reset();
      setDecideId(null);
    } catch (e: unknown) {
      const msg = (e as { response?: { data?: { message?: unknown } } })?.response?.data?.message;
      setError(Array.isArray(msg) ? msg.join(', ') : String(msg ?? 'Ошибка'));
    }
  };

  if (isLoading) return <Box sx={{ display: 'flex', justifyContent: 'center', mt: 8 }}><CircularProgress /></Box>;
  if (!procurement) return <Alert severity="error">Закупка не найдена</Alert>;

  return (
    <Box>
      <Breadcrumbs sx={{ mb: 2 }}>
        <Link href="/procurements" underline="hover" color="inherit">Закупки</Link>
        <Typography color="text.primary">{procurement.number}</Typography>
      </Breadcrumbs>

      <Box sx={{ display: 'flex', alignItems: 'flex-start', gap: 2, mb: 3 }}>
        <Box sx={{ flexGrow: 1 }}>
          <Typography variant="h5">{procurement.title}</Typography>
          <Box sx={{ display: 'flex', gap: 1, mt: 1, alignItems: 'center' }}>
            <StatusChip status={procurement.status} />
            <Chip label={LAW_LABEL[procurement.lawType]} size="small" variant="outlined" />
          </Box>
        </Box>
      </Box>

      <Paper sx={{ p: 3, mb: 3 }}>
        <Grid container spacing={4}>
          <Grid size={{ xs: 12, md: 6 }}>
            <Typography variant="subtitle2" gutterBottom>Информация</Typography>
            <Divider sx={{ mb: 1 }} />
            <Box sx={{ display: 'flex', py: 0.75 }}>
              <Typography variant="body2" color="text.secondary" sx={{ width: 160, flexShrink: 0 }}>Номер</Typography>
              <Typography variant="body2">{procurement.number}</Typography>
            </Box>
            <Box sx={{ display: 'flex', py: 0.75 }}>
              <Typography variant="body2" color="text.secondary" sx={{ width: 160, flexShrink: 0 }}>Тип закона</Typography>
              <Typography variant="body2">{LAW_LABEL[procurement.lawType]}</Typography>
            </Box>
            <Box sx={{ display: 'flex', py: 0.75 }}>
              <Typography variant="body2" color="text.secondary" sx={{ width: 160, flexShrink: 0 }}>Статус</Typography>
              <StatusChip status={procurement.status} />
            </Box>
            {procurement.description && (
              <Box sx={{ display: 'flex', py: 0.75 }}>
                <Typography variant="body2" color="text.secondary" sx={{ width: 160, flexShrink: 0 }}>Описание</Typography>
                <Typography variant="body2">{procurement.description}</Typography>
              </Box>
            )}
          </Grid>
          <Grid size={{ xs: 12, md: 6 }}>
            <Typography variant="subtitle2" gutterBottom>Переход по статусам</Typography>
            <Divider sx={{ mb: 1 }} />
            {Object.entries(STATUS_LABELS).map(([key, label]) => (
              <Box key={key} sx={{ display: 'flex', alignItems: 'center', gap: 1, py: 0.5 }}>
                <Chip
                  size="small"
                  label={label}
                  color={procurement.status === key ? 'primary' : 'default'}
                  variant={procurement.status === key ? 'filled' : 'outlined'}
                />
              </Box>
            ))}
          </Grid>
        </Grid>
      </Paper>

      {/* Proposals */}
      <Box sx={{ bgcolor: 'white', borderRadius: 1, border: '1px solid #e0e0e0', p: 2 }}>
        <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 1 }}>
          <Typography variant="subtitle1" fontWeight={600}>Коммерческие предложения</Typography>
          <Button size="small" startIcon={<AddIcon />} onClick={() => setPropOpen(true)}>Добавить КП</Button>
        </Box>

        {error && <Alert severity="error" sx={{ mb: 1 }}>{error}</Alert>}

        <Table size="small">
          <TableHead>
            <TableRow>
              <TableCell>Поставщик</TableCell>
              <TableCell align="right">Предложенная сумма</TableCell>
              <TableCell>Статус</TableCell>
              <TableCell>Дата подачи</TableCell>
              <TableCell>Примечание</TableCell>
              <TableCell />
            </TableRow>
          </TableHead>
          <TableBody>
            {proposals.length === 0 && (
              <TableRow>
                <TableCell colSpan={6} align="center">
                  <Typography variant="body2" color="text.secondary">КП не добавлены</Typography>
                </TableCell>
              </TableRow>
            )}
            {proposals.map((p) => (
              <TableRow key={p.id}>
                <TableCell>{p.supplierName}</TableCell>
                <TableCell align="right">{fmt(p.offeredAmount)}</TableCell>
                <TableCell><StatusChip status={p.status} /></TableCell>
                <TableCell>{p.submittedAt ? dayjs(p.submittedAt).format('DD.MM.YYYY') : '—'}</TableCell>
                <TableCell>{p.notes ?? '—'}</TableCell>
                <TableCell>
                  {p.status === 'PENDING' && (
                    <Box sx={{ display: 'flex', gap: 0.5 }}>
                      <Button
                        size="small"
                        color="success"
                        startIcon={<CheckIcon />}
                        onClick={() => { setDecideId(p.id); decideForm.setValue('status', 'ACCEPTED'); }}
                      >
                        Принять
                      </Button>
                      <Button
                        size="small"
                        color="error"
                        startIcon={<CloseIcon />}
                        onClick={() => { setDecideId(p.id); decideForm.setValue('status', 'REJECTED'); }}
                      >
                        Отклонить
                      </Button>
                    </Box>
                  )}
                  {p.status !== 'PENDING' && p.rejectionReason && (
                    <Typography variant="caption" color="text.secondary">{p.rejectionReason}</Typography>
                  )}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </Box>

      {/* Add Proposal Dialog */}
      <Dialog open={propOpen} onClose={() => setPropOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Добавить коммерческое предложение</DialogTitle>
        <form onSubmit={propForm.handleSubmit(onAddProposal)}>
          <DialogContent sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
            <TextField
              {...propForm.register('supplierName', { required: 'Обязательное поле' })}
              label="Поставщик"
              size="small"
              fullWidth
              error={!!propForm.formState.errors.supplierName}
              helperText={propForm.formState.errors.supplierName?.message}
            />
            <TextField
              {...propForm.register('offeredAmount', { required: 'Обязательное поле' })}
              label="Предложенная сумма, ₽"
              size="small"
              fullWidth
              placeholder="4800000.00"
            />
            <TextField {...propForm.register('notes')} label="Примечание" size="small" fullWidth multiline rows={2} />
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setPropOpen(false)}>Отмена</Button>
            <Button type="submit" variant="contained" disabled={propForm.formState.isSubmitting}>Добавить</Button>
          </DialogActions>
        </form>
      </Dialog>

      {/* Decide Dialog */}
      <Dialog open={!!decideId} onClose={() => setDecideId(null)} maxWidth="xs" fullWidth>
        <DialogTitle>Решение по КП</DialogTitle>
        <form onSubmit={decideForm.handleSubmit(onDecide)}>
          <DialogContent sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
            <Controller
              name="status"
              control={decideForm.control}
              render={({ field }) => (
                <FormControl size="small" fullWidth>
                  <InputLabel>Решение</InputLabel>
                  <Select {...field} label="Решение">
                    <MenuItem value="ACCEPTED">Принять</MenuItem>
                    <MenuItem value="REJECTED">Отклонить</MenuItem>
                  </Select>
                </FormControl>
              )}
            />
            {watchStatus === 'REJECTED' && (
              <TextField
                {...decideForm.register('rejectionReason', { required: 'Укажите причину отклонения' })}
                label="Причина отклонения"
                size="small"
                fullWidth
                multiline
                rows={2}
                error={!!decideForm.formState.errors.rejectionReason}
                helperText={decideForm.formState.errors.rejectionReason?.message}
              />
            )}
            {error && <Alert severity="error">{error}</Alert>}
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setDecideId(null)}>Отмена</Button>
            <Button type="submit" variant="contained" disabled={decideForm.formState.isSubmitting}>Подтвердить</Button>
          </DialogActions>
        </form>
      </Dialog>
    </Box>
  );
}
