import { SeoHead } from '@/components/frontend/seo-head';
import { DetailOverviewSection } from '@/components/machines/detail-overview-section';
import { DetailRelatedSection } from '@/components/machines/detail-related-section';
import type { MachineDetailPageProps } from '@/types/frontend';

export default function MachineShow({
    machine,
    status,
    specs,
    specsImage,
    checks,
    related,
    cta,
}: MachineDetailPageProps) {
    return (
        <>
            <SeoHead title={machine.name} description={machine.desc} />

            <DetailOverviewSection
                machine={machine}
                status={status}
                specs={specs}
                specsImage={specsImage}
                checks={checks}
                cta={cta}
            />
            <DetailRelatedSection data={related} />
        </>
    );
}
