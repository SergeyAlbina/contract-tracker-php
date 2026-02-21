import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';
import { StageStatus } from '@ct/shared';

export class StageResponseDto {
  @ApiProperty() id: string;
  @ApiProperty() contractId: string;
  @ApiProperty() title: string;
  @ApiProperty({ enum: StageStatus }) status: StageStatus;
  @ApiProperty() plannedStart: Date;
  @ApiProperty() plannedEnd: Date;
  @ApiPropertyOptional() actualStart?: Date;
  @ApiPropertyOptional() actualEnd?: Date;
  @ApiPropertyOptional() amount?: string;
  @ApiPropertyOptional() description?: string;
  @ApiProperty() createdAt: Date;
  @ApiProperty() updatedAt: Date;
}
