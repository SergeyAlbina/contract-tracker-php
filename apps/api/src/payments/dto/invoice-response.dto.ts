import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';

export class InvoiceResponseDto {
  @ApiProperty() id: string;
  @ApiProperty() contractId: string;
  @ApiPropertyOptional() stageId?: string;
  @ApiProperty() number: string;
  @ApiProperty() amount: string;
  @ApiProperty() issuedAt: Date;
  @ApiPropertyOptional() dueAt?: Date;
  @ApiPropertyOptional() paidAt?: Date;
  @ApiPropertyOptional() notes?: string;
  @ApiProperty() createdAt: Date;
  @ApiProperty() updatedAt: Date;
}
