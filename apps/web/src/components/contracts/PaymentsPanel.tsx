'use client';
import { useState } from 'react';
import useSWR from 'swr';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Typography from '@mui/material/Typography';
import Tabs from '@mui/material/Tabs';
import Tab from '@mui/material/Tab';
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
import Select from '@mui/material/Select';
import MenuItem from '@mui/material/MenuItem';
import FormControl from '@mui/material/FormControl';
import InputLabel from '@mui/material/InputLabel';
import Alert from '@mui/material/Alert';
import AddIcon from '@mui/icons-material/Add';
import { useForm, Controller } from 'react-hook-form';
import { DatePicker } from '@mui/x-date-pickers/DatePicker';
import { LocalizationProvider } from '@mui/x-date-pickers/LocalizationProvider';
import { AdapterDayjs } from '@mui/x-date-pickers/AdapterDayjs';
import dayjs, { Dayjs } from 'dayjs';
import 'dayjs/locale/ru';
import { paymentsApi } from '@/lib/api';
import StatusChip from '@/components/common/StatusChip';
import type { InvoiceResponse, PaymentResponse } from '@/types/api';

dayjs.locale('ru');

const fmt = (val?: string) =>
  val == null ? '—' : Number(val).toLocaleString('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 0 });

interface Props { contractId: string }

interface InvoiceForm {
  number: string;
  amount: string;
  issuedAt: Dayjs | null;
  dueAt: Dayjs | null;
  notes: string;
}

interface PaymentForm {
  amount: string;
  status: string;
  plannedAt: Dayjs | null;
  reference: string;
  notes: string;
}

export default function PaymentsPanel({ contractId }: Props) {
  const [subTab, setSubTab] = useState(0);
  const [invoiceOpen, setInvoiceOpen] = useState(false);
  const [paymentOpen, setPaymentOpen] = useState(false);
  const [error, setError] = useState('');

  const invoiceKey = ['invoices', contractId];
  const paymentKey = ['payments', contractId];

  const { data: invoices = [], mutate: mutateInvoices } = useSWR<InvoiceResponse[]>(
    invoiceKey,
    () => paymentsApi.listInvoices(contractId).then((r) => r.data),
  );
  const { data: payments = [], mutate: mutatePayments } = useSWR<PaymentResponse[]>(
    paymentKey,
    () => paymentsApi.listPayments(contractId).then((r) => r.data),
  );

  const invoiceForm = useForm<InvoiceForm>({
    defaultValues: { number: '', amount: '', issuedAt: null, dueAt: null, notes: '' },
  });
  const paymentForm = useForm<PaymentForm>({
    defaultValues: { amount: '', status: 'PLANNED', plannedAt: null, reference: '', notes: '' },
  });

  const onAddInvoice = async (values: InvoiceForm) => {
    setError('');
    try {
      await paymentsApi.createInvoice(contractId, {
        number: values.number,
        amount: values.amount,
        issuedAt: values.issuedAt?.toISOString(),
        ...(values.dueAt && { dueAt: values.dueAt.toISOString() }),
        ...(values.notes && { notes: values.notes }),
      });
      await mutateInvoices();
      invoiceForm.reset();
      setInvoiceOpen(false);
    } catch {
      setError('Ошибка создания счёта');
    }
  };

  const onAddPayment = async (values: PaymentForm) => {
    setError('');
    try {
      await paymentsApi.createPayment(contractId, {
        amount: values.amount,
        status: values.status,
        ...(values.plannedAt && { plannedAt: values.plannedAt.toISOString() }),
        ...(values.reference && { reference: values.reference }),
        ...(values.notes && { notes: values.notes }),
      });
      await mutatePayments();
      paymentForm.reset();
      setPaymentOpen(false);
    } catch {
      setError('Ошибка создания платежа');
    }
  };

  return (
    <LocalizationProvider dateAdapter={AdapterDayjs} adapterLocale="ru">
      {error && <Alert severity="error" sx={{ mb: 1 }}>{error}</Alert>}
      <Tabs value={subTab} onChange={(_, v) => setSubTab(v)} sx={{ mb: 1 }}>
        <Tab label={`Счета (${invoices.length})`} />
        <Tab label={`Платежи (${payments.length})`} />
      </Tabs>

      {subTab === 0 && (
        <>
          <Box sx={{ display: 'flex', justifyContent: 'flex-end', mb: 1 }}>
            <Button size="small" startIcon={<AddIcon />} onClick={() => setInvoiceOpen(true)}>Добавить счёт</Button>
          </Box>
          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell>Номер</TableCell>
                <TableCell>Дата выставления</TableCell>
                <TableCell>Срок оплаты</TableCell>
                <TableCell align="right">Сумма</TableCell>
                <TableCell>Оплачен</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {invoices.length === 0 && (
                <TableRow>
                  <TableCell colSpan={5} align="center">
                    <Typography variant="body2" color="text.secondary">Счета не добавлены</Typography>
                  </TableCell>
                </TableRow>
              )}
              {invoices.map((inv) => (
                <TableRow key={inv.id}>
                  <TableCell>{inv.number}</TableCell>
                  <TableCell>{dayjs(inv.issuedAt).format('DD.MM.YYYY')}</TableCell>
                  <TableCell>{inv.dueAt ? dayjs(inv.dueAt).format('DD.MM.YYYY') : '—'}</TableCell>
                  <TableCell align="right">{fmt(inv.amount)}</TableCell>
                  <TableCell>{inv.paidAt ? dayjs(inv.paidAt).format('DD.MM.YYYY') : '—'}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </>
      )}

      {subTab === 1 && (
        <>
          <Box sx={{ display: 'flex', justifyContent: 'flex-end', mb: 1 }}>
            <Button size="small" startIcon={<AddIcon />} onClick={() => setPaymentOpen(true)}>Добавить платёж</Button>
          </Box>
          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell>Статус</TableCell>
                <TableCell align="right">Сумма</TableCell>
                <TableCell>Плановая дата</TableCell>
                <TableCell>Дата оплаты</TableCell>
                <TableCell>Платёжное поручение</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {payments.length === 0 && (
                <TableRow>
                  <TableCell colSpan={5} align="center">
                    <Typography variant="body2" color="text.secondary">Платежи не добавлены</Typography>
                  </TableCell>
                </TableRow>
              )}
              {payments.map((p) => (
                <TableRow key={p.id}>
                  <TableCell><StatusChip status={p.status} /></TableCell>
                  <TableCell align="right">{fmt(p.amount)}</TableCell>
                  <TableCell>{p.plannedAt ? dayjs(p.plannedAt).format('DD.MM.YYYY') : '—'}</TableCell>
                  <TableCell>{p.paidAt ? dayjs(p.paidAt).format('DD.MM.YYYY') : '—'}</TableCell>
                  <TableCell>{p.reference ?? '—'}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </>
      )}

      {/* Invoice Dialog */}
      <Dialog open={invoiceOpen} onClose={() => setInvoiceOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Добавить счёт</DialogTitle>
        <form onSubmit={invoiceForm.handleSubmit(onAddInvoice)}>
          <DialogContent sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
            <TextField
              {...invoiceForm.register('number', { required: 'Обязательное поле' })}
              label="Номер счёта"
              size="small"
              fullWidth
              error={!!invoiceForm.formState.errors.number}
              helperText={invoiceForm.formState.errors.number?.message}
            />
            <TextField
              {...invoiceForm.register('amount', { required: 'Обязательное поле' })}
              label="Сумма, ₽"
              size="small"
              fullWidth
              placeholder="100000.00"
            />
            <Controller
              name="issuedAt"
              control={invoiceForm.control}
              rules={{ required: 'Обязательное поле' }}
              render={({ field, fieldState }) => (
                <DatePicker
                  label="Дата выставления"
                  value={field.value}
                  onChange={field.onChange}
                  slotProps={{ textField: { size: 'small', fullWidth: true, error: !!fieldState.error, helperText: fieldState.error?.message } }}
                />
              )}
            />
            <Controller
              name="dueAt"
              control={invoiceForm.control}
              render={({ field }) => (
                <DatePicker
                  label="Срок оплаты"
                  value={field.value}
                  onChange={field.onChange}
                  slotProps={{ textField: { size: 'small', fullWidth: true } }}
                />
              )}
            />
            <TextField {...invoiceForm.register('notes')} label="Примечание" size="small" fullWidth multiline rows={2} />
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setInvoiceOpen(false)}>Отмена</Button>
            <Button type="submit" variant="contained" disabled={invoiceForm.formState.isSubmitting}>Добавить</Button>
          </DialogActions>
        </form>
      </Dialog>

      {/* Payment Dialog */}
      <Dialog open={paymentOpen} onClose={() => setPaymentOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Добавить платёж</DialogTitle>
        <form onSubmit={paymentForm.handleSubmit(onAddPayment)}>
          <DialogContent sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
            <TextField
              {...paymentForm.register('amount', { required: 'Обязательное поле' })}
              label="Сумма, ₽"
              size="small"
              fullWidth
              placeholder="100000.00"
            />
            <Controller
              name="status"
              control={paymentForm.control}
              render={({ field }) => (
                <FormControl size="small" fullWidth>
                  <InputLabel>Статус</InputLabel>
                  <Select {...field} label="Статус">
                    <MenuItem value="PLANNED">Планируется</MenuItem>
                    <MenuItem value="IN_PROGRESS">В обработке</MenuItem>
                    <MenuItem value="PAID">Оплачено</MenuItem>
                    <MenuItem value="CANCELED">Отменён</MenuItem>
                  </Select>
                </FormControl>
              )}
            />
            <Controller
              name="plannedAt"
              control={paymentForm.control}
              render={({ field }) => (
                <DatePicker
                  label="Плановая дата"
                  value={field.value}
                  onChange={field.onChange}
                  slotProps={{ textField: { size: 'small', fullWidth: true } }}
                />
              )}
            />
            <TextField {...paymentForm.register('reference')} label="Платёжное поручение №" size="small" fullWidth />
            <TextField {...paymentForm.register('notes')} label="Примечание" size="small" fullWidth multiline rows={2} />
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setPaymentOpen(false)}>Отмена</Button>
            <Button type="submit" variant="contained" disabled={paymentForm.formState.isSubmitting}>Добавить</Button>
          </DialogActions>
        </form>
      </Dialog>
    </LocalizationProvider>
  );
}
