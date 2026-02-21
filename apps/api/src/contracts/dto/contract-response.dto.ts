import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';
import { ContractStatus, LawType, RiskFlag } from '@ct/shared';

export class ContractResponseDto {
  @ApiProperty() id: string;
  @ApiProperty() number: string;
  @ApiProperty() title: string;
  @ApiProperty({ enum: LawType }) lawType: LawType;
  @ApiProperty({ enum: ContractStatus }) status: ContractStatus;
  @ApiProperty() supplierName: string;
  @ApiPropertyOptional() supplierInn?: string;
  @ApiProperty() totalAmount: string;
  @ApiPropertyOptional() nmckAmount?: string;
  @ApiPropertyOptional() signedAt?: Date;
  @ApiPropertyOptional() startDate?: Date;
  @ApiPropertyOptional() endDate?: Date;
  @ApiPropertyOptional() description?: string;
  @ApiProperty() ownerId: string;
  @ApiPropertyOptional() procurementId?: string;
  @ApiProperty() balance: string;
  @ApiProperty({ enum: RiskFlag, isArray: true }) riskFlags: RiskFlag[];
  @ApiProperty() createdAt: Date;
  @ApiProperty() updatedAt: Date;
}
