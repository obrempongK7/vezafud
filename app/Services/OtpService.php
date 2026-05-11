<?php

namespace App\Services;

use App\Models\OtpVerification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Twilio\Rest\Client as TwilioClient;

class OtpService
{
    private int $otpLength = 6;
    private int $expiryMinutes = 10;
    private int $maxAttempts = 5;
    private int $cooldownSeconds = 60;

    public function sendEmailOtp(string $email, string $purpose): void
    {
        $this->checkCooldown("email:{$email}:{$purpose}");
        $otp = $this->generateOtp();
        $this->saveOtp($email, 'email', $otp, $purpose);
        Mail::to($email)->send(new \App\Mail\OtpMail($otp, $purpose));
        Cache::put("otp_cooldown:email:{$email}:{$purpose}", true, $this->cooldownSeconds);
    }

    public function sendSmsOtp(string $phone, string $purpose): void
    {
        $this->checkCooldown("phone:{$phone}:{$purpose}");
        $otp = $this->generateOtp();
        $this->saveOtp($phone, 'phone', $otp, $purpose);
        $this->sendSms($phone, "Your Vezafud OTP is: {$otp}. Valid for {$this->expiryMinutes} minutes. Do not share this code.");
        Cache::put("otp_cooldown:phone:{$phone}:{$purpose}", true, $this->cooldownSeconds);
    }

    public function verify(string $identifier, string $identifierType, string $otp, string $purpose): bool
    {
        $record = OtpVerification::where('identifier', $identifier)
            ->where('identifier_type', $identifierType)
            ->where('purpose', $purpose)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$record) return false;

        $record->increment('attempts');

        if ($record->attempts > $this->maxAttempts) {
            $record->delete();
            return false;
        }

        if ($record->otp !== $otp) return false;

        $record->update(['is_used' => true]);
        return true;
    }

    private function saveOtp(string $identifier, string $type, string $otp, string $purpose): void
    {
        OtpVerification::where('identifier', $identifier)
            ->where('identifier_type', $type)
            ->where('purpose', $purpose)
            ->where('is_used', false)
            ->delete();

        OtpVerification::create([
            'identifier'      => $identifier,
            'identifier_type' => $type,
            'otp'             => $otp,
            'purpose'         => $purpose,
            'expires_at'      => now()->addMinutes($this->expiryMinutes),
        ]);
    }

    private function generateOtp(): string
    {
        return str_pad((string) random_int(0, 10 ** $this->otpLength - 1), $this->otpLength, '0', STR_PAD_LEFT);
    }

    private function checkCooldown(string $key): void
    {
        if (Cache::has("otp_cooldown:{$key}")) {
            throw new \Exception("Please wait {$this->cooldownSeconds} seconds before requesting another OTP.");
        }
    }

    private function sendSms(string $phone, string $message): void
    {
        try {
            $twilio = new TwilioClient(config('services.twilio.sid'), config('services.twilio.token'));
            $twilio->messages->create($phone, ['from' => config('services.twilio.from'), 'body' => $message]);
        } catch (\Exception $e) {
            \Log::error("SMS send failed: " . $e->getMessage());
            throw new \Exception("Failed to send SMS. Please try again.");
        }
    }
}
