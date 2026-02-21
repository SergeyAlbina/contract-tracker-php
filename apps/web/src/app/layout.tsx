import type { Metadata } from 'next';
import ThemeRegistry from '@/components/providers/ThemeRegistry';

export const metadata: Metadata = {
  title: 'Contract Tracker',
  description: 'Система контроля жизненного цикла контрактов (223-ФЗ / 44-ФЗ)',
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="ru">
      <body>
        <ThemeRegistry>{children}</ThemeRegistry>
      </body>
    </html>
  );
}
