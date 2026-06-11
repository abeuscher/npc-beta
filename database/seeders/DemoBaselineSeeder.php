<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\RandomDataGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The curated CRM baseline a prospect sees on first entry to the demo node.
 *
 * Idempotent: wipes the prior scrub-data baseline, then regenerates a fixed
 * set, so re-running (the daily demo:reset) returns the same prospect-facing
 * shape. Runs the generator under a super_admin acting context because
 * events/pages carry a NOT NULL author_id and the generator gates writes on
 * the super_admin role.
 */
class DemoBaselineSeeder extends Seeder
{
    /**
     * The baseline counts. Transactions are created implicitly from active
     * donations and memberships, so they are not listed here.
     */
    public const BASELINE = [
        'contacts'      => 40,
        'events'        => 6,
        'registrations' => 25,
        'donations'     => 30,
        'memberships'   => 15,
        'posts'         => 5,
        'products'      => 6,
    ];

    public function run(): void
    {
        $actor    = $this->resolveSuperAdmin();
        $previous = Auth::user();

        Auth::login($actor);

        try {
            app(RandomDataGenerator::class)->wipe();
            app(RandomDataGenerator::class)->generate(self::BASELINE);
            $this->designateHeroContact();
        } finally {
            $previous ? Auth::login($previous) : Auth::logout();
        }

        // Ensure the demo (and super_admin) dashboard arrangements exist on every
        // baseline restore — covers the soft-reset path, which skips
        // migrate:fresh --seed. Idempotent.
        (new DashboardViewSeeder())->run();
    }

    /**
     * Float one well-stocked generated contact to the top of the contacts list
     * (sorted created_at desc) so the guided product tour opens to a rich
     * record — an active membership plus a run of donations and their
     * transactions, all already created by the generator. The tour finds it by
     * this email (see AdminPanelProvider's tour URL map); promoting an existing
     * contact reuses the generator's correct financial wiring rather than
     * hand-building it.
     */
    protected function designateHeroContact(): void
    {
        $hero = \App\Models\Contact::query()
            ->whereHas('memberships', fn ($q) => $q->where('status', 'active'))
            ->whereHas('donations')
            ->first()
            ?? \App\Models\Contact::query()->whereHas('memberships')->first()
            ?? \App\Models\Contact::query()->latest('id')->first();

        if (! $hero) {
            return;
        }

        $hero->forceFill([
            'email'      => 'tour.hero@nphelper.demo',
            'created_at' => now(),
        ])->save();
    }

    /**
     * Reuse the operator super_admin if the install has one; otherwise mint a
     * system actor with an unguessable password (no auto-login route points at
     * it, mirroring the shared demo user). Never deleted — events/pages FK it
     * with ON DELETE RESTRICT.
     */
    protected function resolveSuperAdmin(): User
    {
        $existing = User::role('super_admin')->first();
        if ($existing) {
            return $existing;
        }

        $user = User::firstOrCreate(
            ['email' => 'demo-system@demo.local'],
            [
                'name'      => 'Demo System',
                'password'  => Hash::make(Str::random(40)),
                'is_active' => true,
            ]
        );

        $user->syncRoles(['super_admin']);

        return $user;
    }
}
