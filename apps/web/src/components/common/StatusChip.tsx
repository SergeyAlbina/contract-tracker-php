'use client';
import Chip, { ChipProps } from '@mui/material/Chip';
import {
  ContractStatus,
  ProcurementStatus,
  ProposalStatus,
  StageStatus,
  PaymentStatus,
} from '@/types/api';

type Status =
  | ContractStatus
  | ProcurementStatus
  | ProposalStatus
  | StageStatus
  | PaymentStatus;

const LABEL: Record<string, string> = {
  // Contract
  DRAFT: 'Черновик',
  ACTIVE: 'Активен',
  SUSPENDED: 'Приостановлен',
  COMPLETED: 'Завершён',
  TERMINATED: 'Расторгнут',
  ARCHIVED: 'В архиве',
  // Procurement
  PUBLISHED: 'Опубликован',
  EVALUATION: 'Оценка',
  AWARDED: 'Победитель выбран',
  CANCELED: 'Отменён',
  // Proposal
  PENDING: 'Ожидает',
  ACCEPTED: 'Принято',
  REJECTED: 'Отклонено',
  // Stage
  PLANNED: 'Планируется',
  IN_PROGRESS: 'В работе',
  OVERDUE: 'Просрочен',
  // Payment
  PAID: 'Оплачено',
};

const COLOR: Record<string, ChipProps['color']> = {
  DRAFT: 'default',
  ACTIVE: 'success',
  SUSPENDED: 'warning',
  COMPLETED: 'info',
  TERMINATED: 'error',
  ARCHIVED: 'default',
  PUBLISHED: 'primary',
  EVALUATION: 'secondary',
  AWARDED: 'success',
  CANCELED: 'error',
  PENDING: 'warning',
  ACCEPTED: 'success',
  REJECTED: 'error',
  PLANNED: 'default',
  IN_PROGRESS: 'primary',
  OVERDUE: 'error',
  PAID: 'success',
};

interface Props {
  status: Status;
  size?: ChipProps['size'];
}

export default function StatusChip({ status, size = 'small' }: Props) {
  return (
    <Chip
      label={LABEL[status] ?? status}
      color={COLOR[status] ?? 'default'}
      size={size}
    />
  );
}
