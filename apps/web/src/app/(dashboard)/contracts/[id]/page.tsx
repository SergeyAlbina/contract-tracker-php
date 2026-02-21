'use client';
import { useState } from 'react';
import useSWR, { mutate } from 'swr';
import { useParams, useRouter } from 'next/navigation';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Breadcrumbs from '@mui/material/Breadcrumbs';
import Link from '@mui/material/Link';
import Paper from '@mui/material/Paper';
import Grid from '@mui/material/Grid';
import Tabs from '@mui/material/Tabs';
import Tab from '@mui/material/Tab';
import Chip from '@mui/material/Chip';
import Divider from '@mui/material/Divider';
import Button from '@mui/material/Button';
import Alert from '@mui/material/Alert';
import CircularProgress from '@mui/material/CircularProgress';
import EditIcon from '@mui/icons-material/Edit';
import { contractsApi, stagesApi, paymentsApi } from '@/lib/api';
import StatusChip from '@/components/common/StatusChip';
import RiskChips from '@/components/common/RiskChip';
import ContractForm from '@/components/contracts/ContractForm';
import StagesPanel from '@/components/contracts/StagesPanel';
import PaymentsPanel from '@/components/contracts/PaymentsPanel';
import { getUiErrorMessage } from '@/lib/error-message';
import type { ContractResponse } from '@/types/api';
import dayjs from 'dayjs';

const fmt = (val?: string) =>
  val == null
    ? '—'
    : Number(val).toLocaleString('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 0 });

const fmtDate = (val?: string) => (val ? dayjs(val).format('DD.MM.YYYY') : '—');

const LAW_LABEL: Record<string, string> = { LAW_223: '223-ФЗ', LAW_44: '44-ФЗ' };

function InfoRow({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <Box sx={{ display: 'flex', py: 0.75 }}>
      <Typography variant="body2" color="text.secondary" sx={{ width: 200, flexShrink: 0 }}>{label}</Typography>
      <Box>{children}</Box>
    </Box>
  );
}

export default function ContractDetailPage() {
  const { id } = useParams<{ id: string }>();
  const router = useRouter();
  const [tab, setTab] = useState(0);
  const [editing, setEditing] = useState(false);
  const [saveError, setSaveError] = useState('');

  const contractKey = ['contract', id];
  const { data: contract, isLoading } = useSWR<ContractResponse>(
    contractKey,
    () => contractsApi.get(id).then((r) => r.data),
  );

  const handleEdit = async (data: Record<string, unknown>) => {
    setSaveError('');
    try {
      await contractsApi.update(id, data);
      await mutate(contractKey);
      setEditing(false);
    } catch (e: unknown) {
      setSaveError(getUiErrorMessage(e, 'Ошибка сохранения'));
    }
  };

  if (isLoading) return <Box sx={{ display: 'flex', justifyContent: 'center', mt: 8 }}><CircularProgress /></Box>;
  if (!contract) return <Alert severity="error">Контракт не найден</Alert>;

  return (
    <Box>
      <Breadcrumbs sx={{ mb: 2 }}>
        <Link href="/contracts" underline="hover" color="inherit">Контракты</Link>
        <Typography color="text.primary">{contract.number}</Typography>
      </Breadcrumbs>

      <Box sx={{ display: 'flex', alignItems: 'flex-start', gap: 2, mb: 3, flexWrap: 'wrap' }}>
        <Box sx={{ flexGrow: 1 }}>
          <Typography variant="h5">{contract.title}</Typography>
          <Box sx={{ display: 'flex', gap: 1, mt: 1, flexWrap: 'wrap', alignItems: 'center' }}>
            <StatusChip status={contract.status} />
            <Chip label={LAW_LABEL[contract.lawType] ?? 'Неизвестный закон'} size="small" variant="outlined" />
            <RiskChips flags={contract.riskFlags} />
          </Box>
        </Box>
        <Button
          startIcon={<EditIcon />}
          variant={editing ? 'contained' : 'outlined'}
          onClick={() => setEditing(!editing)}
        >
          {editing ? 'Отмена' : 'Редактировать'}
        </Button>
      </Box>

      {editing ? (
        <Paper sx={{ p: 3, mb: 3 }}>
          {saveError && <Alert severity="error" sx={{ mb: 2 }}>{saveError}</Alert>}
          <ContractForm initial={contract} onSubmit={handleEdit} submitLabel="Сохранить изменения" />
        </Paper>
      ) : (
        <Paper sx={{ p: 3, mb: 3 }}>
          <Grid container spacing={4}>
            <Grid size={{ xs: 12, md: 6 }}>
              <Typography variant="subtitle2" gutterBottom>Основная информация</Typography>
              <Divider sx={{ mb: 1 }} />
              <InfoRow label="Номер">{contract.number}</InfoRow>
              <InfoRow label="Поставщик">{contract.supplierName}</InfoRow>
              {contract.supplierInn && <InfoRow label="ИНН">{contract.supplierInn}</InfoRow>}
              <InfoRow label="Тип закона">{LAW_LABEL[contract.lawType]}</InfoRow>
              {contract.description && <InfoRow label="Описание">{contract.description}</InfoRow>}
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}>
              <Typography variant="subtitle2" gutterBottom>Финансы и даты</Typography>
              <Divider sx={{ mb: 1 }} />
              <InfoRow label="Сумма контракта">
                <Typography variant="body2" fontWeight={600}>{fmt(contract.totalAmount)}</Typography>
              </InfoRow>
              {contract.nmckAmount && <InfoRow label="НМЦК">{fmt(contract.nmckAmount)}</InfoRow>}
              <InfoRow label="Остаток бюджета">
                <Typography
                  variant="body2"
                  fontWeight={600}
                  color={Number(contract.balance) < 0 ? 'error.main' : 'success.main'}
                >
                  {fmt(contract.balance)}
                </Typography>
              </InfoRow>
              <InfoRow label="Дата подписания">{fmtDate(contract.signedAt)}</InfoRow>
              <InfoRow label="Дата начала">{fmtDate(contract.startDate)}</InfoRow>
              <InfoRow label="Дата окончания">{fmtDate(contract.endDate)}</InfoRow>
            </Grid>
          </Grid>
        </Paper>
      )}

      <Box sx={{ bgcolor: 'white', borderRadius: 1, border: '1px solid #e0e0e0' }}>
        <Tabs value={tab} onChange={(_, v) => setTab(v)} sx={{ px: 2 }}>
          <Tab label="Этапы" />
          <Tab label="Счета и оплаты" />
        </Tabs>
        <Divider />
        <Box sx={{ p: 2 }}>
          {tab === 0 && <StagesPanel contractId={id} />}
          {tab === 1 && <PaymentsPanel contractId={id} />}
        </Box>
      </Box>
    </Box>
  );
}
