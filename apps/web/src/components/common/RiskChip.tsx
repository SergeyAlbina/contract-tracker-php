'use client';
import Chip from '@mui/material/Chip';
import Tooltip from '@mui/material/Tooltip';
import Box from '@mui/material/Box';
import { alpha, useTheme } from '@mui/material/styles';
import { RiskFlag } from '@/types/api';

const RISK_META: Record<RiskFlag, { label: string; tone: 'high' | 'medium' | 'info'; tip: string }> = {
  EXPIRING_10: { label: '≤10 дн', tone: 'high', tip: 'Истекает через 10 и менее дней' },
  EXPIRING_30: { label: '≤30 дн', tone: 'medium', tip: 'Истекает через 30 и менее дней' },
  EXPIRING_90: { label: '≤90 дн', tone: 'info', tip: 'Истекает через 90 и менее дней' },
  OVERSPEND: { label: 'Перерасход', tone: 'high', tip: 'Оплачено больше суммы контракта' },
  OVERDUE_STAGE: { label: 'Этап просрочен', tone: 'high', tip: 'Есть просроченный этап' },
  MISSING_DOCS: { label: 'Нет документов', tone: 'medium', tip: 'Документы не прикреплены' },
};

interface Props {
  flags: RiskFlag[];
}

export default function RiskChips({ flags }: Props) {
  const theme = useTheme();

  if (!flags.length) return null;
  return (
    <Box sx={{ display: 'flex', gap: 0.75, flexWrap: 'wrap' }}>
      {flags.map((f) => {
        const meta = RISK_META[f];
        const palette =
          meta.tone === 'high'
            ? theme.palette.error
            : meta.tone === 'medium'
              ? theme.palette.warning
              : theme.palette.info;

        return (
          <Tooltip key={f} title={meta.tip}>
            <Chip
              label={meta.label}
              size="small"
              variant="outlined"
              sx={{
                backgroundColor: alpha(palette.main, 0.12),
                borderColor: alpha(palette.main, 0.34),
                color: palette.dark,
                fontWeight: 700,
                fontSize: '0.72rem',
                height: 24,
                '& .MuiChip-label': { px: 1.1 },
              }}
            />
          </Tooltip>
        );
      })}
    </Box>
  );
}
