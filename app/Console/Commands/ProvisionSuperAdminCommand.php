<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRule;

class ProvisionSuperAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storefleet:super-admin:provision 
                            {--force : Bypass the existing Super Admin check}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Provision the initial Super Administrator with mandatory two-factor authentication';

    public function handle(TwoFactorAuthenticationService $twoFactorService): int
    {
        $this->components->info('StoreFleet Super Admin Provisioning Tool');

        // Security check: Refuse unsafe repeat execution unless --force is specified
        $existingSuperAdmin = User::where('is_super_admin', true)->first();

        if ($existingSuperAdmin && ! $this->option('force')) {
            $this->components->error("A Super Admin already exists in this installation: {$existingSuperAdmin->email}");
            $this->components->warn('Repeat provisioning is refused to prevent unauthorized administrative escalation.');
            $this->line('If you are the legitimate operator and intentionally need an additional Super Admin, re-run with --force.');

            return self::FAILURE;
        }

        if ($existingSuperAdmin && $this->option('force')) {
            if (! $this->confirm('A Super Admin already exists. Do you really want to provision an additional Super Admin?', false)) {
                $this->components->info('Provisioning aborted by operator.');

                return self::SUCCESS;
            }
        }

        // Collect inputs interactively
        $name = (string) $this->ask('Full Name');
        $email = (string) $this->ask('Email Address');
        $password = (string) $this->secret('Password (min 10 characters, mixed case, numbers)');
        $passwordConfirmation = (string) $this->secret('Confirm Password');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'same:password_confirmation', PasswordRule::min(10)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        // Provision mandatory Two-Factor Authentication credentials
        $twoFactorSecret = $twoFactorService->generateSecretKey();
        $recoveryCodes = $twoFactorService->generateRecoveryCodes(8);
        $otpauthUrl = $twoFactorService->getQrCodeUrl('StoreFleet', $email, $twoFactorSecret);

        // Create the Super Admin record
        $user = new User;
        $user->name = $name;
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->role = 'super_admin';
        $user->is_super_admin = true;
        $user->is_active = true;
        $user->two_factor_secret = $twoFactorSecret;
        $user->two_factor_recovery_codes = $recoveryCodes;
        $user->two_factor_confirmed_at = now();
        $user->save();

        // Audit log the provisioning event (passwords and 2FA secrets are strictly omitted)
        AuditLog::log('super_admin.provisioned', $user->id, [
            'email' => $user->email,
            'name' => $user->name,
            'provisioned_via' => 'cli',
        ]);

        $this->newLine();
        $this->components->info('Super Admin successfully created!');
        $this->newLine();

        $this->components->warn('IMPORTANT: Configure your Authenticator app now. These secrets are displayed once and never stored in plain text.');
        $this->table(
            ['Parameter', 'Value'],
            [
                ['Super Admin Name', $user->name],
                ['Super Admin Email', $user->email],
                ['2FA Secret Key (Manual entry)', $twoFactorSecret],
                ['2FA OTPAuth URI', $otpauthUrl],
            ]
        );

        $this->newLine();
        $this->components->info('Emergency Single-Use Recovery Codes:');
        foreach ($recoveryCodes as $index => $code) {
            $this->line(sprintf('  [%d] %s', $index + 1, $code));
        }

        $this->newLine();
        $this->line('Store these recovery codes in a secure, encrypted password manager.');
        $this->newLine();

        return self::SUCCESS;
    }
}
