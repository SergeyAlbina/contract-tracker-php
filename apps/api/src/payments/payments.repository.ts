import { Injectable } from '@nestjs/common';
import { Invoice, Payment, Prisma } from '../generated/prisma/client';
import { PrismaService } from '../prisma/prisma.service';

@Injectable()
export class PaymentsRepository {
  constructor(private prisma: PrismaService) {}

  // ─── Invoices ─────────────────────────────────────────────────────────────

  findInvoicesByContract(contractId: string): Promise<Invoice[]> {
    return this.prisma.invoice.findMany({
      where: { contractId },
      orderBy: { issuedAt: 'desc' },
    });
  }

  findInvoiceById(id: string): Promise<Invoice | null> {
    return this.prisma.invoice.findUnique({ where: { id } });
  }

  createInvoice(data: Prisma.InvoiceCreateInput): Promise<Invoice> {
    return this.prisma.invoice.create({ data });
  }

  // ─── Payments ─────────────────────────────────────────────────────────────

  findPaymentsByContract(contractId: string): Promise<Payment[]> {
    return this.prisma.payment.findMany({
      where: { contractId },
      orderBy: { createdAt: 'desc' },
    });
  }

  findPaymentById(id: string): Promise<Payment | null> {
    return this.prisma.payment.findUnique({ where: { id } });
  }

  createPayment(data: Prisma.PaymentCreateInput): Promise<Payment> {
    return this.prisma.payment.create({ data });
  }

  updatePayment(id: string, data: Prisma.PaymentUpdateInput): Promise<Payment> {
    return this.prisma.payment.update({ where: { id }, data });
  }

  deletePayment(id: string): Promise<Payment> {
    return this.prisma.payment.delete({ where: { id } });
  }
}
