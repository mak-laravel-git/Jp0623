type SmsConsentCheckboxProps = {
    checked: boolean;
    onChange: (checked: boolean) => void;
    name?: string;
};

export function SmsConsentCheckbox({
    checked,
    onChange,
    name = 'sms_consent',
}: SmsConsentCheckboxProps) {
    return (
        <label className="col-span-full inline-flex cursor-pointer items-start gap-2 rounded-lg border-2 border-brand-line px-3.5 py-2 text-sm font-medium">
            <input
                type="checkbox"
                name={name}
                checked={checked}
                onChange={(event) => onChange(event.target.checked)}
                className="mt-0.5 accent-brass"
            />
            <span>
                (Optional) I agree to receive SMS text messages from Vending
                Machine Location Services at the phone number I provided about
                my request, service updates and offers. Message frequency
                varies. Msg &amp; data rates may apply. Reply STOP to opt out or
                HELP for help. Consent is not a condition of any purchase or
                of submitting this form. See our{' '}
                <a
                    href="/legal/privacy"
                    target="_blank"
                    rel="noreferrer"
                    className="underline"
                >
                    Privacy Policy
                </a>{' '}
                and{' '}
                <a
                    href="/legal/terms"
                    target="_blank"
                    rel="noreferrer"
                    className="underline"
                >
                    Terms
                </a>
                .
            </span>
        </label>
    );
}
