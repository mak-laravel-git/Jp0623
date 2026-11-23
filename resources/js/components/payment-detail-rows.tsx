export type DetailRow = { label: string; value: string };

/**
 * Label / value rows of payment details (transaction id, card, amounts). Long ids wrap on small screens.
 */
export function PaymentDetailRows({ rows }: { rows: DetailRow[] }) {
    if (rows.length === 0) {
        return null;
    }

    return (
        <dl className="space-y-3">
            {rows.map((row) => (
                <div
                    key={row.label}
                    className="flex flex-col gap-0.5 sm:flex-row sm:justify-between sm:gap-4"
                >
                    <dt className="text-sm text-muted-foreground">
                        {row.label}
                    </dt>
                    <dd className="text-sm font-medium break-all sm:text-right">
                        {row.value}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
