import {
  ContractStatus,
  LawType,
  PaymentStatus,
  ProcurementStatus,
  ProposalStatus,
  RiskFlag,
  StageStatus,
  UserRole,
} from '@ct/shared';

export { ContractStatus, LawType, PaymentStatus, ProcurementStatus, ProposalStatus, RiskFlag, StageStatus, UserRole };

export interface Paginated<T> {
  data: T[];
  total: number;
  page: number;
  limit: number;
}

export interface MeResponse {
  id: string;
  email: string;
  fullName: string;
  role: UserRole;
  isActive: boolean;
  createdAt: string;
}

export interface ContractResponse {
  id: string;
  number: string;
  title: string;
  lawType: LawType;
  status: ContractStatus;
  supplierName: string;
  supplierInn?: string;
  totalAmount: string;
  nmckAmount?: string;
  signedAt?: string;
  startDate?: string;
  endDate?: string;
  description?: string;
  ownerId: string;
  procurementId?: string;
  balance: string;
  riskFlags: RiskFlag[];
  createdAt: string;
  updatedAt: string;
}

export interface StageResponse {
  id: string;
  contractId: string;
  title: string;
  status: StageStatus;
  plannedStart: string;
  plannedEnd: string;
  actualStart?: string;
  actualEnd?: string;
  amount?: string;
  description?: string;
  createdAt: string;
  updatedAt: string;
}

export interface InvoiceResponse {
  id: string;
  contractId: string;
  stageId?: string;
  number: string;
  amount: string;
  issuedAt: string;
  dueAt?: string;
  paidAt?: string;
  notes?: string;
  createdAt: string;
  updatedAt: string;
}

export interface PaymentResponse {
  id: string;
  contractId: string;
  invoiceId?: string;
  amount: string;
  status: PaymentStatus;
  plannedAt?: string;
  paidAt?: string;
  reference?: string;
  notes?: string;
  createdAt: string;
  updatedAt: string;
}

export interface ProcurementResponse {
  id: string;
  number: string;
  title: string;
  lawType: LawType;
  status: ProcurementStatus;
  description?: string;
  plannedDate?: string;
  ownerId: string;
  createdAt: string;
  updatedAt: string;
}

export interface ProposalResponse {
  id: string;
  procurementId: string;
  supplierName: string;
  offeredAmount: string;
  status: ProposalStatus;
  notes?: string;
  rejectionReason?: string;
  submittedAt?: string;
  decidedAt?: string;
  createdAt: string;
  updatedAt: string;
}

export interface UserResponse {
  id: string;
  email: string;
  fullName: string;
  role: UserRole;
  isActive: boolean;
  createdAt: string;
  updatedAt: string;
}

export interface TokenResponse {
  accessToken: string;
  refreshToken: string;
}
