export type ComplianceType = 'mot' | 'tax' | 'insurance';

export const COMPLIANCE_LABELS: Record<ComplianceType, string> = {
    mot: 'MOT',
    tax: 'Road Tax',
    insurance: 'Insurance',
};
