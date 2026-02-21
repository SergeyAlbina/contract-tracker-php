import {
  Controller,
  Get,
  Post,
  Patch,
  Delete,
  Body,
  Param,
  HttpCode,
  HttpStatus,
  UseGuards,
} from '@nestjs/common';
import { ApiTags, ApiOperation, ApiBearerAuth } from '@nestjs/swagger';
import { PaymentsService } from './payments.service';
import { CreateInvoiceDto } from './dto/create-invoice.dto';
import { CreatePaymentDto } from './dto/create-payment.dto';
import { UpdatePaymentDto } from './dto/update-payment.dto';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';

@ApiTags('payments')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard)
@Controller('contracts/:contractId')
export class PaymentsController {
  constructor(private service: PaymentsService) {}

  // ─── Invoices ─────────────────────────────────────────────────────────────

  @Get('invoices')
  @ApiOperation({ summary: 'Счета по контракту' })
  findInvoices(@Param('contractId') contractId: string) {
    return this.service.findInvoices(contractId);
  }

  @Post('invoices')
  @ApiOperation({ summary: 'Добавить счёт' })
  createInvoice(@Param('contractId') contractId: string, @Body() dto: CreateInvoiceDto) {
    return this.service.createInvoice(contractId, dto);
  }

  // ─── Payments ─────────────────────────────────────────────────────────────

  @Get('payments')
  @ApiOperation({ summary: 'Оплаты по контракту' })
  findPayments(@Param('contractId') contractId: string) {
    return this.service.findPayments(contractId);
  }

  @Post('payments')
  @ApiOperation({ summary: 'Добавить оплату' })
  createPayment(@Param('contractId') contractId: string, @Body() dto: CreatePaymentDto) {
    return this.service.createPayment(contractId, dto);
  }

  @Patch('payments/:id')
  @ApiOperation({ summary: 'Обновить оплату' })
  updatePayment(
    @Param('contractId') contractId: string,
    @Param('id') id: string,
    @Body() dto: UpdatePaymentDto,
  ) {
    return this.service.updatePayment(contractId, id, dto);
  }

  @Delete('payments/:id')
  @HttpCode(HttpStatus.NO_CONTENT)
  @ApiOperation({ summary: 'Удалить оплату (только не PAID)' })
  deletePayment(@Param('contractId') contractId: string, @Param('id') id: string) {
    return this.service.deletePayment(contractId, id);
  }
}
