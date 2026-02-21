'use client';
import { useState, useCallback } from 'react';
import useSWR from 'swr';
import { useRouter } from 'next/navigation';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import TextField from '@mui/material/TextField';
import Select from '@mui/material/Select';
import MenuItem from '@mui/material/MenuItem';
import FormControl from '@mui/material/FormControl';
import InputLabel from '@mui/material/InputLabel';
import Typography from '@mui/material/Typography';
import AddIcon from '@mui/icons-material/Add';
import { DataGrid, GridColDef, GridRenderCellParams, GridPaginationModel } from '@mui/x-data-grid';
import dayjs from 'dayjs';
import { contractsApi } from '@/lib/api';
import StatusChip from '@/components/common/StatusChip';
import RiskChips from '@/components/common/RiskChip';
import type { ContractResponse, ContractStatus, LawType, RiskFlag } from '@/types/api';

const LAW_LABEL: Record<string, string> = { LAW_223: '223-ФЗ', LAW_44: '44-ФЗ' };

const fmt = (val: string) =>
  Number(val).toLocaleString('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 0 });

export default function ContractsPage() {
  const router = useRouter();
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [lawType, setLawType] = useState('');
  const [page, setPage] = useState(0);
  const [pageSize, setPageSize] = useState(20);

  const params = {
    page: page + 1,
    limit: pageSize,
    ...(search && { search }),
    ...(status && { status }),
    ...(lawType && { lawType }),
  };

  const { data, isLoading } = useSWR(
    ['contracts', params],
    () => contractsApi.list(params).then((r) => r.data),
  );

  const rows: ContractResponse[] = data?.data ?? [];
  const total: number = data?.total ?? 0;

  const handlePagination = useCallback((model: GridPaginationModel) => {
    setPage(model.page);
    setPageSize(model.pageSize);
  }, []);

  const columns: GridColDef<ContractResponse>[] = [
    { field: 'number', headerName: 'Номер', width: 130 },
    { field: 'title', headerName: 'Название', flex: 1, minWidth: 200 },
    {
      field: 'lawType',
      headerName: 'Закон',
      width: 90,
      renderCell: (p: GridRenderCellParams<ContractResponse, LawType>) =>
        LAW_LABEL[p.value ?? ''] ?? p.value,
    },
    {
      field: 'status',
      headerName: 'Статус',
      width: 130,
      renderCell: (p: GridRenderCellParams<ContractResponse, ContractStatus>) =>
        p.value ? <StatusChip status={p.value} /> : null,
    },
    { field: 'supplierName', headerName: 'Поставщик', width: 180 },
    {
      field: 'totalAmount',
      headerName: 'Сумма',
      width: 140,
      align: 'right',
      headerAlign: 'right',
      renderCell: (p) => fmt(p.value as string),
    },
    {
      field: 'balance',
      headerName: 'Остаток',
      width: 140,
      align: 'right',
      headerAlign: 'right',
      renderCell: (p) => {
        const val = Number(p.value as string);
        return (
          <Typography variant="body2" color={val < 0 ? 'error' : 'text.primary'}>
            {fmt(p.value as string)}
          </Typography>
        );
      },
    },
    {
      field: 'endDate',
      headerName: 'Дата окончания',
      width: 140,
      renderCell: (p) => p.value ? dayjs(p.value as string).format('DD.MM.YYYY') : '—',
    },
    {
      field: 'riskFlags',
      headerName: 'Риски',
      width: 220,
      sortable: false,
      renderCell: (p: GridRenderCellParams<ContractResponse, RiskFlag[]>) =>
        <RiskChips flags={p.value ?? []} />,
    },
  ];

  return (
    <Box>
      <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', mb: 2 }}>
        <Typography variant="h5">Контракты</Typography>
        <Button variant="contained" startIcon={<AddIcon />} onClick={() => router.push('/contracts/new')}>
          Новый контракт
        </Button>
      </Box>

      {/* Фильтры */}
      <Box sx={{ display: 'flex', gap: 2, mb: 2, flexWrap: 'wrap' }}>
        <TextField
          size="small"
          placeholder="Поиск по номеру, названию, поставщику"
          value={search}
          onChange={(e) => { setSearch(e.target.value); setPage(0); }}
          sx={{ width: 320 }}
        />
        <FormControl size="small" sx={{ minWidth: 160 }}>
          <InputLabel>Статус</InputLabel>
          <Select value={status} label="Статус" onChange={(e) => { setStatus(e.target.value); setPage(0); }}>
            <MenuItem value="">Все</MenuItem>
            {['DRAFT', 'ACTIVE', 'SUSPENDED', 'COMPLETED', 'TERMINATED', 'ARCHIVED'].map((s) => (
              <MenuItem key={s} value={s}><StatusChip status={s as ContractStatus} /></MenuItem>
            ))}
          </Select>
        </FormControl>
        <FormControl size="small" sx={{ minWidth: 120 }}>
          <InputLabel>Закон</InputLabel>
          <Select value={lawType} label="Закон" onChange={(e) => { setLawType(e.target.value); setPage(0); }}>
            <MenuItem value="">Все</MenuItem>
            <MenuItem value="LAW_223">223-ФЗ</MenuItem>
            <MenuItem value="LAW_44">44-ФЗ</MenuItem>
          </Select>
        </FormControl>
      </Box>

      <DataGrid
        rows={rows}
        columns={columns}
        loading={isLoading}
        rowCount={total}
        paginationMode="server"
        paginationModel={{ page, pageSize }}
        onPaginationModelChange={handlePagination}
        pageSizeOptions={[10, 20, 50]}
        onRowClick={(p) => router.push(`/contracts/${p.id}`)}
        sx={{ bgcolor: 'white', cursor: 'pointer', height: 600 }}
        disableRowSelectionOnClick
      />
    </Box>
  );
}
