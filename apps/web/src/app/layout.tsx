import type { Metadata } from 'next';
import { Manrope } from 'next/font/google';
import ThemeRegistry from '@/components/providers/ThemeRegistry';

const manrope = Manrope({
  subsets: ['latin', 'cyrillic'],
  variable: '--font-manrope',
  display: 'swap',
});

export const metadata: Metadata = {
  title: 'Трекер контрактов',
  description: 'Система контроля жизненного цикла контрактов (223-ФЗ / 44-ФЗ)',
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="ru">
      <body className={`${manrope.variable} ${manrope.className}`}>
        <ThemeRegistry>{children}</ThemeRegistry>
      </body>
    </html>
  );
}
