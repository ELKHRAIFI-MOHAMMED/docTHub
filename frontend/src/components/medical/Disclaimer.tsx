import { useTranslation } from 'react-i18next';
import { Alert } from '../common/ui';

export function Disclaimer({ text }: { text?: string }) {
  const { t } = useTranslation();
  return (
    <Alert tone="info" title={t('disclaimer.title')}>
      {text ?? t('disclaimer.orientation')}
    </Alert>
  );
}
