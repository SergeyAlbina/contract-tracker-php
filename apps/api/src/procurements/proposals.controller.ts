import { Controller, Get, Post, Patch, Body, Param, UseGuards } from '@nestjs/common';
import { ApiTags, ApiOperation, ApiBearerAuth } from '@nestjs/swagger';
import { ProposalsService } from './proposals.service';
import { CreateProposalDto } from './dto/create-proposal.dto';
import { DecideProposalDto } from './dto/decide-proposal.dto';
import { ProposalResponseDto } from './dto/proposal-response.dto';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';

@ApiTags('proposals')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard)
@Controller('procurements/:procurementId/proposals')
export class ProposalsController {
  constructor(private service: ProposalsService) {}

  @Get()
  @ApiOperation({ summary: 'КП по закупке' })
  findAll(@Param('procurementId') procurementId: string): Promise<ProposalResponseDto[]> {
    return this.service.findByProcurement(procurementId);
  }

  @Post()
  @ApiOperation({ summary: 'Добавить КП к закупке' })
  create(
    @Param('procurementId') procurementId: string,
    @Body() dto: CreateProposalDto,
  ): Promise<ProposalResponseDto> {
    return this.service.create(procurementId, dto);
  }

  @Patch(':id/decide')
  @ApiOperation({ summary: 'Принять / отклонить КП' })
  decide(@Param('id') id: string, @Body() dto: DecideProposalDto): Promise<ProposalResponseDto> {
    return this.service.decide(id, dto);
  }
}
