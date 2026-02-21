import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';
import { PaymentStatus } from '@ct/shared';

export class PaymentResponseDto {
  @ApiProperty() id: string;
  @ApiProperty() contractId: string;
  @ApiPropertyOptional() invoiceId?: string;
  @ApiProperty() amount: string;
  @ApiProperty({ enum: PaymentStatus }) status: PaymentStatus;
  @ApiPropertyOptional() plannedAt?: Date;
  @ApiPropertyOptional() paidAt?: Date;
  @ApiPropertyOptional() reference?: string;
  @ApiPropertyOptional() notes?: string;
  @ApiProperty() createdAt: Date;
  @ApiProperty() updatedAt: Date;
}
