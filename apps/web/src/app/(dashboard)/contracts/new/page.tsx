'use client';
import { useRouter } from 'next/navigation';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Breadcrumbs from '@mui/material/Breadcrumbs';
import Link from '@mui/material/Link';
import Paper from '@mui/material/Paper';
import Alert from '@mui/material/Alert';
import { useState } from 'react';
import ContractForm from '@/components/contracts/ContractForm';
import { contractsApi } from '@/lib/api';
import { getUiErrorMessage } from '@/lib/error-message';

export default function NewContractPage() {
  const router = useRouter();
  const [error, setError] = useState('');

  const handleSubmit = async (data: Record<string, unknown>) => {
    setError('');
    try {
      const { data: created } = await contractsApi.create(data);
      router.push(`/contracts/${created.id}`);
    } catch (e: unknown) {
      setError(getUiErrorMessage(e, 'Ошибка создания контракта'));
    }
  };

  return (
    <Box>
      <Breadcrumbs sx={{ mb: 2 }}>
        <Link href="/contracts" underline="hover" color="inherit">Контракты</Link>
        <Typography color="text.primary">Новый контракт</Typography>
      </Breadcrumbs>
      <Typography variant="h5" sx={{ mb: 3 }}>Новый контракт</Typography>
      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
      <Paper sx={{ p: 3 }}>
        <ContractForm onSubmit={handleSubmit} submitLabel="Создать контракт" />
      </Paper>
    </Box>
  );
}
