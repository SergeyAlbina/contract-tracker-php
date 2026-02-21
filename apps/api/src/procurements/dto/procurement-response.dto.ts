import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';
import { LawType, ProcurementStatus } from '@ct/shared';

export class ProcurementResponseDto {
  @ApiProperty() id: string;
  @ApiProperty() number: string;
  @ApiProperty() title: string;
  @ApiProperty({ enum: LawType }) lawType: LawType;
  @ApiProperty({ enum: ProcurementStatus }) status: ProcurementStatus;
  @ApiPropertyOptional() description?: string;
  @ApiPropertyOptional() plannedDate?: Date;
  @ApiProperty() ownerId: string;
  @ApiProperty() createdAt: Date;
  @ApiProperty() updatedAt: Date;
}
