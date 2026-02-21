import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';
import { ProposalStatus } from '@ct/shared';

export class ProposalResponseDto {
  @ApiProperty() id: string;
  @ApiProperty() procurementId: string;
  @ApiProperty() supplierName: string;
  @ApiProperty() offeredAmount: string;
  @ApiProperty({ enum: ProposalStatus }) status: ProposalStatus;
  @ApiPropertyOptional() notes?: string;
  @ApiPropertyOptional() rejectionReason?: string;
  @ApiPropertyOptional() submittedAt?: Date;
  @ApiPropertyOptional() decidedAt?: Date;
  @ApiProperty() createdAt: Date;
  @ApiProperty() updatedAt: Date;
}
