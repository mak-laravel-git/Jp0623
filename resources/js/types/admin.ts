// =============================================================================
// ADMIN — shared shapes for the access-management pages
// (admin/users, admin/roles, admin/permissions).
// =============================================================================

/** The protected, system-critical role. Mirrors App\Enums\RoleEnum::SUPER_ADMIN. */
export const SUPER_ADMIN_ROLE = 'super-admin';

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
    prev_page_url: string | null;
    next_page_url: string | null;
}

export interface RoleRef {
    id: number;
    name: string;
}

export interface AdminUser {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    roles: RoleRef[];
}

export interface AdminOperatorProfile {
    id: number;
    first_name: string;
    last_name: string;
    company_name: string;
    phone: string | null;
    owns_machines: boolean;
    machine_types: string[];
    leads_needed: string;
    primary_city: string;
    address: string | null;
    city: string | null;
    state: string | null;
    postal_code: string | null;
    latitude: string | null;
    longitude: string | null;
    terms_accepted_at: string | null;
    email_code_confirmed_at: string | null;
    trial_started_at: string | null;
}

export interface AdminUserDetail extends AdminUser {
    permissions: { id: number; name: string }[];
    updated_at: string;
}

export interface AdminRoleListItem {
    id: number;
    name: string;
    guard_name: string;
    permissions_count: number;
    users_count: number;
    created_at: string;
}

export interface AdminRoleDetail {
    id: number;
    name: string;
    permissions: string[];
    is_super_admin: boolean;
}

/** A single assignable permission, as sent to the role editor. */
export interface PermissionOption {
    id: number;
    name: string;
    group: string;
}

/** A permission row for the read-only permissions listing. */
export interface PermissionListItem {
    id: number;
    name: string;
    group: string;
    guard_name: string;
    roles_count: number;
}

export interface MachineCategoryOption {
    id: number;
    name: string;
}

export interface AdminMachineCategory {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    sort_order: number;
    is_active: boolean;
    machines_count?: number;
    created_at: string;
    updated_at: string;
}

export interface AdminHomeFaq {
    id: number;
    question: string;
    answer: string;
    sort_order: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface AdminLegalPageSection {
    heading: string | null;
    body: string;
}

export interface AdminLegalPageSummary {
    id: number;
    slug: string;
    title: string;
    last_updated_on: string | null;
    sections_count: number;
}

export interface AdminLegalPage {
    id: number;
    slug: string;
    title: string;
    last_updated_on: string | null;
    sections: AdminLegalPageSection[];
}

export interface AdminContactMessage {
    id: number;
    name: string;
    company: string | null;
    email: string;
    phone: string | null;
    topic: string;
    message: string;
    terms_accepted: boolean;
    status: 'new' | 'reviewed';
    created_at: string;
    updated_at: string;
}

export interface AdminRouteListing {
    id: number;
    city: string;
    about: string;
    locations_count: number;
    machines_count: number;
    annual_revenue: number;
    asking_price: number;
    seller_financing: boolean;
    is_active: boolean;
    listed_at: string;
    created_at: string;
    updated_at: string;
}

export interface AdminMembershipCustomPrice {
    id: number;
    email: string;
    monthly_price: number;
    member_name: string | null;
    registered: boolean;
    membership_status: string | null;
    created_at: string;
    updated_at: string;
}

export type LeadIssueStatus = 'open' | 'approved' | 'rejected';

export interface AdminLeadIssueRow {
    id: number;
    ticket: string;
    lead_reference: string;
    lead_name: string | null;
    lead_city: string | null;
    operator_name: string;
    operator_email: string;
    category: string;
    category_label: string;
    reason: string;
    status: LeadIssueStatus;
    status_label: string;
    price: number;
    created_at: string;
}

export interface AdminLeadIssueDetail {
    id: number;
    ticket: string;
    status: LeadIssueStatus;
    status_label: string;
    category: string;
    category_label: string;
    reason: string;
    admin_note: string | null;
    resolved_by: string | null;
    resolved_at: string | null;
    created_at: string;
    lead_reference: string;
    lead_name: string | null;
    lead_type: string | null;
    lead_city: string | null;
    price: number;
    purchased_at: string | null;
    receipt_url: string | null;
    payment_status: string;
    payment: AdminLeadIssuePayment;
    payment_details: { label: string; value: string }[];
    refund: AdminLeadIssueRefund | null;
    operator: {
        name: string;
        email: string;
        phone: string | null;
        company: string | null;
    };
}

export interface AdminMembershipPriceHistory {
    id: number;
    email: string;
    member_name: string | null;
    registered: boolean;
    action: 'created' | 'updated' | 'removed';
    old_price: number | null;
    new_price: number | null;
    changed_by: string | null;
    email_sent: boolean;
    created_at: string;
}

export interface MembershipCustomPriceLookup {
    exists: boolean;
    name: string | null;
    message: string;
}

export interface AdminHeroImage {
    id: number;
    hero_section_id: number;
    image: string;
    image_url: string | null;
    alt: string;
    sort_order: number;
}

export interface AdminHeroSection {
    id: number;
    eyebrow: string;
    title_before: string;
    title_accent: string;
    lede: string;
    bullets: string[];
    cta_label: string;
    cta_href: string;
    trust_stars: string;
    trust_text: string;
    trust_reviews_label: string;
    trust_reviews_href: string;
    images: AdminHeroImage[];
}

export type RouteInquiryStatus = 'pending' | 'contacted' | 'closed';

export interface AdminRouteInquiry {
    id: number;
    route_listing_id: number | null;
    user_id: number;
    contact_name: string;
    contact_email: string;
    contact_phone: string | null;
    listing_reference: string;
    listing_city: string;
    listing_asking_price: number;
    message: string;
    status: RouteInquiryStatus;
    created_at: string;
    updated_at: string;
    route_listing?: {
        id: number;
        city: string;
        about: string;
        asking_price: number;
        is_active: boolean;
    } | null;
}

export interface AdminLocationType {
    id: number;
    name: string;
    slug: string | null;
    description: string | null;
    image: string | null;
    image_url: string | null;
    kind: string | null;
    sort_order: number;
    is_active: boolean;
    show_on_industries: boolean;
    created_at: string;
    updated_at: string;
}

export interface AdminReview {
    id: number;
    quote: string;
    role: string;
    org: string;
    rating: number;
    sort_order: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface AdminAboutFaq {
    id: number;
    question: string;
    answer: string;
    sort_order: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface AdminMachineFaq {
    id: number;
    question: string;
    answer: string;
    sort_order: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface AdminOfficeCoffeeFaq {
    id: number;
    question: string;
    answer: string;
    sort_order: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface AdminMicroMarketFaq {
    id: number;
    question: string;
    answer: string;
    sort_order: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface AdminMachine {
    id: number;
    machine_category_id: number;
    name: string;
    slug: string;
    kind: string;
    tags: string[] | null;
    description: string | null;
    image: string | null;
    image_url: string | null;
    spec_image: string | null;
    spec_image_url: string | null;
    capacity: string | null;
    dimensions: string | null;
    best_for: string | null;
    status: string;
    is_featured: boolean;
    is_active: boolean;
    sort_order: number;
    created_at: string;
    updated_at: string;
    category?: MachineCategoryOption & { slug?: string };
}

export interface AdminCoffeeType {
    id: number;
    label: string;
    sort_order: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface AdminCity {
    id: number;
    city: string;
    state: string;
    postal_code: string | null;
    latitude: number | null;
    longitude: number | null;
    sort_order: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface CityImportReport {
    created: number;
    updated: number;
    skipped: number;
    errors: { row: number; message: string }[];
}

export interface AdminArticle {
    id: number;
    slug: string;
    category: string;
    title: string;
    excerpt: string | null;
    body: string | null;
    image: string | null;
    image_url: string | null;
    sort_order: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface AdminCoffeeMachine {
    id: number;
    title: string;
    text: string | null;
    tag: string | null;
    image: string | null;
    image_url: string | null;
    sort_order: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface AdminProduct {
    id: number;
    name: string;
    slug: string;
    kind: string;
    tags: string[] | null;
    description: string | null;
    image: string | null;
    image_url: string | null;
    is_featured: boolean;
    is_active: boolean;
    sort_order: number;
    created_at: string;
    updated_at: string;
}

export type OperatorSmsStatus =
    'pending' | 'done' | 'partial' | 'failed' | 'skipped';

export interface AdminVendingRequest {
    id: number;
    location_type: string;
    location_type_note: string | null;
    business_name: string;
    address_or_zip: string;
    address: string | null;
    city: string | null;
    state: string | null;
    postal_code: string | null;
    latitude: string | null;
    longitude: string | null;
    employees_on_site: number | null;
    daily_visitors: number | null;
    placement: string;
    space_notes: string | null;
    food_on_site: string;
    comparing_quotes: string;
    machine_interests: string[];
    machine_count: string;
    existing_vending: string;
    switch_reason: string | null;
    contact_name: string;
    job_title: string | null;
    email: string;
    phone: string;
    terms_accepted: boolean;
    price: number | null;
    status: 'new' | 'reviewed';
    operator_sms_status: OperatorSmsStatus;
    operator_sms_sent_count: number;
    operator_sms_failed_count: number;
    operator_sms_provider_status: string | null;
    operator_sms_note: string | null;
    operator_sms_notified_at: string | null;
    created_at: string;
    updated_at: string;
    /** True when a paid LeadPurchase exists for this lead. */
    is_sold: boolean;
    /** True when any LeadPurchase holds lead_lock (pending or paid). */
    is_held: boolean;
}

export interface AdminCoffeeServiceRequest {
    id: number;
    business_name: string;
    zip_code: string;
    address: string | null;
    city: string | null;
    state: string | null;
    postal_code: string | null;
    latitude: string | null;
    longitude: string | null;
    contact_name: string;
    phone: string;
    email: string;
    notes: string | null;
    terms_accepted: boolean;
    price: number | null;
    status: 'new' | 'reviewed' | 'closed';
    operator_sms_status: OperatorSmsStatus;
    operator_sms_sent_count: number;
    operator_sms_failed_count: number;
    operator_sms_provider_status: string | null;
    operator_sms_note: string | null;
    operator_sms_notified_at: string | null;
    created_at: string;
    updated_at: string;
    /** True when a paid LeadPurchase exists for this lead. */
    is_sold: boolean;
    /** True when any LeadPurchase holds lead_lock (pending or paid). */
    is_held: boolean;
}

/** Prefer structured city/state/postal; fall back to legacy display fields. */
export function formatRequestLocation(request: {
    address?: string | null;
    city?: string | null;
    state?: string | null;
    postal_code?: string | null;
    address_or_zip?: string | null;
    zip_code?: string | null;
}): string {
    const cityState = [request.city, request.state].filter(Boolean).join(', ');
    const withPostal = [cityState, request.postal_code]
        .filter(Boolean)
        .join(' ');

    if (request.address && withPostal) {
        return `${request.address}, ${withPostal}`;
    }

    if (withPostal) {
        return withPostal;
    }

    return request.address_or_zip || request.zip_code || '—';
}

/** Build the public URL for a stored avatar path, or null. */
export function avatarUrl(path: string | null): string | null {
    if (!path) {
        return null;
    }

    return path.startsWith('http') ? path : `/storage/${path}`;
}

/** Group an ordered permission list by its `group` field, preserving order. */
export function groupByGroup<T extends { group: string }>(
    items: T[],
): Record<string, T[]> {
    return items.reduce<Record<string, T[]>>((acc, item) => {
        (acc[item.group] ??= []).push(item);

        return acc;
    }, {});
}

export interface AdminLeadIssuePayment {
    card_label: string | null;
    total: number;
    credits: number;
    card: number;
    can_refund: boolean;
    can_refund_dollars: boolean;
}

export interface AdminLeadIssueRefund {
    id: number;
    type: 'dollar' | 'credit';
    type_label: string;
    total_amount: number;
    credit_amount: number;
    card_amount: number;
    status: 'pending' | 'completed' | 'failed';
    status_label: string;
    failure_message: string | null;
    square_refund_id: string | null;
    lead_action_label: string;
    refunded_by: string | null;
    details: { label: string; value: string }[];
    receipt_url: string;
    created_at: string;
}

export interface AdminRefundRow {
    id: number;
    report_id: number;
    lead_reference: string;
    lead_name: string | null;
    lead_city: string | null;
    operator_name: string;
    operator_email: string;
    type: 'dollar' | 'credit';
    type_label: string;
    total_amount: number;
    credit_amount: number;
    card_amount: number;
    status: 'pending' | 'completed' | 'failed';
    status_label: string;
    failure_message: string | null;
    lead_action: string;
    lead_action_label: string;
    created_at: string;
}

export type PaymentSettingSource = 'admin' | 'env' | 'missing';

export interface AdminPaymentSettings {
    fields: Record<
        'environment' | 'application_id' | 'location_id' | 'webhook_url',
        { value: string | null; source: PaymentSettingSource }
    >;
    secrets: Record<
        'access_token' | 'webhook_signature_key',
        {
            configured: boolean;
            last4: string | null;
            source: PaymentSettingSource;
        }
    >;
    default_webhook_url: string;
    updated_by: string | null;
    updated_at: string | null;
    is_configured: boolean;
}

export interface AdminTwilioSettings {
    enabled: { value: boolean; source: PaymentSettingSource };
    fields: Record<
        'sid' | 'from_number' | 'messaging_service_sid' | 'lead_radius_miles',
        { value: string | null; source: PaymentSettingSource }
    >;
    secrets: Record<
        'auth_token',
        {
            configured: boolean;
            last4: string | null;
            source: PaymentSettingSource;
        }
    >;
    updated_by: string | null;
    updated_at: string | null;
    is_configured: boolean;
}
