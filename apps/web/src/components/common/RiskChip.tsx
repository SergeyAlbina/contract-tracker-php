'use client';
import Chip from '@mui/material/Chip';
import Tooltip from '@mui/material/Tooltip';
import Box from '@mui/material/Box';
import { RiskFlag } from '@/types/api';

const RISK_META: Record<RiskFlag, { label: string; color: string; tip: string }> = {
  EXPIRING_10: { label: '≤10 дн', color: '#d32f2f', tip: 'Истекает через 10 и менее дней' },
  EXPIRING_30: { label: '≤30 дн', color: '#e65100', tip: 'Истекает через 30 и менее дней' },
  EXPIRING_90: { label: '≤90 дн', color: '#f57c00', tip: 'Истекает через 90 и менее дней' },
  OVERSPEND:   { label: 'Перерасход', color: '#c62828', tip: 'Оплачено больше суммы контракта' },
  OVERDUE_STAGE: { label: 'Этап просрочен', color: '#ad1457', tip: 'Есть просроченный этап' },
  MISSING_DOCS: { label: 'Нет документов', color: '#4527a0', tip: 'Документы не прикреплены' },
};

interface Props {
  flags: RiskFlag[];
}

export default function RiskChips({ flags }: Props) {
  if (!flags.length) return null;
  return (
    <Box sx={{ display: 'flex', gap: 0.5, flexWrap: 'wrap' }}>
      {flags.map((f) => {
        const meta = RISK_META[f];
        return (
          <Tooltip key={f} title={meta.tip}>
            <Chip
              label={meta.label}
              size="small"
              sx={{
                backgroundColor: meta.color,
                color: '#fff',
                fontWeight: 600,
                fontSize: '0.7rem',
              }}
            />
          </Tooltip>
        );
      })}
    </Box>
  );
}
