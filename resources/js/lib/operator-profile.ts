export const OPERATOR_LEADS_NEEDED = [
    '1',
    '2',
    '3',
    '4',
    '5',
    '6+',
] as const;

export type OperatorLeadsNeeded =
    (typeof OPERATOR_LEADS_NEEDED)[number];
