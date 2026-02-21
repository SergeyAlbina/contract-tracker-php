"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.RiskFlag = exports.UserRole = exports.DocumentAccess = exports.PaymentStatus = exports.StageStatus = exports.ContractStatus = exports.ProposalStatus = exports.ProcurementStatus = exports.LawType = void 0;
// --- Законодательство ---
var LawType;
(function (LawType) {
    LawType["LAW_223"] = "LAW_223";
    LawType["LAW_44"] = "LAW_44";
})(LawType || (exports.LawType = LawType = {}));
// --- Закупки ---
var ProcurementStatus;
(function (ProcurementStatus) {
    ProcurementStatus["DRAFT"] = "DRAFT";
    ProcurementStatus["PUBLISHED"] = "PUBLISHED";
    ProcurementStatus["EVALUATION"] = "EVALUATION";
    ProcurementStatus["AWARDED"] = "AWARDED";
    ProcurementStatus["CANCELED"] = "CANCELED";
})(ProcurementStatus || (exports.ProcurementStatus = ProcurementStatus = {}));
// --- КП (Коммерческие предложения) ---
var ProposalStatus;
(function (ProposalStatus) {
    ProposalStatus["PENDING"] = "PENDING";
    ProposalStatus["ACCEPTED"] = "ACCEPTED";
    ProposalStatus["REJECTED"] = "REJECTED";
})(ProposalStatus || (exports.ProposalStatus = ProposalStatus = {}));
// --- Контракты ---
var ContractStatus;
(function (ContractStatus) {
    ContractStatus["DRAFT"] = "DRAFT";
    ContractStatus["ACTIVE"] = "ACTIVE";
    ContractStatus["SUSPENDED"] = "SUSPENDED";
    ContractStatus["COMPLETED"] = "COMPLETED";
    ContractStatus["TERMINATED"] = "TERMINATED";
    ContractStatus["ARCHIVED"] = "ARCHIVED";
})(ContractStatus || (exports.ContractStatus = ContractStatus = {}));
// --- Этапы ---
var StageStatus;
(function (StageStatus) {
    StageStatus["PLANNED"] = "PLANNED";
    StageStatus["IN_PROGRESS"] = "IN_PROGRESS";
    StageStatus["COMPLETED"] = "COMPLETED";
    StageStatus["OVERDUE"] = "OVERDUE";
})(StageStatus || (exports.StageStatus = StageStatus = {}));
// --- Оплаты ---
var PaymentStatus;
(function (PaymentStatus) {
    PaymentStatus["PLANNED"] = "PLANNED";
    PaymentStatus["IN_PROGRESS"] = "IN_PROGRESS";
    PaymentStatus["PAID"] = "PAID";
    PaymentStatus["CANCELED"] = "CANCELED";
})(PaymentStatus || (exports.PaymentStatus = PaymentStatus = {}));
// --- Документы ---
var DocumentAccess;
(function (DocumentAccess) {
    DocumentAccess["PRIVATE"] = "PRIVATE";
    DocumentAccess["INTERNAL"] = "INTERNAL";
})(DocumentAccess || (exports.DocumentAccess = DocumentAccess = {}));
// --- RBAC ---
var UserRole;
(function (UserRole) {
    UserRole["ADMIN"] = "ADMIN";
    UserRole["HEAD_CS"] = "HEAD_CS";
    UserRole["SPECIALIST_CS"] = "SPECIALIST_CS";
})(UserRole || (exports.UserRole = UserRole = {}));
// --- Флаги риска ---
var RiskFlag;
(function (RiskFlag) {
    RiskFlag["EXPIRING_90"] = "EXPIRING_90";
    RiskFlag["EXPIRING_30"] = "EXPIRING_30";
    RiskFlag["EXPIRING_10"] = "EXPIRING_10";
    RiskFlag["OVERSPEND"] = "OVERSPEND";
    RiskFlag["OVERDUE_STAGE"] = "OVERDUE_STAGE";
    RiskFlag["MISSING_DOCS"] = "MISSING_DOCS";
})(RiskFlag || (exports.RiskFlag = RiskFlag = {}));
