import { BadRequestException, Injectable, NotFoundException } from '@nestjs/common';
import { Invoice, Payment } from '../generated/prisma/client';
import { PaymentStatus } from '@ct/shared';
import { PaymentsRepository } from './payments.repository';
import { ContractsRepository } from '../contracts/contracts.repository';
import { CreateInvoiceDto } from './dto/create-invoice.dto';
import { CreatePaymentDto } from './dto/create-payment.dto';
import { UpdatePaymentDto } from './dto/update-payment.dto';
import { InvoiceResponseDto } from './dto/invoice-response.dto';
import { PaymentResponseDto } from './dto/payment-response.dto';

@Injectable()
export class PaymentsService {
  constructor(
    private repo: PaymentsRepository,
    private contractsRepo: ContractsRepository,
  ) {}

  // ─── Invoices ─────────────────────────────────────────────────────────────

  async findInvoices(contractId: string): Promise<InvoiceResponseDto[]> {
    await this.assertContractExists(contractId);
    const invoices = await this.repo.findInvoicesByContract(contractId);
    return invoices.map(this.toInvoiceDto);
  }

  async createInvoice(contractId: string, dto: CreateInvoiceDto): Promise<InvoiceResponseDto> {
    await this.assertContractExists(contractId);
    const invoice = await this.repo.createInvoice({
      number: dto.number,
      amount: dto.amount,
      issuedAt: new Date(dto.issuedAt),
      dueAt: dto.dueAt ? new Date(dto.dueAt) : undefined,
      notes: dto.notes,
      contract: { connect: { id: contractId } },
      ...(dto.stageId && { stage: { connect: { id: dto.stageId } } }),
    });
    return this.toInvoiceDto(invoice);
  }

  // ─── Payments ─────────────────────────────────────────────────────────────

  async findPayments(contractId: string): Promise<PaymentResponseDto[]> {
    await this.assertContractExists(contractId);
    const payments = await this.repo.findPaymentsByContract(contractId);
    return payments.map(this.toPaymentDto);
  }

  async createPayment(contractId: string, dto: CreatePaymentDto): Promise<PaymentResponseDto> {
    await this.assertContractExists(contractId);
    const payment = await this.repo.createPayment({
      amount: dto.amount,
      status: (dto.status ?? 'PLANNED') as any,
      plannedAt: dto.plannedAt ? new Date(dto.plannedAt) : undefined,
      paidAt: dto.paidAt ? new Date(dto.paidAt) : undefined,
      reference: dto.reference,
      notes: dto.notes,
      contract: { connect: { id: contractId } },
      ...(dto.invoiceId && { invoice: { connect: { id: dto.invoiceId } } }),
    });
    return this.toPaymentDto(payment);
  }

  async updatePayment(
    contractId: string,
    id: string,
    dto: UpdatePaymentDto,
  ): Promise<PaymentResponseDto> {
    await this.assertPaymentInContract(id, contractId);
    const payment = await this.repo.updatePayment(id, {
      amount: dto.amount,
      status: dto.status as any,
      plannedAt: dto.plannedAt ? new Date(dto.plannedAt) : undefined,
      paidAt: dto.paidAt ? new Date(dto.paidAt) : undefined,
      reference: dto.reference,
      notes: dto.notes,
    });
    return this.toPaymentDto(payment);
  }

  async deletePayment(contractId: string, id: string): Promise<void> {
    const payment = await this.assertPaymentInContract(id, contractId);
    if (payment.status === 'PAID') {
      throw new BadRequestException('Нельзя удалить оплаченный платёж');
    }
    await this.repo.deletePayment(id);
  }

  // ─── Helpers & Mappers ────────────────────────────────────────────────────

  private async assertContractExists(contractId: string): Promise<void> {
    const c = await this.contractsRepo.findById(contractId);
    if (!c) throw new NotFoundException(`Контракт ${contractId} не найден`);
  }

  private async assertPaymentInContract(id: string, contractId: string): Promise<Payment> {
    const p = await this.repo.findPaymentById(id);
    if (!p || p.contractId !== contractId) throw new NotFoundException(`Платёж ${id} не найден`);
    return p;
  }

  private toInvoiceDto(i: Invoice): InvoiceResponseDto {
    return {
      id: i.id,
      contractId: i.contractId,
      stageId: i.stageId ?? undefined,
      number: i.number,
      amount: i.amount.toString(),
      issuedAt: i.issuedAt,
      dueAt: i.dueAt ?? undefined,
      paidAt: i.paidAt ?? undefined,
      notes: i.notes ?? undefined,
      createdAt: i.createdAt,
      updatedAt: i.updatedAt,
    };
  }

  private toPaymentDto(p: Payment): PaymentResponseDto {
    return {
      id: p.id,
      contractId: p.contractId,
      invoiceId: p.invoiceId ?? undefined,
      amount: p.amount.toString(),
      status: p.status as unknown as PaymentStatus,
      plannedAt: p.plannedAt ?? undefined,
      paidAt: p.paidAt ?? undefined,
      reference: p.reference ?? undefined,
      notes: p.notes ?? undefined,
      createdAt: p.createdAt,
      updatedAt: p.updatedAt,
    };
  }
}
