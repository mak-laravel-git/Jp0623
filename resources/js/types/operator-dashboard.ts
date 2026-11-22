export interface OperatorLead {
    id: string;
    source: 'vending' | 'coffee';
    type: string;
    cat: string;
    city: string;
    st: string;
    zip: string;
    dist: number | null;
    people: number | null;
    wants: string;
    io: string | null;
    price: number;
    listed: string;
}

/**
 * A lead another operator already bought nearby. Only public details are sent:
 * no business name, address, contact details or buyer.
 */
export interface OperatorSoldLead {
    id: string;
    type: string;
    city: string;
    st: string;
    zip: string;
    dist: number;
    people: number | null;
    wants: string;
    price: number;
    sold: string;
    mine: boolean;
}

export type PurchasedLeadStatus = 'ok' | 'issue_open';

export type LeadPaymentStatus = 'paid' | 'pending';

/**
 * A purchased lead. While `payment_status` is `pending` the contact fields are
 * null: they are only sent once Square confirmed the payment.
 */
export interface PurchasedLead {
    id: string;
    payment_status: LeadPaymentStatus;
    purchased_at: string;
    name: string | null;
    type: string;
    city: string;
    st: string;
    zip: string;
    address: string | null;
    contact_name: string | null;
    contact_phone: string | null;
    email: string | null;
    price: number;
    credit_used: number | null;
    status: PurchasedLeadStatus;
    receipt_url: string | null;
    card_brand: string | null;
    card_last4: string | null;
}

export interface OperatorKpi {
    label: string;
    value: string;
    hint: string | null;
}

export interface OperatorActivityItem {
    text: string;
    when: string;
}

export interface OperatorDashboardProps {
    firstName: string;
    primaryCity: string;
    subscription: {
        status: string;
        label: string;
        needs_payment: boolean;
        is_paid_mode: boolean;
        monthly_price: number;
    };
    kpis: OperatorKpi[];
    leads: OperatorLead[];
    newestLeads: OperatorLead[];
    activity: OperatorActivityItem[];
}
