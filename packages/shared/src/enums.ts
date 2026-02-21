// --- Законодательство ---
export enum LawType {
  LAW_223 = 'LAW_223',
  LAW_44 = 'LAW_44',
}

// --- Закупки ---
export enum ProcurementStatus {
  DRAFT = 'DRAFT',
  PUBLISHED = 'PUBLISHED',
  EVALUATION = 'EVALUATION',
  AWARDED = 'AWARDED',
  CANCELED = 'CANCELED',
}

// --- КП (Коммерческие предложения) ---
export enum ProposalStatus {
  PENDING = 'PENDING',
  ACCEPTED = 'ACCEPTED',
  REJECTED = 'REJECTED',
}

// --- Контракты ---
export enum ContractStatus {
  DRAFT = 'DRAFT',
  ACTIVE = 'ACTIVE',
  SUSPENDED = 'SUSPENDED',
  COMPLETED = 'COMPLETED',
  TERMINATED = 'TERMINATED',
  ARCHIVED = 'ARCHIVED',
}

// --- Этапы ---
export enum StageStatus {
  PLANNED = 'PLANNED',
  IN_PROGRESS = 'IN_PROGRESS',
  COMPLETED = 'COMPLETED',
  OVERDUE = 'OVERDUE',
}

// --- Оплаты ---
export enum PaymentStatus {
  PLANNED = 'PLANNED',
  IN_PROGRESS = 'IN_PROGRESS',
  PAID = 'PAID',
  CANCELED = 'CANCELED',
}

// --- Документы ---
export enum DocumentAccess {
  PRIVATE = 'PRIVATE',
  INTERNAL = 'INTERNAL',
}

// --- RBAC ---
export enum UserRole {
  ADMIN = 'ADMIN',
  HEAD_CS = 'HEAD_CS',
  SPECIALIST_CS = 'SPECIALIST_CS',
}

// --- Флаги риска ---
export enum RiskFlag {
  EXPIRING_90 = 'EXPIRING_90',
  EXPIRING_30 = 'EXPIRING_30',
  EXPIRING_10 = 'EXPIRING_10',
  OVERSPEND = 'OVERSPEND',
  OVERDUE_STAGE = 'OVERDUE_STAGE',
  MISSING_DOCS = 'MISSING_DOCS',
}
