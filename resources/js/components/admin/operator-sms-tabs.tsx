import { Link } from '@inertiajs/react';
import { FileText, Send, Settings2 } from 'lucide-react';
import { usePermission } from '@/hooks/use-permissions';
import { cn } from '@/lib/utils';
import operatorSms from '@/routes/admin/operator-sms';
import templates from '@/routes/admin/operator-sms/templates';
import twilioSettings from '@/routes/admin/twilio-settings';
import { PERMISSIONS } from '@/types/permissions';

export function OperatorSmsTabs({
    active,
}: {
    active: 'send' | 'templates' | 'settings';
}) {
    const { can } = usePermission();
    const items = [
        {
            key: 'send' as const,
            label: 'Send SMS',
            href: operatorSms.index().url,
            icon: Send,
        },
        {
            key: 'templates' as const,
            label: 'SMS templates',
            href: templates.index().url,
            icon: FileText,
        },
        ...(can(PERMISSIONS.TWILIO_SETTINGS.EDIT)
            ? [
                  {
                      key: 'settings' as const,
                      label: 'Twilio settings',
                      href: twilioSettings.edit().url,
                      icon: Settings2,
                  },
              ]
            : []),
    ];

    return (
        <div className="flex flex-wrap gap-2 border-b pb-3">
            {items.map((item) => {
                const Icon = item.icon;
                const isActive = item.key === active;

                return (
                    <Link
                        key={item.key}
                        href={item.href}
                        className={cn(
                            'inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                            isActive
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted/60 text-muted-foreground hover:bg-muted hover:text-foreground',
                        )}
                    >
                        <Icon className="h-4 w-4" />
                        {item.label}
                    </Link>
                );
            })}
        </div>
    );
}
