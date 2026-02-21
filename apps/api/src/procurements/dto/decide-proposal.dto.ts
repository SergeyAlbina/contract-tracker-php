import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';
import { IsEnum, IsOptional, IsString } from 'class-validator';
import { ProposalStatus } from '@ct/shared';

export class DecideProposalDto {
  @ApiProperty({ enum: [ProposalStatus.ACCEPTED, ProposalStatus.REJECTED] })
  @IsEnum(ProposalStatus)
  status: ProposalStatus.ACCEPTED | ProposalStatus.REJECTED;

  @ApiPropertyOptional({ description: 'Обязательно при REJECTED' })
  @IsString()
  @IsOptional()
  rejectionReason?: string;
}
