import { Link, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    BookOpen,
    Building2,
    CircleHelp,
    Coffee,
    CreditCard,
    FolderGit2,
    FolderTree,
    LayoutGrid,
    MapPinned,
    MapPin,
    MessageSquare,
    Newspaper,
    PanelTop,
    Package,
    Receipt,
    Route,
    Scale,
    Send,
    ShoppingBasket,
    Star,
    Ticket,
    // Upload, // File Upload Demo — uncomment with nav item below
    User,
    Users,
    UserCog,
    Inbox,
    Mail,
    History,
    KeyRound,
    Undo2,
    Wallet,
} from 'lucide-react';
import { useMemo } from 'react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavUser } from '@/components/nav-user';
import { SidebarNav } from '@/components/navigation';
import type { NavNode } from '@/components/navigation';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { usePermission } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import {
    index as accountIndex,
    location as accountLocation,
} from '@/routes/account';
import adminAdminManagement from '@/routes/admin/admin-management';
import adminArticles from '@/routes/admin/articles';
import adminCities from '@/routes/admin/cities';
import adminCoffeeMachines from '@/routes/admin/coffee-machines';
import adminCoffeeTypes from '@/routes/admin/coffee-types';
import adminAboutFaqs from '@/routes/admin/about-faqs';
import adminReviews from '@/routes/admin/reviews';
import adminHomeFaqs from '@/routes/admin/home-faqs';
import adminLocationTypes from '@/routes/admin/location-types';
import adminMachineCategories from '@/routes/admin/machine-categories';
import adminMachineFaqs from '@/routes/admin/machine-faqs';
import adminMachines from '@/routes/admin/machines';
import adminOfficeCoffeeFaqs from '@/routes/admin/office-coffee-faqs';
import adminProducts from '@/routes/admin/products';
import adminHeroSection from '@/routes/admin/hero-section';
import adminLeadIssues from '@/routes/admin/lead-issues';
import adminRefunds from '@/routes/admin/refunds';
import adminRouteInquiries from '@/routes/admin/route-inquiries';
import adminRouteListings from '@/routes/admin/route-listings';
import adminUserManagement from '@/routes/admin/user-management';
import adminCoffeeServiceRequests from '@/routes/admin/coffee-service-requests';
import adminContactMessages from '@/routes/admin/contact-messages';
import adminOperatorSms from '@/routes/admin/operator-sms';
import adminMembershipPriceHistory from '@/routes/admin/membership-price-history';
import adminMembershipSettings from '@/routes/admin/membership-settings';
import adminPaymentSettings from '@/routes/admin/payment-settings';
import adminLegalPages from '@/routes/admin/legal-pages';
import adminPayments from '@/routes/admin/payments';
import adminVendingRequests from '@/routes/admin/vending-requests';
import { index as billingIndex } from '@/routes/billing';
// import { index as fileUploadDemo } from '@/routes/file-upload-demo'; // File Upload Demo — uncomment with nav item below
import {
    faq as leadsFaq,
    index as leadsIndex,
    purchased as leadsPurchased,
    refunds as leadsRefunds,
    sold as leadsSold,
    tickets as leadsTickets,
} from '@/routes/leads';
import { index as routesIndex } from '@/routes/routes';
import { index as subscriptionIndex } from '@/routes/subscription';
import type { NavItem } from '@/types';
import { PERMISSIONS } from '@/types/permissions';

const footerNavItems: NavItem[] = [
    // {
    //     title: 'Repository',
    //     href: 'https://github.com/laravel/react-starter-kit',
    //     icon: FolderGit2,
    // },
    // {
    //     title: 'Documentation',
    //     href: 'https://laravel.com/docs/starter-kits#react',
    //     icon: BookOpen,
    // },
];

export function AppSidebar() {
    const { hasAnyRole } = usePermission();
    const isAdmin = hasAnyRole(['admin', 'super-admin']);
    const { credits } = usePage().props;

    const mainNav = useMemo((): NavNode[] => {
        const items: NavNode[] = [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutGrid,
                permissions: [PERMISSIONS.DASHBOARD.VIEW],
            },
        ];

        if (!isAdmin) {
            items.push(
                {
                    title: 'Location leads',
                    icon: MapPin,
                    permissions: [PERMISSIONS.DASHBOARD.VIEW],
                    items: [
                        {
                            title: 'All location leads',
                            href: leadsIndex(),
                            icon: MapPin,
                            permissions: [PERMISSIONS.DASHBOARD.VIEW],
                        },
                        {
                            title: 'Purchased location leads',
                            href: leadsPurchased(),
                            icon: MapPin,
                            permissions: [PERMISSIONS.DASHBOARD.VIEW],
                        },
                        {
                            title: 'Sold leads near you',
                            href: leadsSold(),
                            icon: BadgeCheck,
                            permissions: [PERMISSIONS.DASHBOARD.VIEW],
                        },
                        // {
                        //     title: 'Lead issues & tickets',
                        //     href: leadsTickets(),
                        //     icon: Ticket,
                        //     permissions: [PERMISSIONS.DASHBOARD.VIEW],
                        // },
                        {
                            title: 'FAQ',
                            href: leadsFaq(),
                            icon: CircleHelp,
                            permissions: [PERMISSIONS.DASHBOARD.VIEW],
                        },
                    ],
                },
                {
                    title: 'Established locations & routes for sale',
                    href: routesIndex(),
                    icon: Route,
                    permissions: [PERMISSIONS.DASHBOARD.VIEW],
                },
                {
                    title: 'Refunds & Credits',
                    href: leadsRefunds(),
                    icon: Wallet,
                    badge: `${credits} Credits`,
                    permissions: [PERMISSIONS.DASHBOARD.VIEW],
                },
                {
                    title: 'Account',
                    icon: User,
                    permissions: [PERMISSIONS.DASHBOARD.VIEW],
                    items: [
                        {
                            title: 'Account info',
                            href: accountIndex(),
                            icon: User,
                            permissions: [PERMISSIONS.DASHBOARD.VIEW],
                        },
                        {
                            title: 'Location settings',
                            href: accountLocation(),
                            icon: MapPinned,
                            permissions: [PERMISSIONS.DASHBOARD.VIEW],
                        },
                        {
                            title: 'Membership',
                            href: subscriptionIndex(),
                            icon: CreditCard,
                            permissions: [PERMISSIONS.DASHBOARD.VIEW],
                        },
                        {
                            title: 'Billing & invoices',
                            href: billingIndex(),
                            icon: Receipt,
                            permissions: [PERMISSIONS.DASHBOARD.VIEW],
                        },
                    ],
                },
            );
        }

        items.push(
            {
                title: 'User Management',
                href: adminUserManagement.index(),
                icon: Users,
                permissions: [PERMISSIONS.USERS.INDEX],
            },
            {
                title: 'Admin Management',
                href: adminAdminManagement.index(),
                icon: UserCog,
                permissions: [PERMISSIONS.USERS.INDEX],
            },
            /*
             * File Upload Demo — commented out; uncomment to restore.
             * Also re-enable the Upload and fileUploadDemo imports above.
             *
             * {
             *     title: 'File Upload Demo',
             *     href: fileUploadDemo(),
             *     icon: Upload,
             *     permissions: [PERMISSIONS.FILE_UPLOAD.INDEX],
             * },
             */
            {
                title: 'Machine Management',
                icon: Package,
                permissions: [
                    PERMISSIONS.MACHINE_CATEGORIES.INDEX,
                    PERMISSIONS.MACHINES.INDEX,
                    PERMISSIONS.LOCATION_TYPES.INDEX,
                ],
                items: [
                    {
                        title: 'Categories',
                        href: adminMachineCategories.index(),
                        icon: FolderTree,
                        permissions: [PERMISSIONS.MACHINE_CATEGORIES.INDEX],
                    },
                    {
                        title: 'Machines',
                        href: adminMachines.index(),
                        icon: Package,
                        permissions: [PERMISSIONS.MACHINES.INDEX],
                    },
                    {
                        title: 'Location Types',
                        href: adminLocationTypes.index(),
                        icon: MapPin,
                        permissions: [PERMISSIONS.LOCATION_TYPES.INDEX],
                    },
                ],
            },
            {
                title: 'Office Coffee Management',
                icon: Coffee,
                permissions: [
                    PERMISSIONS.COFFEE_MACHINES.INDEX,
                    PERMISSIONS.COFFEE_TYPES.INDEX,
                ],
                items: [
                    {
                        title: 'Coffee Machines',
                        href: adminCoffeeMachines.index(),
                        icon: Coffee,
                        permissions: [PERMISSIONS.COFFEE_MACHINES.INDEX],
                    },
                    {
                        title: 'Coffee Types',
                        href: adminCoffeeTypes.index(),
                        icon: Coffee,
                        permissions: [PERMISSIONS.COFFEE_TYPES.INDEX],
                    },
                ],
            },
            {
                title: 'Product Management',
                icon: ShoppingBasket,
                permissions: [PERMISSIONS.PRODUCTS.INDEX],
                items: [
                    {
                        title: 'Products',
                        href: adminProducts.index(),
                        icon: ShoppingBasket,
                        permissions: [PERMISSIONS.PRODUCTS.INDEX],
                    },
                ],
            },
            {
                title: 'Lead Management',
                icon: Inbox,
                permissions: [
                    PERMISSIONS.VENDING_REQUESTS.INDEX,
                    PERMISSIONS.COFFEE_SERVICE_REQUESTS.INDEX,
                    PERMISSIONS.LEAD_ISSUES.INDEX,
                ],
                items: [
                    {
                        title: 'Vending Requests',
                        href: adminVendingRequests.index(),
                        icon: Inbox,
                        permissions: [PERMISSIONS.VENDING_REQUESTS.INDEX],
                    },
                    {
                        title: 'Coffee Requests',
                        href: adminCoffeeServiceRequests.index(),
                        icon: Coffee,
                        permissions: [
                            PERMISSIONS.COFFEE_SERVICE_REQUESTS.INDEX,
                        ],
                    },
                    {
                        title: 'Lead issues',
                        href: adminLeadIssues.index(),
                        icon: Undo2,
                        permissions: [PERMISSIONS.LEAD_ISSUES.INDEX],
                    },
                    {
                        title: 'Refunds',
                        href: adminRefunds.index(),
                        icon: Wallet,
                        permissions: [PERMISSIONS.LEAD_ISSUES.INDEX],
                    },
                ],
            },
            {
                title: 'Route Management',
                icon: Route,
                permissions: [
                    PERMISSIONS.ROUTE_LISTINGS.INDEX,
                    PERMISSIONS.ROUTE_INQUIRIES.INDEX,
                ],
                items: [
                    {
                        title: 'Route Listings',
                        href: adminRouteListings.index(),
                        icon: Route,
                        permissions: [PERMISSIONS.ROUTE_LISTINGS.INDEX],
                    },
                    {
                        title: 'Route Inquiries',
                        href: adminRouteInquiries.index(),
                        icon: MessageSquare,
                        permissions: [PERMISSIONS.ROUTE_INQUIRIES.INDEX],
                    },
                ],
            },
            {
                title: 'Cities',
                href: adminCities.index(),
                icon: Building2,
                permissions: [PERMISSIONS.CITIES.INDEX],
            },
            {
                title: 'Articles',
                href: adminArticles.index(),
                icon: Newspaper,
                permissions: [PERMISSIONS.ARTICLES.INDEX],
            },

            {
                title: 'Contact Messages',
                href: adminContactMessages.index(),
                icon: Mail,
                permissions: [PERMISSIONS.CONTACT_MESSAGES.INDEX],
            },
            {
                title: 'Operator SMS',
                href: adminOperatorSms.index(),
                icon: Send,
                permissions: [PERMISSIONS.OPERATOR_SMS.INDEX],
            },
            {
                title: 'Billing & Membership',
                icon: CreditCard,
                permissions: [
                    PERMISSIONS.PAYMENTS.INDEX,
                    PERMISSIONS.MEMBERSHIP_SETTINGS.EDIT,
                    PERMISSIONS.PAYMENT_SETTINGS.EDIT,
                ],
                items: [
                    {
                        title: 'Payments',
                        href: adminPayments.index(),
                        icon: Receipt,
                        permissions: [PERMISSIONS.PAYMENTS.INDEX],
                    },
                    {
                        title: 'Payment settings',
                        href: adminPaymentSettings.edit(),
                        icon: KeyRound,
                        permissions: [PERMISSIONS.PAYMENT_SETTINGS.EDIT],
                    },
                    {
                        title: 'Membership settings',
                        href: adminMembershipSettings.edit(),
                        icon: CreditCard,
                        permissions: [PERMISSIONS.MEMBERSHIP_SETTINGS.EDIT],
                    },
                    {
                        title: 'Price history',
                        href: adminMembershipPriceHistory.index(),
                        icon: History,
                        permissions: [PERMISSIONS.MEMBERSHIP_SETTINGS.EDIT],
                    },
                ],
            },
            {
                title: 'Hero section',
                href: adminHeroSection.edit(),
                icon: PanelTop,
                permissions: [PERMISSIONS.HERO_SECTION.EDIT],
            },
            {
                title: 'Reviews',
                href: adminReviews.index(),
                icon: Star,
                permissions: [PERMISSIONS.REVIEWS.INDEX],
            },
            {
                title: 'Legal pages',
                href: adminLegalPages.index(),
                icon: Scale,
                permissions: [PERMISSIONS.LEGAL_PAGES.INDEX],
            },
            {
                title: 'FAQ Management',
                icon: CircleHelp,
                permissions: [
                    PERMISSIONS.HOME_FAQS.INDEX,
                    PERMISSIONS.MACHINE_FAQS.INDEX,
                    PERMISSIONS.OFFICE_COFFEE_FAQS.INDEX,
                    PERMISSIONS.ABOUT_FAQS.INDEX,
                ],
                items: [
                    {
                        title: 'Home FAQs',
                        href: adminHomeFaqs.index(),
                        icon: CircleHelp,
                        permissions: [PERMISSIONS.HOME_FAQS.INDEX],
                    },
                    {
                        title: 'Machine FAQs',
                        href: adminMachineFaqs.index(),
                        icon: CircleHelp,
                        permissions: [PERMISSIONS.MACHINE_FAQS.INDEX],
                    },
                    {
                        title: 'Office Coffee FAQs',
                        href: adminOfficeCoffeeFaqs.index(),
                        icon: Coffee,
                        permissions: [PERMISSIONS.OFFICE_COFFEE_FAQS.INDEX],
                    },
                    {
                        title: 'About FAQs',
                        href: adminAboutFaqs.index(),
                        icon: CircleHelp,
                        permissions: [PERMISSIONS.ABOUT_FAQS.INDEX],
                    },
                ],
            },
            /*
             * Access Control — commented out so neither admin nor super-admin
             * sees Roles / Permissions / Settings in the sidebar. Routes remain.
             * To restore: re-import ShieldCheck, Shield, KeyRound, Settings,
             * LockKeyhole, adminRoles, adminPermissions, and uncomment below.
             *
             * {
             *     title: 'Access Control',
             *     icon: ShieldCheck,
             *     permissions: [
             *         PERMISSIONS.USERS.INDEX,
             *         PERMISSIONS.ROLES.INDEX,
             *         PERMISSIONS.PERMISSIONS.INDEX,
             *     ],
             *     items: [
             *         {
             *             title: 'Users',
             *             href: adminUsers.index(),
             *             icon: Users,
             *             permissions: [PERMISSIONS.USERS.INDEX],
             *         },
             *         {
             *             title: 'Roles',
             *             href: adminRoles.index(),
             *             icon: Shield,
             *             permissions: [PERMISSIONS.ROLES.INDEX],
             *         },
             *         {
             *             title: 'Permissions',
             *             href: adminPermissions.index(),
             *             icon: KeyRound,
             *             permissions: [PERMISSIONS.PERMISSIONS.INDEX],
             *         },
             *         {
             *             title: 'Settings',
             *             icon: Settings,
             *             permissions: [PERMISSIONS.SETTINGS.INDEX],
             *             items: [
             *                 {
             *                     title: 'Profile',
             *                     href: '#',
             *                     icon: User,
             *                     permissions: [PERMISSIONS.SETTINGS.INDEX],
             *                 },
             *                 {
             *                     title: 'Security',
             *                     icon: Shield,
             *                     permissions: [PERMISSIONS.SETTINGS.INDEX],
             *                     items: [
             *                         {
             *                             title: 'Password',
             *                             href: '#',
             *                             icon: LockKeyhole,
             *                             permissions: [PERMISSIONS.SETTINGS.INDEX],
             *                         },
             *                     ],
             *                 },
             *             ],
             *         },
             *     ],
             * },
             */
        );

        return items;
    }, [isAdmin, credits]);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="h-auto! py-1.5 group-data-[collapsible=icon]:size-8!"
                        >
                            <Link
                                href={dashboard()}
                                prefetch
                                className="w-full"
                            >
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <SidebarNav items={mainNav} label="Platform" />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
