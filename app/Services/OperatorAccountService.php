<?php

namespace App\Services;

use App\Mail\AccountEmailOtpMail;
use App\Models\User;
use App\Support\AccountOtp;
use App\Support\PhoneNumber;
use App\Support\RegisterOtp;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Saves the changes a member makes on the "Account info" page.
 */
class OperatorAccountService
{
    public function __construct(private TwilioSmsService $sms) {}

    /**
     * A new phone number is confirmed by SMS code when Twilio is set up,
     * and by the current password when it is not.
     */
    public function usesSmsVerification(): bool
    {
        return $this->sms->isEnabled();
    }

    /**
     * @param  array{first_name: string, last_name: string, company_name?: string}  $data
     */
    public function updateProfile(User $user, array $data): void
    {
        DB::transaction(function () use ($user, $data): void {
            $name = trim($data['first_name'].' '.$data['last_name']);

            $user->update(['name' => Str::limit($name, 255, '')]);
            $user->operatorProfile?->update(Arr::only($data, ['first_name', 'last_name', 'company_name']));
        });
    }

    /**
     * @throws ValidationException
     */
    public function sendEmailCode(User $user, string $email): void
    {
        $code = RegisterOtp::generateCode();

        AccountOtp::store($user, AccountOtp::EMAIL, $email, $code);

        try {
            Mail::to($email)->send(new AccountEmailOtpMail($code));
        } catch (Throwable $exception) {
            report($exception);
            AccountOtp::forget($user, AccountOtp::EMAIL);

            throw ValidationException::withMessages([
                'email' => 'We could not send the code to this email. Please try again in a moment.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function changeEmail(User $user, string $email, string $code): void
    {
        $this->ensureCodeMatches($user, AccountOtp::EMAIL, $email, $code);

        DB::transaction(function () use ($user, $email): void {
            // A reset link sent to the old address must stop working.
            Password::broker()->deleteToken($user);

            $user->forceFill(['email' => $email, 'email_verified_at' => now()])->save();
            $user->operatorProfile?->update(['email_code_confirmed_at' => now()]);
        });

        AccountOtp::forget($user, AccountOtp::EMAIL);
    }

    /**
     * @throws ValidationException
     */
    public function sendPhoneCode(User $user, string $phone): void
    {
        $number = (string) PhoneNumber::toE164($phone);
        $code = RegisterOtp::generateCode();

        AccountOtp::store($user, AccountOtp::PHONE, $number, $code);

        try {
            $this->sms->send($number, sprintf(
                '%s verification code: %s. It expires in 10 minutes.',
                config('app.name'),
                $code,
            ));
        } catch (Throwable $exception) {
            report($exception);
            AccountOtp::forget($user, AccountOtp::PHONE);

            throw ValidationException::withMessages([
                'phone' => 'We could not text a code to this number. Check the number and try again.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function changePhone(User $user, string $phone, ?string $code): void
    {
        if ($this->usesSmsVerification()) {
            $this->ensureCodeMatches($user, AccountOtp::PHONE, (string) PhoneNumber::toE164($phone), (string) $code);
        }

        $user->operatorProfile?->update(['phone' => $phone]);

        AccountOtp::forget($user, AccountOtp::PHONE);
    }

    /**
     * @throws ValidationException
     */
    private function ensureCodeMatches(User $user, string $purpose, string $target, string $code): void
    {
        if (! AccountOtp::verify($user, $purpose, $target, $code)) {
            throw ValidationException::withMessages([
                'code' => 'The verification code is invalid or has expired.',
            ]);
        }
    }
}
